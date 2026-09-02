<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'image_url',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('status', Review::STATUS_APPROVED);
    }

    public function wishlistedBy(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    /**
     * Adds a `starting_price` column: the lowest base-tier (min_quantity=1)
     * unit price across this product's variants, via a correlated
     * subselect rather than an N+1 lookup per product. This is what a
     * listing page's "starting_price" and price sort are computed from -
     * deliberately account/quantity-independent, so it never bypasses
     * PricingEngine's own account-override/tier precedence for an actual
     * quote, it just gives listings something sensible to show and sort by.
     */
    public function scopeWithStartingPrice(Builder $query): Builder
    {
        $subquery = PriceTier::query()
            ->selectRaw('MIN(price_tiers.unit_price)')
            ->join('product_variants', 'product_variants.id', '=', 'price_tiers.product_variant_id')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('price_tiers.min_quantity', 1);

        return $query->addSelect(['starting_price' => $subquery]);
    }

    /**
     * @param  'price_asc'|'price_desc'|'name'|'newest'|null  $sort
     */
    public function scopeSortBy(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $query->orderByRaw('starting_price IS NULL, starting_price ASC'),
            'price_desc' => $query->orderByRaw('starting_price IS NULL, starting_price DESC'),
            'newest' => $query->orderByDesc('created_at'),
            default => $query->orderBy('name'),
        };
    }
}
