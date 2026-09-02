<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff-only, cross-account visibility - deliberately not scoped to the
 * requesting user's own orders the way the customer-facing OrderController
 * is. Reachable only via the `staff` middleware (see routes/api.php).
 */
class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string'],
            'account_id' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Order::query()
            ->with(['user:id,name,email,account_id'])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['account_id'] ?? null, fn ($q, $accountId) => $q->whereHas(
                'user',
                fn ($uq) => $uq->where('account_id', $accountId)
            ))
            ->latest()
            ->paginate($validated['per_page'] ?? 25);

        return ApiResponse::success(
            $paginator->getCollection()->map(fn (Order $order) => [
                'id' => $order->id,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => $order->total,
                'customer' => $this->customerPayload($order),
                'created_at' => $order->created_at->toIso8601String(),
            ]),
            meta: [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    /**
     * A single order's full detail for the staff order-detail page -
     * deliberately not scoped to any account, the same cross-account
     * visibility as index() above. Added alongside the tracking-update
     * endpoint (see App\Http\Controllers\Api\Admin\ShipmentController) so
     * that page has something to render before submitting the tracking
     * form.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['user:id,name,email,account_id', 'items']);

        return ApiResponse::success([
            'id' => $order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total' => $order->total,
            'discount_total' => $order->discount_total,
            'tracking_carrier' => $order->tracking_carrier,
            'tracking_number' => $order->tracking_number,
            'customer' => $this->customerPayload($order),
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ]),
            'created_at' => $order->created_at->toIso8601String(),
        ]);
    }

    /**
     * A guest order has no `user` row at all - `user_id` is nullable since
     * guest checkout was added - so this can't unconditionally reach into
     * $order->user the way an authenticated-only order model could.
     */
    private function customerPayload(Order $order): array
    {
        if ($order->user === null) {
            return [
                'id' => null,
                'name' => $order->guest_name,
                'email' => $order->guest_email,
                'account_id' => null,
                'guest' => true,
            ];
        }

        return [
            'id' => $order->user->id,
            'name' => $order->user->name,
            'email' => $order->user->email,
            'account_id' => $order->user->account_id,
            'guest' => false,
        ];
    }
}
