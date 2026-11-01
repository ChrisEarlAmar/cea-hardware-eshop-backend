<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountPrice;
use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class PricingEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_tier_applies_when_quantity_is_below_any_higher_tier(): void
    {
        $variant = $this->variantWithTiers([
            [1, '10.00'], [50, '8.00'], [200, '6.00'],
        ]);

        $result = app(PricingEngine::class)->priceFor(null, $variant, 10);

        $this->assertSame('10.00', $result['unit_price']);
        $this->assertSame('100.00', $result['line_total']);
        $this->assertSame('base_price', $result['source']);
    }

    public function test_quantity_tier_applies_cliff_pricing_to_the_entire_quantity(): void
    {
        $variant = $this->variantWithTiers([
            [1, '10.00'], [50, '8.00'], [200, '6.00'],
        ]);

        // Exactly at the threshold: all 50 units at 8.00, not a blend of
        // 9 units at 10.00 and 41 at 8.00 (that would be graduated pricing,
        // which is explicitly not what this engine implements).
        $result = app(PricingEngine::class)->priceFor(null, $variant, 50);

        $this->assertSame('8.00', $result['unit_price']);
        $this->assertSame('400.00', $result['line_total']);
        $this->assertSame('quantity_tier', $result['source']);
    }

    public function test_highest_qualifying_tier_is_selected_above_its_threshold(): void
    {
        $variant = $this->variantWithTiers([
            [1, '10.00'], [50, '8.00'], [200, '6.00'],
        ]);

        $result = app(PricingEngine::class)->priceFor(null, $variant, 250);

        $this->assertSame('6.00', $result['unit_price']);
        $this->assertSame('1500.00', $result['line_total']);
        $this->assertSame('quantity_tier', $result['source']);
    }

    public function test_account_price_overrides_tier_pricing_outright_even_when_a_tier_would_be_cheaper(): void
    {
        $variant = $this->variantWithTiers([
            [1, '10.00'], [50, '8.00'], [200, '6.00'],
        ]);

        $account = Account::create(['name' => 'Acme Co']);

        // Deliberately WORSE than the 200-unit tier (6.00), to prove the
        // precedence rule is "account price wins, full stop" - not "the
        // system picks whichever number is lowest".
        AccountPrice::create([
            'account_id' => $account->id,
            'product_variant_id' => $variant->id,
            'unit_price' => '9.00',
        ]);

        $result = app(PricingEngine::class)->priceFor($account, $variant, 250);

        $this->assertSame('9.00', $result['unit_price']);
        $this->assertSame('2250.00', $result['line_total']);
        $this->assertSame('account_price', $result['source']);
    }

    public function test_an_account_with_no_negotiated_price_still_gets_standard_tier_pricing(): void
    {
        $variant = $this->variantWithTiers([
            [1, '10.00'], [50, '8.00'],
        ]);

        $account = Account::create(['name' => 'Acme Co']);

        $result = app(PricingEngine::class)->priceFor($account, $variant, 50);

        $this->assertSame('8.00', $result['unit_price']);
        $this->assertSame('quantity_tier', $result['source']);
    }

    public function test_an_account_price_for_a_different_variant_does_not_leak_across(): void
    {
        $variantA = $this->variantWithTiers([[1, '10.00']]);
        $variantB = $this->variantWithTiers([[1, '10.00']]);

        $account = Account::create(['name' => 'Acme Co']);
        AccountPrice::create([
            'account_id' => $account->id,
            'product_variant_id' => $variantA->id,
            'unit_price' => '1.00',
        ]);

        $result = app(PricingEngine::class)->priceFor($account, $variantB, 1);

        $this->assertSame('10.00', $result['unit_price']);
        $this->assertSame('base_price', $result['source']);
    }

    public function test_quantity_below_one_is_rejected(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $this->expectException(InvalidArgumentException::class);

        app(PricingEngine::class)->priceFor(null, $variant, 0);
    }

    public function test_a_variant_with_no_price_tiers_throws_rather_than_returning_a_bogus_price(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Untiered Bolt', 'slug' => 'untiered-bolt']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'UNTIERED-1', 'stock' => 10]);

        $this->expectException(RuntimeException::class);

        app(PricingEngine::class)->priceFor(null, $variant, 1);
    }

    public function test_a_sale_discount_is_applied_on_top_of_the_matched_tier(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00'], [50, '8.00']], saleDiscountPercent: '25.00');

        $result = app(PricingEngine::class)->priceFor(null, $variant, 1);

        $this->assertSame('7.50', $result['unit_price']);
        $this->assertSame('sale_price', $result['source']);
    }

    public function test_an_expired_sale_no_longer_discounts_the_price(): void
    {
        $variant = $this->variantWithTiers(
            [[1, '10.00']],
            saleDiscountPercent: '50.00',
            saleEndsAt: now()->subDay(),
        );

        $result = app(PricingEngine::class)->priceFor(null, $variant, 1);

        $this->assertSame('10.00', $result['unit_price']);
        $this->assertSame('base_price', $result['source']);
    }

    public function test_a_sale_never_discounts_an_accounts_own_negotiated_price(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']], saleDiscountPercent: '50.00');
        $account = Account::create(['name' => 'Acme Co']);

        AccountPrice::create([
            'account_id' => $account->id,
            'product_variant_id' => $variant->id,
            'unit_price' => '9.00',
        ]);

        $result = app(PricingEngine::class)->priceFor($account, $variant, 1);

        $this->assertSame('9.00', $result['unit_price']);
        $this->assertSame('account_price', $result['source']);
    }

    /**
     * @param  array<int, array{0: int, 1: string}>  $tiers
     */
    private function variantWithTiers(
        array $tiers,
        ?string $saleDiscountPercent = null,
        ?\Illuminate\Support\Carbon $saleEndsAt = null,
    ): ProductVariant {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Bolt',
            'slug' => 'test-bolt-'.uniqid(),
            'sale_discount_percent' => $saleDiscountPercent,
            'sale_ends_at' => $saleEndsAt,
        ]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => 1000]);

        foreach ($tiers as [$minQuantity, $unitPrice]) {
            PriceTier::create([
                'product_variant_id' => $variant->id,
                'min_quantity' => $minQuantity,
                'unit_price' => $unitPrice,
            ]);
        }

        return $variant;
    }
}
