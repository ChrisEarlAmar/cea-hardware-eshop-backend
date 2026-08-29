<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceTier extends Model
{
    protected $fillable = [
        'product_variant_id',
        'min_quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            // Kept as a string (not float) to avoid floating-point drift;
            // PricingEngine converts to integer cents before doing math.
            'unit_price' => 'decimal:2',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
