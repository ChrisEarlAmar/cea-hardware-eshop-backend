<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $fillable = [
        'state',
        'rate_percent',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:2',
        ];
    }
}
