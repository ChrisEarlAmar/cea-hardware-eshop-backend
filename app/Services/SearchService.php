<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Synonym;
use App\Models\VariantAttributeValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SearchService
{
    private const AUTOCOMPLETE_MIN_LENGTH = 2;

    private const AUTOCOMPLETE_LIMIT = 8;

    /**
     * Turns free text into a product list via two complementary paths:
     *
     *   1. Structured attribute matching - normalize the query (and each of
     *      its words) and resolve it against the hand-written synonym table
     *      ("ss" / "20mm") as well as directly against real attribute
     *      values ("Stainless Steel" / "20"), so an alias and its canonical
     *      term land on exactly the same set of variants.
     *   2. A plain substring fallback against product name/description/SKU,
     *      for anything not captured as a known attribute value.
     *
     * Both paths feed into one product list - a query can be resolved by
     * either without knowing which happened. $facets (attribute key =>
     * selected value) then narrows that list further - see
     * productIdsMatchingFacets() for why that's an AND across facets.
     *
     * @param  array<string, string>  $facets
     */
    public function search(string $query, ?string $sort = null, int $perPage = 24, array $facets = []): LengthAwarePaginator
    {
        $productIds = $this->matchingProductIds($query, $facets);

        return Product::query()
            ->withStartingPrice()
            ->whereIn('id', $productIds)
            ->sortBy($sort)
            ->paginate($perPage);
    }

    /**
     * Given the same query text and already-selected facets a caller would
     * pass to search(), reports which attribute keys/values still occur
     * among the current result set and how many of those products carry
     * each one - e.g. "Material: Stainless Steel (12), Zinc-Plated (8)".
     * Counts are scoped to the current (already-filtered) result set, so a
     * facet a caller has already selected narrows the counts shown for
     * every other facet too.
     *
     * @param  array<string, string>  $selectedFacets
     * @return Collection<int, array{name: string, values: Collection<int, array{key: string, value: string, count: int}>}>
     */
    public function facetsFor(string $query, array $selectedFacets = []): Collection
    {
        $productIds = $this->matchingProductIds($query, $selectedFacets);

        if ($productIds->isEmpty()) {
            return collect();
        }

        $rows = VariantAttributeValue::query()
            ->join('product_variants', 'product_variants.id', '=', 'variant_attribute_values.product_variant_id')
            ->join('attributes', 'attributes.id', '=', 'variant_attribute_values.attribute_id')
            ->whereIn('product_variants.product_id', $productIds)
            ->groupBy('attributes.key', 'attributes.name', 'variant_attribute_values.value')
            ->orderBy('attributes.name')
            ->orderBy('variant_attribute_values.value')
            ->get([
                'attributes.key as attribute_key',
                'attributes.name as attribute_name',
                'variant_attribute_values.value',
                DB::raw('COUNT(DISTINCT product_variants.product_id) as product_count'),
            ]);

        // Grouped by display name, not the underlying attribute key - several
        // categories define their own key for what's conceptually the same
        // customer-facing filter (e.g. "wrench_material" and "bit_material"
        // both display as "Material"), and showing those as separate sidebar
        // sections would look like duplicated/broken facets. Each value keeps
        // its own originating key, since that's still what selecting it must
        // filter by (see productIdsMatchingFacets()).
        return $rows->groupBy('attribute_name')->map(fn (Collection $group) => [
            'name' => $group->first()->attribute_name,
            'values' => $group->map(fn ($row) => [
                'key' => $row->attribute_key,
                'value' => $row->value,
                'count' => (int) $row->product_count,
            ])->values(),
        ])->values();
    }

    /**
     * Up to 8 lightweight suggestions - matching product names first, then
     * matching SKUs filling any remaining slots - for an as-you-type
     * dropdown. Deliberately just a couple of bounded LIKE queries rather
     * than a new search index: fast enough at this catalog's scale, and
     * consistent with how search() itself matches text.
     *
     * @return Collection<int, array{type: string, label: string, product_slug: string}>
     */
    public function autocomplete(string $query): Collection
    {
        $normalized = trim($query);

        if (mb_strlen($normalized) < self::AUTOCOMPLETE_MIN_LENGTH) {
            return collect();
        }

        $productMatches = Product::query()
            ->where('name', 'like', "%{$normalized}%")
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ["{$normalized}%"])
            ->orderBy('name')
            ->limit(self::AUTOCOMPLETE_LIMIT)
            ->get(['name', 'slug'])
            ->map(fn (Product $product) => [
                'type' => 'product',
                'label' => $product->name,
                'product_slug' => $product->slug,
            ]);

        $remaining = self::AUTOCOMPLETE_LIMIT - $productMatches->count();

        $skuMatches = collect();
        if ($remaining > 0) {
            $skuMatches = ProductVariant::query()
                ->with('product:id,name,slug')
                ->where('sku', 'like', "%{$normalized}%")
                ->orderByRaw('CASE WHEN sku LIKE ? THEN 0 ELSE 1 END', ["{$normalized}%"])
                ->orderBy('sku')
                ->limit($remaining)
                ->get()
                ->filter(fn (ProductVariant $variant) => $variant->product !== null)
                ->map(fn (ProductVariant $variant) => [
                    'type' => 'sku',
                    'label' => $variant->sku,
                    'product_slug' => $variant->product->slug,
                ]);
        }

        return $productMatches->concat($skuMatches)->values();
    }

    /**
     * The full product id set search()/facetsFor() share: text-or-attribute
     * matching against $query, then narrowed by $facets (an AND across
     * every supplied facet - see productIdsMatchingFacets()).
     *
     * @param  array<string, string>  $facets
     * @return Collection<int, int>
     */
    private function matchingProductIds(string $query, array $facets = []): Collection
    {
        $normalized = $this->normalize($query);
        $tokens = array_values(array_filter(explode(' ', $normalized)));
        $candidateTerms = array_values(array_unique(array_merge([$normalized], $tokens)));

        $resolvedValues = $this->resolveAttributeValues($candidateTerms);

        $productIdsFromAttributes = collect();
        if ($resolvedValues->isNotEmpty()) {
            $productIdsFromAttributes = ProductVariant::query()
                ->whereHas('attributeValues', fn ($q) => $q->whereIn('value', $resolvedValues))
                ->pluck('product_id')
                ->unique();
        }

        $productIds = Product::query()
            ->where(function ($q) use ($query, $productIdsFromAttributes) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhereHas('variants', fn ($vq) => $vq->where('sku', 'like', "%{$query}%"));

                if ($productIdsFromAttributes->isNotEmpty()) {
                    $q->orWhereIn('id', $productIdsFromAttributes);
                }
            })
            ->pluck('id');

        $facetProductIds = $this->productIdsMatchingFacets($facets);

        if ($facetProductIds !== null) {
            $productIds = $productIds->intersect($facetProductIds)->values();
        }

        return $productIds;
    }

    /**
     * Every supplied facet must match (an AND, not an OR): a product only
     * qualifies if it has a variant satisfying each key/value pair, so this
     * intersects the per-facet matching sets rather than merging them.
     *
     * @param  array<string, string>  $facets  attribute key => selected value
     * @return Collection<int, int>|null  null when no facets were supplied (nothing to narrow by); otherwise the intersected product id set (possibly empty).
     */
    private function productIdsMatchingFacets(array $facets): ?Collection
    {
        if ($facets === []) {
            return null;
        }

        $matchingSets = [];

        foreach ($facets as $key => $value) {
            $matchingSets[] = ProductVariant::query()
                ->whereHas('attributeValues', function ($q) use ($key, $value) {
                    $q->where('value', $value)
                        ->whereHas('attribute', fn ($aq) => $aq->where('key', $key));
                })
                ->pluck('product_id')
                ->unique()
                ->values();
        }

        return array_reduce(
            $matchingSets,
            fn (?Collection $carry, Collection $set) => $carry === null ? $set : $carry->intersect($set)->values(),
            null,
        );
    }

    /**
     * @param  array<int, string>  $candidateTerms
     * @return Collection<int, string>
     */
    private function resolveAttributeValues(array $candidateTerms): Collection
    {
        $synonymValues = Synonym::query()
            ->whereIn('term', $candidateTerms)
            ->pluck('value');

        // Also match the canonical value itself (e.g. searching "Stainless
        // Steel" directly, not just its "ss" alias) - compared in PHP
        // rather than a SQL LOWER() expression, which is simple and
        // dialect-agnostic at this catalog's scale.
        $directValues = VariantAttributeValue::query()
            ->distinct()
            ->pluck('value')
            ->filter(fn ($value) => in_array(strtolower($value), $candidateTerms, true));

        return $synonymValues->merge($directValues)->unique()->values();
    }

    private function normalize(string $query): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower($query)));
    }
}
