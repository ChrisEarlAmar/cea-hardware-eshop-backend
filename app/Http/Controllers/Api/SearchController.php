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
            $this->requestedFacets($request),
        );

        return ApiResponse::success(
            [
                'query' => $validated['q'],
                'products' => ProductSummaryResource::collection($paginator->items()),
            ],
            meta: $this->paginationMeta($paginator),
        );
    }

    /**
     * Reports which attribute keys/values are still available to filter by
     * (and how many current results carry each) for the same query + any
     * facets already selected - what the frontend renders its facet
     * checkboxes from and keeps their counts in sync with.
     */
    public function facets(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        $facets = $this->searchService->facetsFor($validated['q'], $this->requestedFacets($request));

        return ApiResponse::success(['facets' => $facets]);
    }

    /**
     * Lightweight as-you-type suggestions. Unlike index()/facets(), a short
     * or missing query isn't a validation error - it's just too little to
     * suggest anything from yet, so the caller gets an empty list back
     * rather than a 422 while the user is still typing their first letter.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        return ApiResponse::success([
            'suggestions' => $this->searchService->autocomplete($validated['q'] ?? ''),
        ]);
    }

    /**
     * Any query param besides the pagination/sort ones PaginatesListings
     * already handles is treated as a facet filter: attribute key => the
     * one value to require (e.g. ?material=Stainless+Steel&thread_size=M8).
     *
     * @return array<string, string>
     */
    private function requestedFacets(Request $request): array
    {
        return collect($request->query())
            ->except(['q', 'sort', 'per_page', 'page'])
            ->map(fn ($value) => is_array($value) ? null : trim((string) $value))
            ->filter(fn (?string $value) => $value !== null && $value !== '')
            ->all();
    }
}
