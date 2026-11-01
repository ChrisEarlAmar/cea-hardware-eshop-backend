<?php

namespace App\Services;

use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\PaymentStartFailedException;
use App\Mail\OrderConfirmationMail;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\OrderApproval;
use App\Models\User;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

class OrderService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly PricingEngine $pricingEngine,
        private readonly PaymentService $paymentService,
        private readonly CouponService $couponService,
        private readonly GiftCardService $giftCardService,
        private readonly LoyaltyService $loyaltyService,
        private readonly TaxService $taxService,
    ) {
    }

    /**
     * Turns a cart into a real order: decrements stock for every item
     * (reusing the same atomic primitive Stage 6 introduced - nothing here
     * reimplements that check), snapshots each line's price at the moment
     * of purchase so the order stays accurate even if catalog prices
     * change later, and clears the cart. All one transaction: if any item
     * has insufficient stock, or the coupon turns out to be invalid, nothing
     * is created and nothing is decremented, including items earlier in the
     * same cart.
     *
     * $extras absorbs every optional input the checkout path has grown
     * (guest identity, addresses, a coupon code, a B2B PO reference/note)
     * behind a single stable signature, rather than growing the parameter
     * list every time a new optional field is added:
     *   - shipping_address_id, billing_address_id: int|null
     *   - guest_email, guest_name: string|null (only for a cart with no user)
     *   - customer_note, po_reference: string|null
     *   - coupon_code: string|null
     *   - gift_card_code: string|null (a guest can redeem one too, unlike
     *     loyalty points, since a gift card is a code, not an account)
     *   - redeem_loyalty_points: int|null (signed-in users only; capped to
     *     both the buyer's balance and what the order still owes)
     *
     * Money flows through the pipeline in this fixed order: subtotal ->
     * coupon discount -> loyalty-points discount -> tax (charged on what's
     * left after those two real discounts) -> gift card, applied last since
     * it behaves like a payment instrument rather than a discount - most
     * real storefronts don't let a gift card reduce the taxable base either.
     *
     * The order normally starts life awaiting payment, not pending - stock
     * is reserved immediately (so nobody else can buy the last unit while
     * this customer is still entering card details), but nothing is
     * confirmed to the customer yet, since no money has actually moved.
     * That moment is handled by transitionTo() when the payment webhook
     * fires.
     *
     * The one exception is a signed-in B2B buyer whose account owner has
     * capped their spending: if this order's total exceeds that cap, it is
     * created in STATUS_PENDING_APPROVAL instead, and payment is never
     * started for it here - see startPayment() and OrderApprovalController.
     */
    public function createFromCart(Cart $cart, array $extras = []): Order
    {
        return DB::transaction(function () use ($cart, $extras) {
            $user = $cart->user;
            $account = $user?->account;

            $coupon = null;
            if (! empty($extras['coupon_code'])) {
                $coupon = Coupon::where('code', $extras['coupon_code'])->first();

                if ($coupon === null || ! $coupon->isUsable()) {
                    throw new InvalidArgumentException('This coupon code is not valid.');
                }
            }

            $giftCard = null;
            if (! empty($extras['gift_card_code'])) {
                $giftCard = GiftCard::where('code', $extras['gift_card_code'])->first();

                if ($giftCard === null || ! $giftCard->isUsable()) {
                    throw new InvalidArgumentException('This gift card code is not valid.');
                }
            }

            $order = Order::create([
                'user_id' => $cart->user_id,
                'status' => Order::STATUS_AWAITING_PAYMENT,
                'total' => '0.00',
                'payment_status' => Order::PAYMENT_UNPAID,
                'guest_email' => $extras['guest_email'] ?? null,
                'guest_name' => $extras['guest_name'] ?? null,
                'shipping_address_id' => $extras['shipping_address_id'] ?? null,
                'billing_address_id' => $extras['billing_address_id'] ?? null,
                'customer_note' => $extras['customer_note'] ?? null,
                'po_reference' => $extras['po_reference'] ?? null,
                'coupon_id' => $coupon?->id,
                'gift_card_id' => $giftCard?->id,
            ]);

            $subtotalCents = 0;

            foreach ($cart->items as $item) {
                $this->inventoryService->decrementStock($item->variant, $item->quantity);

                $priced = $this->pricingEngine->priceFor($account, $item->variant, $item->quantity);

                $order->items()->create([
                    'product_variant_id' => $item->variant->id,
                    'sku' => $item->variant->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $priced['unit_price'],
                    'line_total' => $priced['line_total'],
                ]);

                $subtotalCents += $this->toCents($priced['line_total']);
            }

            $couponDiscountCents = 0;

            if ($coupon !== null) {
                $subtotal = $this->toDecimalString($subtotalCents);

                if ($coupon->min_order_total !== null && $subtotalCents < $this->toCents((string) $coupon->min_order_total)) {
                    throw new InvalidArgumentException("This coupon requires a minimum order of {$coupon->min_order_total}.");
                }

                $couponDiscountCents = $this->toCents($this->couponService->discountFor($coupon, $subtotal));
            }

            $afterCouponCents = max(0, $subtotalCents - $couponDiscountCents);

            // Loyalty points are only ever a signed-in perk - a guest has no
            // balance to spend from.
            $loyaltyPointsToRedeem = 0;
            $loyaltyDiscountCents = 0;

            if ($user !== null && ! empty($extras['redeem_loyalty_points'])) {
                $loyaltyPointsToRedeem = $this->loyaltyService->pointsToRedeem(
                    (int) $extras['redeem_loyalty_points'],
                    $user->loyalty_points_balance,
                    $this->toDecimalString($afterCouponCents),
                );
                $loyaltyDiscountCents = $this->toCents($this->loyaltyService->discountForPoints($loyaltyPointsToRedeem));
            }

            $taxableCents = max(0, $afterCouponCents - $loyaltyDiscountCents);

            $shippingAddress = $extras['shipping_address_id'] ?? null
                ? Address::find($extras['shipping_address_id'])
                : null;
            $taxCents = $this->toCents($this->taxService->taxFor($shippingAddress?->state, $this->toDecimalString($taxableCents)));

            $beforeGiftCardCents = $taxableCents + $taxCents;

            $giftCardAmountCents = 0;
            if ($giftCard !== null) {
                $giftCardAmountCents = $this->toCents($this->giftCardService->amountToApply($giftCard, $this->toDecimalString($beforeGiftCardCents)));
            }

            $totalCents = max(0, $beforeGiftCardCents - $giftCardAmountCents);

            // Every redemption is applied now, inside this transaction, so a
            // later failure (insufficient stock further down an earlier
            // loop iteration would have already thrown) rolls it back along
            // with everything else - never charged for real until payment
            // succeeds, but reserved immediately the same way stock is.
            if ($coupon !== null) {
                $this->couponService->redeem($coupon);
            }

            if ($giftCard !== null && $giftCardAmountCents > 0) {
                $this->giftCardService->redeem($giftCard, $this->toDecimalString($giftCardAmountCents));
            }

            if ($loyaltyPointsToRedeem > 0) {
                $user->decrement('loyalty_points_balance', $loyaltyPointsToRedeem);
            }

            $status = Order::STATUS_AWAITING_PAYMENT;

            if ($user !== null && $user->spending_limit_cents !== null && $totalCents > $user->spending_limit_cents) {
                $status = Order::STATUS_PENDING_APPROVAL;
            }

            $order->update([
                'total' => $this->toDecimalString($totalCents),
                'discount_total' => $this->toDecimalString($couponDiscountCents + $loyaltyDiscountCents),
                'tax_total' => $this->toDecimalString($taxCents),
                'gift_card_amount' => $this->toDecimalString($giftCardAmountCents),
                'loyalty_points_redeemed' => $loyaltyPointsToRedeem,
                'loyalty_points_earned' => $this->loyaltyService->pointsEarnedFor($this->toDecimalString($taxableCents)),
                'status' => $status,
            ]);

            $order->statusHistory()->create([
                'from_status' => null,
                'to_status' => $status,
                'changed_by' => $cart->user_id,
            ]);

            if ($status === Order::STATUS_PENDING_APPROVAL) {
                OrderApproval::create([
                    'order_id' => $order->id,
                    'requested_by' => $cart->user_id,
                    'status' => OrderApproval::STATUS_PENDING,
                    'threshold_amount' => $this->toDecimalString($user->spending_limit_cents),
                ]);
            }

            $cart->items()->delete();

            return $order->load('items');
        });
    }

    /**
     * Starts collecting payment for an order that is currently
     * awaiting_payment. Pulled out of the checkout flow so the same
     * failure handling - release the stock reservation immediately by
     * cancelling the order, rather than leaving it held against an order
     * nobody can ever pay for - applies identically whether payment starts
     * right after checkout or later, once a B2B approval clears an order
     * that was held in STATUS_PENDING_APPROVAL.
     *
     * @return array{id: string, client_secret: string}
     */
    public function startPayment(Order $order): array
    {
        try {
            $intent = $this->paymentService->createPaymentIntentFor($order);
        } catch (Throwable $e) {
            $this->transitionTo($order, Order::STATUS_CANCELLED, null, 'Payment could not be started.');
            report($e);

            throw new PaymentStartFailedException($order, $e);
        }

        $order->update([
            'payment_intent_id' => $intent['id'],
            'payment_status' => Order::PAYMENT_REQUIRES_PAYMENT,
        ]);

        return $intent;
    }

    /**
     * Applies a state transition if - and only if - the order's current
     * status allows it (see Order::canTransitionTo). Cancelling restores
     * the stock that was decremented at checkout, since the goods haven't
     * shipped yet, and refunds any gift-card amount or loyalty points that
     * were redeemed at checkout, for the same reason - none of it was ever
     * actually charged. There's nothing to reverse for loyalty points
     * *earned*, since those are only ever credited once payment succeeds
     * (see below), never at creation time. All of that restoration is part
     * of the same transaction as the status change. Every transition -
     * including this one - is appended to the order's status history, never
     * just overwriting the current status.
     *
     * Notifications are dispatched only after the transaction commits, not
     * from inside it - queuing a job/notification before the row it
     * references has actually committed would let a worker race ahead of
     * the write it depends on.
     *
     * $actor is the user who initiated the change, or null when the system
     * did (e.g. a payment webhook). It is intentionally not derived from
     * the currently-authenticated request here, since some callers (the
     * webhook handler) have no authenticated user at all.
     */
    public function transitionTo(Order $order, string $newStatus, ?User $actor = null, ?string $note = null): void
    {
        if (! $order->canTransitionTo($newStatus)) {
            throw new InvalidOrderTransitionException($order, $newStatus);
        }

        $fromStatus = $order->status;

        DB::transaction(function () use ($order, $newStatus, $fromStatus, $actor, $note) {
            if ($newStatus === Order::STATUS_CANCELLED) {
                foreach ($order->items as $item) {
                    DB::table('product_variants')
                        ->where('id', $item->product_variant_id)
                        ->increment('stock', $item->quantity);
                }

                if ($order->gift_card_id !== null && (float) $order->gift_card_amount > 0) {
                    $this->giftCardService->refund($order->giftCard, (string) $order->gift_card_amount);
                }

                if ($order->loyalty_points_redeemed > 0 && $order->user !== null) {
                    $order->user->increment('loyalty_points_balance', $order->loyalty_points_redeemed);
                }
            }

            // Points are only ever credited once payment actually clears -
            // never at checkout time, since an order that's never paid for
            // shouldn't earn anything.
            if ($fromStatus === Order::STATUS_AWAITING_PAYMENT && $newStatus === Order::STATUS_PENDING
                && $order->loyalty_points_earned > 0 && $order->user !== null) {
                $order->user->increment('loyalty_points_balance', $order->loyalty_points_earned);
            }

            $order->update(['status' => $newStatus]);

            $order->statusHistory()->create([
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'changed_by' => $actor?->id,
                'note' => $note,
            ]);
        });

        $order->refresh();

        // The customer-facing "your order is confirmed" moment is when
        // payment actually clears, not when the cart was submitted - so the
        // confirmation email and the "order placed" notification both fire
        // here, on the awaiting_payment -> pending transition, rather than
        // at cart-to-order creation time. A guest order has no User to
        // notify in-app, but still gets the confirmation email at whatever
        // address they checked out with.
        if ($fromStatus === Order::STATUS_AWAITING_PAYMENT && $newStatus === Order::STATUS_PENDING) {
            $order->user?->notify(new OrderPlaced($order));

            if ($order->user !== null) {
                Mail::to($order->user)->queue(new OrderConfirmationMail($order));
            } elseif ($order->guest_email !== null) {
                Mail::to($order->guest_email)->queue(new OrderConfirmationMail($order));
            }
        } else {
            $order->user?->notify(new OrderStatusChanged($order));
        }
    }

    private function toCents(string $decimal): int
    {
        return (int) round(((float) $decimal) * 100);
    }

    private function toDecimalString(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
