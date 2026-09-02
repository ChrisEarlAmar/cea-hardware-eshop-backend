<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductSummaryResource;
use App\Models\Product;
use App\Models\WishlistItem;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * The signed-in user's wishlisted products. Reuses ProductSummaryResource
     * (id, name, slug, image_url, starting_price) - the same shape a listing
     * page shows - rather than inventing a bespoke wishlist payload.
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->whereHas('wishlistedBy', fn ($query) => $query->where('user_id', $request->user()->id))
            ->withStartingPrice()
            ->orderBy('name')
            ->get();

        return ApiResponse::success(ProductSummaryResource::collection($products));
    }

    /**
     * Adds the product to the user's wishlist if it isn't already there,
     * removes it otherwise. There's no ownership concept here beyond "this
     * user's own wishlist row" - any signed-in user may wishlist any product.
     */
    public function toggle(Request $request, Product $product): JsonResponse
    {
        $userId = $request->user()->id;

        $existing = WishlistItem::where('user_id', $userId)
            ->where('product_id', $product->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return ApiResponse::success(['wishlisted' => false]);
        }

        WishlistItem::create([
            'user_id' => $userId,
            'product_id' => $product->id,
        ]);

        return ApiResponse::success(['wishlisted' => true]);
    }
}
