<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public order-tracking lookup by order id + email, for a "Track your
 * order" page a visitor can use without signing in.
 *
 * Deliberately a separate endpoint from GuestOrderController::show rather
 * than a change to it: that controller is the guest checkout/payment flow's
 * own order-status lookup, is relied on exactly as written, and its
 * `$order->user_id !== null` guard is intentional there - a guest checkout
 * session should never resolve to somebody's signed-in order. This
 * controller is the general-purpose twin a public tracking page actually
 * needs: it matches a guest order the same way (by guest_email), but also
 * matches an authenticated order against its owning user's account email,
 * so a signed-in customer can track an order here too without logging in.
 */
class OrderTrackingController extends Controller
{
    public function show(Request $request, Order $order): JsonResponse
    {
        $email = (string) $request->query('email');

        if ($email === '' || ! $this->emailMatches($order, $email)) {
            abort(404);
        }

        $order->loadMissing('items');

        return ApiResponse::success([
            'id' => $order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total' => $order->total,
            'tracking_carrier' => $order->tracking_carrier,
            'tracking_number' => $order->tracking_number,
            'items' => $order->items->map(fn ($item) => [
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
            ]),
            'created_at' => $order->created_at->toIso8601String(),
        ]);
    }

    private function emailMatches(Order $order, string $email): bool
    {
        $ownerEmail = $order->user_id !== null
            ? $order->user?->email
            : $order->guest_email;

        return $ownerEmail !== null && strcasecmp($ownerEmail, $email) === 0;
    }
}
