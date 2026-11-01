<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    // Only reachable via the Stripe webhook, never via the customer-facing
    // status-update endpoint - that endpoint's validation whitelist doesn't
    // include this value, so a customer request can never fake "payment
    // succeeded" for themselves.
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    // A B2B order that exceeded the buyer's spending limit. Payment
    // collection is deliberately not started yet - no PaymentIntent exists
    // until the account owner approves and the order moves on to
    // STATUS_AWAITING_PAYMENT.
    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_REQUIRES_PAYMENT = 'requires_payment';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_REFUNDED = 'refunded';

    /**
     * The full state machine. An order can only move to one of the states
     * listed for its current status - anything else is rejected, whether
     * the request came from the frontend or a direct API call.
     */
    private const TRANSITIONS = [
        self::STATUS_PENDING_APPROVAL => [self::STATUS_AWAITING_PAYMENT, self::STATUS_CANCELLED],
        self::STATUS_AWAITING_PAYMENT => [self::STATUS_PENDING, self::STATUS_CANCELLED],
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_DELIVERED],
        self::STATUS_DELIVERED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'user_id',
        'status',
        'total',
        'payment_intent_id',
        'payment_status',
        'guest_email',
        'guest_name',
        'shipping_address_id',
        'billing_address_id',
        'tracking_carrier',
        'tracking_number',
        'customer_note',
        'po_reference',
        'coupon_id',
        'discount_total',
        'tax_total',
        'gift_card_id',
        'gift_card_amount',
        'loyalty_points_redeemed',
        'loyalty_points_earned',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'gift_card_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->oldest('id');
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'billing_address_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function approval(): HasOne
    {
        return $this->hasOne(OrderApproval::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
