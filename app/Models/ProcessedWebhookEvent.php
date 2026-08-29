<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedWebhookEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
