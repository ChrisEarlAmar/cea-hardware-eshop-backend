<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    protected $fillable = [
        'name',
        'key',
        'data_type',
        'unit',
    ];

    public function categoryAttributes(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class);
    }

    public function variantAttributeValues(): HasMany
    {
        return $this->hasMany(VariantAttributeValue::class);
    }
}
