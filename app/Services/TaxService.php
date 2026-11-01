<?php

namespace App\Services;

use App\Models\TaxRate;

/**
 * Deliberately not a real tax-jurisdiction engine (no external API, no
 * address-rules database) - just a flat admin-configurable rate per US
 * state, applied to the order's pre-tax subtotal-after-discounts. A state
 * with no configured rate charges 0%, which is also the answer when there's
 * no shipping address at all to read a state from.
 */
class TaxService
{
    public function rateFor(?string $state): string
    {
        if ($state === null) {
            return '0.00';
        }

        $taxRate = TaxRate::where('state', strtoupper($state))->first();

        return $taxRate?->rate_percent ?? '0.00';
    }

    public function taxFor(?string $state, string $taxableAmount): string
    {
        $rate = (float) $this->rateFor($state);

        if ($rate <= 0) {
            return '0.00';
        }

        $taxableCents = $this->toCents($taxableAmount);
        $taxCents = (int) round($taxableCents * ($rate / 100));

        return $this->toDecimalString($taxCents);
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
