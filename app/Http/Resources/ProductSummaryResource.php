<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'image_url' => $this->image_url,
            // Populated via a subselect (see CategoryController/SearchService)
            // over each variant's base (min_quantity = 1) price tier - the
            // lowest price any variant of this product could sell for,
            // shown as "From $X.XX" on listing pages. Null only if a
            // product somehow has no priced variants yet.
            'starting_price' => $this->starting_price !== null
                ? number_format((float) $this->starting_price, 2, '.', '')
                : null,
            'is_featured' => (bool) $this->is_featured,
            // Only surfaced while the sale is actually live - see
            // Product::isOnSale() - so a listing never shows a stale
            // percentage for a sale that already lapsed.
            'sale_discount_percent' => $this->isOnSale()
                ? number_format((float) $this->sale_discount_percent, 2, '.', '')
                : null,
        ];
    }
}
