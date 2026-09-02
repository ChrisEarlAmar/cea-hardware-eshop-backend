<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer-facing RMA flow: a return can only be requested for an item on
 * the requester's own, already-delivered order, and only once at a time -
 * see ReturnRequest::canTransitionTo for the state machine the admin side
 * (App\Http\Controllers\Api\Admin\ReturnController) enforces from here on.
 */
class ReturnController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paginator = ReturnRequest::query()
            ->whereHas('order', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['order:id', 'orderItem:id,sku'])
            ->latest('requested_at')
            ->paginate($request->integer('per_page', 25));

        return ApiResponse::success(
            $paginator->getCollection()->map(fn (ReturnRequest $returnRequest) => $this->payload($returnRequest)),
            meta: [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(Request $request, OrderItem $orderItem): JsonResponse
    {
        $order = $orderItem->order;

        if ($order === null || $order->user_id !== $request->user()->id) {
            abort(404);
        }

        if ($order->status !== Order::STATUS_DELIVERED) {
            return ApiResponse::error(
                'order_not_delivered',
                'A return can only be requested once the order has been delivered.',
                422,
            );
        }

        $alreadyRequested = ReturnRequest::query()
            ->where('order_item_id', $orderItem->id)
            ->whereIn('status', [ReturnRequest::STATUS_REQUESTED, ReturnRequest::STATUS_APPROVED])
            ->exists();

        if ($alreadyRequested) {
            return ApiResponse::error(
                'return_already_requested',
                'A return has already been requested for this item.',
                422,
            );
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $returnRequest = ReturnRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'reason' => $validated['reason'],
            'status' => ReturnRequest::STATUS_REQUESTED,
            'requested_at' => now(),
        ]);

        return ApiResponse::success($this->payload($returnRequest), status: 201);
    }

    private function payload(ReturnRequest $returnRequest): array
    {
        return [
            'id' => $returnRequest->id,
            'order_id' => $returnRequest->order_id,
            'order_item_id' => $returnRequest->order_item_id,
            'order_item_sku' => $returnRequest->orderItem?->sku,
            'reason' => $returnRequest->reason,
            'status' => $returnRequest->status,
            'requested_at' => $returnRequest->requested_at?->toIso8601String(),
            'resolved_at' => $returnRequest->resolved_at?->toIso8601String(),
            'refund_amount' => $returnRequest->refund_amount,
        ];
    }
}
