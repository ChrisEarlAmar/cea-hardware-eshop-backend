<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A B2B buyer's request for a custom quote on a product at a given
 * quantity. Deliberately simple for this learning project: there is no
 * threaded reply or quoted price stored here, just a status flag staff can
 * set once they've followed up with the customer out-of-band (email,
 * phone, etc). See App\Http\Controllers\Api\RfqController (customer side)
 * and App\Http\Controllers\Api\Admin\RfqController (staff side).
 */
class RfqRequest extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESPONDED = 'responded';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'message',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
