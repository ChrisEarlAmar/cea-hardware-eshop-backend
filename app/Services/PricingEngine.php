<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ProductVariant;
use InvalidArgumentException;
use RuntimeException;

class PricingEngine
{
    /**
     * The single source of truth for computing a line price. Every call
     * site - the pricing quote endpoint today, cart and order totals later
     * - must go through this method rather than recomputing prices itself,
     * so a given (account, variant, quantity) always produces the same
     * answer everywhere.
     *
     * Precedence (highest wins outright - never stacked with anything else):
     *   1. An account-specific price for this variant, if one exists - a
     *      negotiated rate is never further discounted by a public sale.
     *   2. Otherwise, the highest-quantity price tier whose min_quantity is
     *      less than or equal to the requested quantity ("cliff" pricing:
     *      the matched tier's price applies to every unit in the line, not
     *      just the units above its threshold) - reduced by the product's
     *      sale_discount_percent if it's currently on sale.
     *
     * @return array{unit_price: string, quantity: int, line_total: string, source: string}
     */
    public function priceFor(?Account $account, ProductVariant $variant, int $quantity): array
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        if ($account !== null) {
            $accountPrice = $variant->accountPrices()
                ->where('account_id', $account->id)
                ->first();

            if ($accountPrice !== null) {
                return $this->buildResult($accountPrice->unit_price, $quantity, 'account_price');
            }
        }

        $tier = $variant->priceTiers()
            ->where('min_quantity', '<=', $quantity)
            ->orderByDesc('min_quantity')
            ->first();

        if ($tier === null) {
            throw new RuntimeException("No price tier configured for variant [{$variant->id}].");
        }

        $product = $variant->relationLoaded('product') ? $variant->product : $variant->product()->first();

        if ($product !== null && $product->isOnSale()) {
            $saleUnitPrice = $this->applyDiscount($tier->unit_price, (string) $product->sale_discount_percent);

            return $this->buildResult($saleUnitPrice, $quantity, 'sale_price');
        }

        $source = $tier->min_quantity === 1 ? 'base_price' : 'quantity_tier';

        return $this->buildResult($tier->unit_price, $quantity, $source);
    }

    private function applyDiscount(string $unitPrice, string $discountPercent): string
    {
        $unitPriceCents = $this->toCents($unitPrice);
        $discountedCents = (int) round($unitPriceCents * (1 - ((float) $discountPercent / 100)));

        return $this->toDecimalString(max(0, $discountedCents));
    }

    private function buildResult(string $unitPrice, int $quantity, string $source): array
    {
        // Convert to integer cents before multiplying so the line total
        // can't drift from PHP float rounding - only convert back to a
        // decimal string for the final result.
        $unitPriceCents = $this->toCents($unitPrice);
        $lineTotalCents = $unitPriceCents * $quantity;

        return [
            'unit_price' => $this->toDecimalString($unitPriceCents),
            'quantity' => $quantity,
            'line_total' => $this->toDecimalString($lineTotalCents),
            'source' => $source,
        ];
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
