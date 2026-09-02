<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderApproval;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The account owner's side of the B2B spending-limit workflow: an order a
 * member placed over their cap sits in Order::STATUS_PENDING_APPROVAL with
 * an OrderApproval row until the owner approves or rejects it here. Payment
 * is never started from this controller - see OrderController::startPayment
 * for why that has to be a separate, buyer-initiated call.
 */
class OrderApprovalController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->account_id === null) {
            return ApiResponse::error('no_account', 'You do not belong to an account yet.', 404);
        }

        Gate::authorize('manageMembers', $user->account);

        $approvals = OrderApproval::query()
            ->where('status', OrderApproval::STATUS_PENDING)
            ->whereHas('order.user', fn ($query) => $query->where('account_id', $user->account_id))
            ->with(['order.items', 'requestedBy'])
            ->latest()
            ->get();

        return ApiResponse::success($approvals->map(fn (OrderApproval $approval) => $this->approvalPayload($approval)));
    }

    public function approve(Request $request, OrderApproval $approval): JsonResponse
    {
        return $this->decide($request, $approval, OrderApproval::STATUS_APPROVED);
    }

    public function reject(Request $request, OrderApproval $approval): JsonResponse
    {
        return $this->decide($request, $approval, OrderApproval::STATUS_REJECTED);
    }

    private function decide(Request $request, OrderApproval $approval, string $decision): JsonResponse
    {
        $user = $request->user();
        $order = $approval->order()->with('user')->first();

        // 404, not 403, for an approval belonging to a different account -
        // same "don't confirm another account's ids exist" reasoning
        // AccountController::removeMember already applies.
        if ($user->account_id === null || $order === null || $order->user->account_id !== $user->account_id) {
            abort(404);
        }

        Gate::authorize('manageMembers', $user->account);

        if ($approval->status !== OrderApproval::STATUS_PENDING) {
            return ApiResponse::error('already_decided', 'This order has already been decided.', 422);
        }

        $approval->update([
            'status' => $decision,
            'approved_by' => $user->id,
            'resolved_at' => now(),
        ]);

        if ($decision === OrderApproval::STATUS_REJECTED) {
            $this->orderService->transitionTo($order, Order::STATUS_CANCELLED, $user, 'Order rejected by account owner.');

            return ApiResponse::success(['message' => 'Order rejected.']);
        }

        $this->orderService->transitionTo($order, Order::STATUS_AWAITING_PAYMENT, $user, 'Order approved by account owner.');

        return ApiResponse::success(['message' => 'Order approved. The buyer can now complete payment.']);
    }

    private function approvalPayload(OrderApproval $approval): array
    {
        return [
            'id' => $approval->id,
            'status' => $approval->status,
            'threshold_amount' => $approval->threshold_amount,
            'requested_by' => $approval->requestedBy?->name,
            'order' => [
                'id' => $approval->order->id,
                'total' => $approval->order->total,
                'created_at' => $approval->order->created_at->toIso8601String(),
            ],
        ];
    }
}
