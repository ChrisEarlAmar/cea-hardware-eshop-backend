<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Support\ApiResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer-facing reviews. Submitting one (store) requires Sanctum auth and
 * proof of a delivered order containing the product - see the `staff`
 * middleware / App\Http\Controllers\Api\Admin\ReviewController for the
 * separate moderation queue a review then sits in before it is public.
 */
class ReviewController extends Controller
{
    public function index(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $approved = $product->approvedReviews();

        $paginator = (clone $approved)
            ->with('user:id,name')
            ->latest()
            ->paginate($validated['per_page'] ?? 10);

        return ApiResponse::success(
            $paginator->getCollection()->map(fn (Review $review) => $this->reviewPayload($review)),
            meta: [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'average_rating' => $this->roundedAverage((clone $approved)->avg('rating')),
                'count' => (clone $approved)->count(),
            ],
        );
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        // "Verified purchase" here means: an order of this user's that has
        // actually been delivered contained a variant of this product. Any
        // other order status (still in flight, or cancelled) doesn't count.
        $qualifyingOrder = Order::query()
            ->where('user_id', $user->id)
            ->where('status', Order::STATUS_DELIVERED)
            ->whereHas('items.variant', fn ($query) => $query->where('product_id', $product->id))
            ->latest()
            ->first();

        if ($qualifyingOrder === null) {
            return ApiResponse::error(
                'no_verified_purchase',
                'You can only review products from an order that has been delivered to you.',
                422,
            );
        }

        try {
            $review = Review::create([
                'product_id' => $product->id,
                'user_id' => $user->id,
                'order_id' => $qualifyingOrder->id,
                'rating' => $validated['rating'],
                'title' => $validated['title'],
                'body' => $validated['body'],
                'status' => Review::STATUS_PENDING,
            ]);
        } catch (UniqueConstraintViolationException) {
            // The (product_id, user_id) unique constraint - one review per
            // customer per product, enforced at the DB layer as the source
            // of truth rather than trusting an application-level check.
            return ApiResponse::error(
                'already_reviewed',
                'You have already reviewed this product.',
                422,
            );
        }

        $review->setRelation('user', $user);

        return ApiResponse::success($this->reviewPayload($review), status: 201);
    }

    private function reviewPayload(Review $review): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'status' => $review->status,
            'author' => $review->relationLoaded('user') ? $review->user?->name : null,
            'created_at' => $review->created_at->toIso8601String(),
        ];
    }

    private function roundedAverage(mixed $average): ?float
    {
        return $average !== null ? round((float) $average, 1) : null;
    }
}
