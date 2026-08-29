<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\AccountPrice;
use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_get_a_quote_for_a_single_line(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00'], [50, '8.00']]);

        $response = $this->postJson('/api/pricing/quote', [
            'lines' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.lines.0.unit_price', '10.00')
            ->assertJsonPath('data.lines.0.source', 'base_price')
            ->assertJsonPath('data.total', '10.00');
    }

    public function test_a_quote_applies_cliff_pricing_to_the_full_quantity(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00'], [50, '8.00']]);

        $response = $this->postJson('/api/pricing/quote', [
            'lines' => [['variant_id' => $variant->id, 'quantity' => 50]],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.lines.0.unit_price', '8.00')
            ->assertJsonPath('data.lines.0.line_total', '400.00')
            ->assertJsonPath('data.total', '400.00');
    }

    public function test_an_authenticated_users_account_price_overrides_tier_pricing_in_the_quote(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00'], [50, '8.00']]);
        $account = Account::create(['name' => 'Acme Co']);
        $user = User::factory()->create(['account_id' => $account->id, 'role' => 'owner']);

        AccountPrice::create([
            'account_id' => $account->id,
            'product_variant_id' => $variant->id,
            'unit_price' => '5.00',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/pricing/quote', [
            'lines' => [['variant_id' => $variant->id, 'quantity' => 50]],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.lines.0.unit_price', '5.00')
            ->assertJsonPath('data.lines.0.source', 'account_price');
    }

    public function test_a_guest_does_not_receive_another_accounts_negotiated_price(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $account = Account::create(['name' => 'Acme Co']);

        AccountPrice::create([
            'account_id' => $account->id,
            'product_variant_id' => $variant->id,
            'unit_price' => '1.00',
        ]);

        $response = $this->postJson('/api/pricing/quote', [
            'lines' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.lines.0.unit_price', '10.00')
            ->assertJsonPath('data.lines.0.source', 'base_price');
    }

    public function test_a_multi_line_quote_totals_correctly_and_matches_separate_single_line_quotes(): void
    {
        $variantA = $this->variantWithTiers([[1, '10.00'], [50, '8.00']]);
        $variantB = $this->variantWithTiers([[1, '3.40'], [50, '2.90']]);

        $multiLine = $this->postJson('/api/pricing/quote', [
            'lines' => [
                ['variant_id' => $variantA->id, 'quantity' => 2],
                ['variant_id' => $variantB->id, 'quantity' => 50],
            ],
        ]);

        $singleA = $this->postJson('/api/pricing/quote', [
            'lines' => [['variant_id' => $variantA->id, 'quantity' => 2]],
        ]);

        $singleB = $this->postJson('/api/pricing/quote', [
            'lines' => [['variant_id' => $variantB->id, 'quantity' => 50]],
        ]);

        $multiLine->assertStatus(200);

        // The same engine call, made through two different entry points,
        // must produce identical numbers - that's the whole point of
        // centralizing pricing in one service.
        $this->assertSame(
            $singleA->json('data.lines.0'),
            $multiLine->json('data.lines.0')
        );
        $this->assertSame(
            $singleB->json('data.lines.0'),
            $multiLine->json('data.lines.1')
        );

        $expectedTotal = number_format(
            ((float) $singleA->json('data.lines.0.line_total'))
                + ((float) $singleB->json('data.lines.0.line_total')),
            2,
            '.',
            ''
        );

        $this->assertSame($expectedTotal, $multiLine->json('data.total'));
        $this->assertSame('20.00', $multiLine->json('data.lines.0.line_total'));
        $this->assertSame('145.00', $multiLine->json('data.lines.1.line_total'));
        $this->assertSame('165.00', $multiLine->json('data.total'));
    }

    public function test_quote_validation_rejects_missing_lines(): void
    {
        $this->postJson('/api/pricing/quote', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_quote_validation_rejects_an_unknown_variant_id(): void
    {
        $this->postJson('/api/pricing/quote', [
            'lines' => [['variant_id' => 999999, 'quantity' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_quote_validation_rejects_a_zero_quantity(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $this->postJson('/api/pricing/quote', [
            'lines' => [['variant_id' => $variant->id, 'quantity' => 0]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    /**
     * @param  array<int, array{0: int, 1: string}>  $tiers
     */
    private function variantWithTiers(array $tiers): ProductVariant
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Bolt', 'slug' => 'test-bolt-'.uniqid()]);
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
