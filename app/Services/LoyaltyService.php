<?php

namespace App\Services;

/**
 * A flat, easy-to-explain scheme: 1 point earned per whole dollar spent
 * (merchandise total, after discounts, before tax - tax isn't "spend" in
 * the reward sense), 100 points redeemable for $1.00 off a later order.
 * Both rates live only here, so changing the program later means changing
 * one class, not hunting through OrderService.
 */
class LoyaltyService
{
    private const POINTS_PER_DOLLAR_EARNED = 1;

    private const POINTS_PER_DOLLAR_REDEEMED = 100;

    public function pointsEarnedFor(string $merchandiseTotal): int
    {
        return (int) floor((float) $merchandiseTotal) * self::POINTS_PER_DOLLAR_EARNED;
    }

    public function discountForPoints(int $points): string
    {
        return number_format($points / self::POINTS_PER_DOLLAR_REDEEMED, 2, '.', '');
    }

    /**
     * Never redeems more points than the customer has, or more than would
     * be needed to cover what the order still owes - redeeming past a zero
     * balance would just be points burned for no discount.
     */
    public function pointsToRedeem(int $requestedPoints, int $availableBalance, string $remainingOwed): int
    {
        $requested = max(0, min($requestedPoints, $availableBalance));
        $owedCents = (int) round(((float) $remainingOwed) * 100);
        $maxUsefulPoints = (int) ceil($owedCents / 100 * self::POINTS_PER_DOLLAR_REDEEMED);

        return min($requested, $maxUsefulPoints);
    }
}
