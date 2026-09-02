<?php

namespace App\Services;

use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\PaymentStartFailedException;
use App\Mail\OrderConfirmationMail;
use App\Models\Cart;
use App\Models\Coupon;
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

            $discountCents = 0;

            if ($coupon !== null) {
                $subtotal = $this->toDecimalString($subtotalCents);

                if ($coupon->min_order_total !== null && $subtotalCents < $this->toCents((string) $coupon->min_order_total)) {
                    throw new InvalidArgumentException("This coupon requires a minimum order of {$coupon->min_order_total}.");
                }

                $discountCents = $this->toCents($this->couponService->discountFor($coupon, $subtotal));
                $this->couponService->redeem($coupon);
            }

            $totalCents = max(0, $subtotalCents - $discountCents);

            $status = Order::STATUS_AWAITING_PAYMENT;

            if ($user !== null && $user->spending_limit_cents !== null && $totalCents > $user->spending_limit_cents) {
                $status = Order::STATUS_PENDING_APPROVAL;
            }

            $order->update([
                'total' => $this->toDecimalString($totalCents),
                'discount_total' => $this->toDecimalString($discountCents),
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
     * shipped yet; that restoration is part of the same transaction as the
     * status change. Every transition - including this one - is appended to
     * the order's status history, never just overwriting the current status.
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
