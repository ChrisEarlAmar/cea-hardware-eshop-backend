<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Shared pagination/sort request handling for catalog-style listing
 * endpoints (category products, search results), so both bound page size
 * and the allow-listed sort values the same way rather than drifting apart.
 */
trait PaginatesListings
{
    private const MAX_PER_PAGE = 60;

    private const DEFAULT_PER_PAGE = 24;

    private const ALLOWED_SORTS = ['name', 'price_asc', 'price_desc', 'newest'];

    private function requestedPerPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return max(1, min(self::MAX_PER_PAGE, $perPage ?: self::DEFAULT_PER_PAGE));
    }

    private function requestedSort(Request $request): ?string
    {
        $sort = $request->string('sort')->toString();

        return in_array($sort, self::ALLOWED_SORTS, true) ? $sort : null;
    }

    /**
     * @return array{page: int, per_page: int, total: int, last_page: int}
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
