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
                'customer' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'account_id' => $order->user->account_id,
                ],
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
}
