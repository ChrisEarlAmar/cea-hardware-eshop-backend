<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCard extends Model
{
    protected $fillable = [
        'code',
        'initial_balance',
        'remaining_balance',
        'expires_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'expires_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function isUsable(): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return $this->remaining_balance > 0;
    }
}
