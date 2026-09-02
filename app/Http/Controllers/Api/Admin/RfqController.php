<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RfqRequest;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff-only RFQ queue - deliberately cross-account, same reasoning as
 * App\Http\Controllers\Api\Admin\OrderController. Reachable only via the
 * `staff` middleware (see routes/api.php).
 *
 * "Responding" here is intentionally just a status flag: in a production
 * system this would likely trigger an email to the customer or open a
 * proper quote-negotiation thread with a stored price. For this learning
 * project, staff are expected to follow up with the customer out-of-band
 * (phone/email) and then mark the request responded once that's done.
 */
class RfqController extends Controller
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

        $paginator = RfqRequest::query()
            ->with(['product:id,name,slug', 'user:id,name,email'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($validated['per_page'] ?? 25);

        return ApiResponse::success(
            $paginator->getCollection()->map(fn (RfqRequest $rfq) => $this->payload($rfq)),
            meta: [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function respond(Request $request, RfqRequest $rfq): JsonResponse
    {
        $rfq->update(['status' => RfqRequest::STATUS_RESPONDED]);

        $this->auditLogger->log($request->user(), 'rfq_request.responded', $rfq);

        return ApiResponse::success($this->payload($rfq->fresh(['product', 'user'])));
    }

    private function payload(RfqRequest $rfq): array
    {
        return [
            'id' => $rfq->id,
            'quantity' => $rfq->quantity,
            'message' => $rfq->message,
            'status' => $rfq->status,
            'product' => $rfq->relationLoaded('product') && $rfq->product ? [
                'id' => $rfq->product->id,
                'name' => $rfq->product->name,
                'slug' => $rfq->product->slug,
            ] : null,
            'customer' => $rfq->relationLoaded('user') && $rfq->user ? [
                'id' => $rfq->user->id,
                'name' => $rfq->user->name,
                'email' => $rfq->user->email,
            ] : null,
            'created_at' => $rfq->created_at?->toIso8601String(),
        ];
    }
}
