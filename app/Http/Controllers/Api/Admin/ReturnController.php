<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff-only RMA review - see App\Http\Controllers\Api\Admin\OrderController
 * for the same cross-account-visibility reasoning. Every transition here
 * goes through ReturnRequest::canTransitionTo rather than trusting the
 * caller, exactly like Order's own state machine.
 */
class ReturnController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = ReturnRequest::query()
            ->with(['order:id,user_id', 'order.user:id,name,email', 'orderItem:id,sku'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('requested_at')
            ->paginate($validated['per_page'] ?? 25);

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

    public function approve(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        return $this->transition($request, $returnRequest, ReturnRequest::STATUS_APPROVED);
    }

    public function reject(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        return $this->transition($request, $returnRequest, ReturnRequest::STATUS_REJECTED);
    }

    /**
     * This is a learning project: no real payment provider is called here.
     * The refund is only ever recorded as a state transition plus the
     * amount the staff member entered - there is no integration with
     * Stripe's (or any) actual refund API.
     */
    public function refund(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        if (! $returnRequest->canTransitionTo(ReturnRequest::STATUS_REFUNDED)) {
            return ApiResponse::error(
                'invalid_transition',
                'This return cannot be refunded from its current status.',
                422,
            );
        }

        $validated = $request->validate([
            'refund_amount' => ['required', 'decimal:0,2'],
        ]);

        $returnRequest->update([
            'status' => ReturnRequest::STATUS_REFUNDED,
            'refund_amount' => $validated['refund_amount'],
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);

        $this->auditLogger->log($request->user(), 'return_request.refunded', $returnRequest, [
            'refund_amount' => $validated['refund_amount'],
        ]);

        return ApiResponse::success($this->payload($returnRequest->fresh()));
    }

    private function transition(Request $request, ReturnRequest $returnRequest, string $status): JsonResponse
    {
        if (! $returnRequest->canTransitionTo($status)) {
            return ApiResponse::error(
                'invalid_transition',
                'This return cannot be moved to that status from its current one.',
                422,
            );
        }

        $returnRequest->update([
            'status' => $status,
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);

        $this->auditLogger->log($request->user(), "return_request.{$status}", $returnRequest);

        return ApiResponse::success($this->payload($returnRequest->fresh()));
    }

    private function payload(ReturnRequest $returnRequest): array
    {
        return [
            'id' => $returnRequest->id,
            'order_id' => $returnRequest->order_id,
            'order_item_id' => $returnRequest->order_item_id,
            'reason' => $returnRequest->reason,
            'status' => $returnRequest->status,
            'requested_at' => $returnRequest->requested_at?->toIso8601String(),
            'resolved_at' => $returnRequest->resolved_at?->toIso8601String(),
            'refund_amount' => $returnRequest->refund_amount,
            'customer' => [
                'name' => $returnRequest->order?->user?->name,
                'email' => $returnRequest->order?->user?->email,
            ],
            'item_sku' => $returnRequest->orderItem?->sku,
        ];
    }
}
