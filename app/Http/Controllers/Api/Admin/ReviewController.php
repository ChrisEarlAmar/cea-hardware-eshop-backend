<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff-only moderation queue for customer reviews - see
 * App\Http\Controllers\Api\ReviewController for where reviews are
 * submitted (as STATUS_PENDING) and publicly read back (STATUS_APPROVED
 * only). Reachable only via the `staff` middleware (see routes/api.php).
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Review::query()
            ->where('status', Review::STATUS_PENDING)
            ->with(['product:id,name,slug', 'user:id,name,email'])
            ->latest()
            ->paginate($validated['per_page'] ?? 25);

        return ApiResponse::success(
            $paginator->getCollection()->map(fn (Review $review) => $this->reviewPayload($review)),
            meta: [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function approve(Request $request, Review $review): JsonResponse
    {
        $review->update(['status' => Review::STATUS_APPROVED]);

        $this->auditLogger->log($request->user(), 'review.approve', $review, [
            'product_id' => $review->product_id,
        ]);

        return ApiResponse::success($this->reviewPayload($review->fresh(['product', 'user'])));
    }

    public function reject(Request $request, Review $review): JsonResponse
    {
        $review->update(['status' => Review::STATUS_REJECTED]);

        $this->auditLogger->log($request->user(), 'review.reject', $review, [
            'product_id' => $review->product_id,
        ]);

        return ApiResponse::success($this->reviewPayload($review->fresh(['product', 'user'])));
    }

    private function reviewPayload(Review $review): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'status' => $review->status,
            'product' => $review->relationLoaded('product') && $review->product ? [
                'id' => $review->product->id,
                'name' => $review->product->name,
                'slug' => $review->product->slug,
            ] : null,
            'customer' => $review->relationLoaded('user') && $review->user ? [
                'id' => $review->user->id,
                'name' => $review->user->name,
                'email' => $review->user->email,
            ] : null,
            'created_at' => $review->created_at->toIso8601String(),
        ];
    }
}
