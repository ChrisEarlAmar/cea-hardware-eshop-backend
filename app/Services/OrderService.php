<?php

namespace App\Services;

use App\Exceptions\InvalidOrderTransitionException;
use App\Mail\OrderConfirmationMail;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrderService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly PricingEngine $pricingEngine,
    ) {
    }

    /**
     * Turns a cart into a real order: decrements stock for every item
     * (reusing the same atomic primitive Stage 6 introduced - nothing here
     * reimplements that check), snapshots each line's price at the moment
     * of purchase so the order stays accurate even if catalog prices
     * change later, and clears the cart. All one transaction: if any item
     * has insufficient stock, nothing is created and nothing is
     * decremented, including items earlier in the same cart.
     *
     * The order starts life awaiting payment, not pending - stock is
     * reserved immediately (so nobody else can buy the last unit while this
     * customer is still entering card details), but nothing is confirmed to
     * the customer yet, since no money has actually moved. That moment is
     * handled by transitionTo() when the payment webhook fires.
     */
    public function createFromCart(Cart $cart): Order
    {
        return DB::transaction(function () use ($cart) {
            $account = $cart->user->account;

            $order = Order::create([
                'user_id' => $cart->user_id,
                'status' => Order::STATUS_AWAITING_PAYMENT,
                'total' => '0.00',
                'payment_status' => Order::PAYMENT_UNPAID,
            ]);

            $totalCents = 0;

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

                $totalCents += $this->toCents($priced['line_total']);
            }

            $order->update(['total' => number_format($totalCents / 100, 2, '.', '')]);

            $order->statusHistory()->create([
                'from_status' => null,
                'to_status' => Order::STATUS_AWAITING_PAYMENT,
                'changed_by' => $cart->user_id,
            ]);

            $cart->items()->delete();

            return $order->load('items');
        });
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
        // at cart-to-order creation time.
        if ($fromStatus === Order::STATUS_AWAITING_PAYMENT && $newStatus === Order::STATUS_PENDING) {
            $order->user->notify(new OrderPlaced($order));
            Mail::to($order->user)->queue(new OrderConfirmationMail($order));
        } else {
            $order->user->notify(new OrderStatusChanged($order));
        }
    }

    private function toCents(string $decimal): int
    {
        return (int) round(((float) $decimal) * 100);
    }
}
