<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * B2B "company order history": every order placed by any member of the
 * requester's own account, not just the requester's own orders. Read-only,
 * and deliberately open to any member (owner or member) - unlike
 * AccountPolicy::manageMembers, this task's brief calls out order history
 * as a common, sensible thing for the whole account to be able to see, not
 * just the owner.
 */
class CompanyOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->account_id === null) {
            return ApiResponse::error('no_account', 'You do not belong to an account yet.', 404);
        }

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Order::query()
            ->with('user:id,name,email,account_id')
            ->whereHas('user', fn ($q) => $q->where('account_id', $user->account_id))
            ->latest()
            ->paginate($validated['per_page'] ?? 25);

        return ApiResponse::success(
            $paginator->getCollection()->map(fn (Order $order) => [
                'id' => $order->id,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => $order->total,
                'po_reference' => $order->po_reference,
                'placed_by' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
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
