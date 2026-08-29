<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\PaginatesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductSummaryResource;
use App\Services\SearchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    use PaginatesListings;

    public function __construct(private readonly SearchService $searchService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        $paginator = $this->searchService->search(
            $validated['q'],
            $this->requestedSort($request),
            $this->requestedPerPage($request),
        );

        return ApiResponse::success(
            [
                'query' => $validated['q'],
                'products' => ProductSummaryResource::collection($paginator->items()),
            ],
            meta: $this->paginationMeta($paginator),
        );
    }
}
