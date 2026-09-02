<?php

namespace App\Services;

use App\Models\Coupon;
use InvalidArgumentException;

class CouponService
{
    /**
     * Resolves a code to a coupon that is currently redeemable for the
     * given subtotal, or throws with a message safe to show the customer
     * directly. A missing/inactive/expired/exhausted code and a code that
     * exists but doesn't meet its minimum order total are deliberately
     * indistinguishable by design where that matters (isUsable), except the
     * minimum-order case, which gets its own message since the customer can
     * actually act on it (add more to the cart).
     */
    public function findUsable(string $code, string $subtotal): Coupon
    {
        $coupon = Coupon::where('code', $code)->first();

        if ($coupon === null || ! $coupon->isUsable()) {
            throw new InvalidArgumentException('This coupon code is not valid.');
        }

        if ($coupon->min_order_total !== null && $this->toCents($subtotal) < $this->toCents((string) $coupon->min_order_total)) {
            throw new InvalidArgumentException("This coupon requires a minimum order of {$coupon->min_order_total}.");
        }

        return $coupon;
    }

    /**
     * The discount never exceeds the subtotal itself - a fixed-amount
     * coupon applied to a smaller order caps out at "this order is free,"
     * never a negative total.
     */
    public function discountFor(Coupon $coupon, string $subtotal): string
    {
        $subtotalCents = $this->toCents($subtotal);

        $discountCents = match ($coupon->type) {
            Coupon::TYPE_PERCENT => (int) round($subtotalCents * ((float) $coupon->value / 100)),
            Coupon::TYPE_FIXED => $this->toCents((string) $coupon->value),
        };

        return $this->toDecimalString(min($discountCents, $subtotalCents));
    }

    public function redeem(Coupon $coupon): void
    {
        $coupon->increment('used_count');
    }

    private function toCents(string $decimal): int
    {
        return (int) round(((float) $decimal) * 100);
    }

    private function toDecimalString(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
