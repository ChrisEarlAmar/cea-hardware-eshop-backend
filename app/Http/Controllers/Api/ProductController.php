<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function show(Product $product): JsonResponse
    {
        $product->load([
            'category.categoryAttributes.attribute',
            'variants.attributeValues.attribute',
            'images',
        ]);

        return ApiResponse::success(new ProductResource($product));
    }
}
