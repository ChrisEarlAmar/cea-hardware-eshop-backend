<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\PaginatesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductSummaryResource;
use App\Models\Category;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use PaginatesListings;

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            CategoryResource::collection(Category::orderBy('name')->get())
        );
    }

    public function products(Request $request, Category $category): JsonResponse
    {
        $paginator = $category->products()
            ->withStartingPrice()
            ->sortBy($this->requestedSort($request))
            ->paginate($this->requestedPerPage($request));

        return ApiResponse::success(
            [
                'category' => new CategoryResource($category),
                'products' => ProductSummaryResource::collection($paginator->items()),
            ],
            meta: $this->paginationMeta($paginator),
        );
    }
}
