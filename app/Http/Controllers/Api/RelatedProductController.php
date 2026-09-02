<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RelatedProductController extends Controller
{
    /**
     * Up to 4 other products in the same category as $product, for a "You
     * may also like" style rail. Deliberately a separate, lightweight
     * endpoint rather than folding this into ProductController::show - it
     * has no need for the full detail payload (attributes, variants,
     * images) that route builds.
     */
    public function index(Product $product): JsonResponse
    {
        $related = Product::query()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->orderBy('name')
            ->limit(4)
            ->get(['id', 'name', 'slug', 'image_url']);

        return ApiResponse::success(
            $related->map(fn (Product $related) => [
                'id' => $related->id,
                'name' => $related->name,
                'slug' => $related->slug,
                'image_url' => $related->image_url,
            ])->values()
        );
    }
}
