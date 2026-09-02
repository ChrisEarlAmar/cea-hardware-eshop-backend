<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-service data export and account deletion requests, scoped entirely
 * to the authenticated user's own data - there is no cross-account access
 * here, unlike the Admin\* controllers.
 */
class AccountDataController extends Controller
{
    /**
     * A full export of everything this user owns. Returned as an ordinary
     * ApiResponse::success() body - the frontend Route Handler that proxies
     * this is responsible for adding the Content-Disposition header that
     * turns it into a download, so nothing binary-specific is needed here.
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = $user->orders()->with('items')->latest()->get();

        return ApiResponse::success([
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'orders' => $orders->map(fn (Order $order) => [
                'id' => $order->id,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $order->items->map(fn ($item) => [
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ]),
            ]),
            'addresses' => $user->addresses->map(fn ($address) => [
                'id' => $address->id,
                'label' => $address->label,
                'recipient_name' => $address->recipient_name,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
                'phone' => $address->phone,
                'is_default' => $address->is_default,
                'created_at' => $address->created_at?->toIso8601String(),
            ]),
            'reviews' => $user->reviews->map(fn ($review) => [
                'id' => $review->id,
                'product_id' => $review->product_id,
                'rating' => $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'status' => $review->status,
                'created_at' => $review->created_at?->toIso8601String(),
            ]),
            'wishlist_items' => $user->wishlistItems->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'created_at' => $item->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Idempotent: a second call while a request is already pending must not
     * bump the original timestamp - that timestamp is the actual signal an
     * admin will eventually act on (see Admin\DeletionRequestController).
     */
    public function requestDeletion(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->deletion_requested_at === null) {
            $user->forceFill(['deletion_requested_at' => now()])->save();
        }

        return ApiResponse::success([
            'deletion_requested_at' => $user->deletion_requested_at?->toIso8601String(),
        ]);
    }

    /**
     * Lets a user change their mind any time before an admin has actually
     * processed the request - there's no grace-period deadline enforced
     * here, since account deletion itself isn't implemented yet either
     * (see Admin\DeletionRequestController's class docblock).
     */
    public function cancelDeletion(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill(['deletion_requested_at' => null])->save();

        return ApiResponse::success([
            'deletion_requested_at' => null,
        ]);
    }
}
