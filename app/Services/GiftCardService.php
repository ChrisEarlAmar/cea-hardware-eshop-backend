<?php

namespace App\Services;

use App\Models\GiftCard;
use InvalidArgumentException;

class GiftCardService
{
    public function findUsable(string $code): GiftCard
    {
        $giftCard = GiftCard::where('code', $code)->first();

        if ($giftCard === null || ! $giftCard->isUsable()) {
            throw new InvalidArgumentException('This gift card code is not valid.');
        }

        return $giftCard;
    }

    /**
     * Never redeems more than the card actually has, or more than the
     * order still owes - a gift card can cover part of an order and leave
     * the rest for a card, coupon discount, or loyalty points to close.
     */
    public function amountToApply(GiftCard $giftCard, string $remainingOwed): string
    {
        $remainingCents = $this->toCents($remainingOwed);
        $balanceCents = $this->toCents((string) $giftCard->remaining_balance);

        return $this->toDecimalString(min($remainingCents, $balanceCents));
    }

    public function redeem(GiftCard $giftCard, string $amount): void
    {
        $giftCard->decrement('remaining_balance', $amount);
    }

    /**
     * Restores a redeemed amount back to the card's balance - used when an
     * order that redeemed it is later cancelled, mirroring how cancelling
     * an order restores the stock it reserved.
     */
    public function refund(GiftCard $giftCard, string $amount): void
    {
        $giftCard->increment('remaining_balance', $amount);
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
