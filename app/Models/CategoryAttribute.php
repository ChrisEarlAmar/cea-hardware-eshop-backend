<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryAttribute extends Model
{
    protected $fillable = [
        'category_id',
        'attribute_id',
        'is_variant_defining',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'is_variant_defining' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
