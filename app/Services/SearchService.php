<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Synonym;
use App\Models\VariantAttributeValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SearchService
{
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
     * either without knowing which happened.
     */
    public function search(string $query, ?string $sort = null, int $perPage = 24): LengthAwarePaginator
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

        return Product::query()
            ->withStartingPrice()
            ->where(function ($q) use ($query, $productIdsFromAttributes) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhereHas('variants', fn ($vq) => $vq->where('sku', 'like', "%{$query}%"));

                if ($productIdsFromAttributes->isNotEmpty()) {
                    $q->orWhereIn('id', $productIdsFromAttributes);
                }
            })
            ->distinct()
            ->sortBy($sort)
            ->paginate($perPage);
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
