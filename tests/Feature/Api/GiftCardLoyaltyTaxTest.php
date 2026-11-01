<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\Category;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GiftCardLoyaltyTaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_gift_card_reduces_the_order_total_and_its_balance(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '20.00']]);
        $giftCard = GiftCard::create(['code' => 'GC-20', 'initial_balance' => '5.00', 'remaining_balance' => '5.00', 'active' => true]);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);

        $response = $this->checkout($user, ['gift_card_code' => 'GC-20']);

        $response->assertStatus(201)
            ->assertJsonPath('data.total', '15.00')
            ->assertJsonPath('data.gift_card_amount', '5.00');

        $this->assertSame('0.00', $giftCard->fresh()->remaining_balance);
    }

    public function test_a_gift_card_never_pushes_the_total_below_zero(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '5.00']]);
        $giftCard = GiftCard::create(['code' => 'GC-BIG', 'initial_balance' => '50.00', 'remaining_balance' => '50.00', 'active' => true]);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);

        $this->checkout($user, ['gift_card_code' => 'GC-BIG'])
            ->assertStatus(201)
            ->assertJsonPath('data.total', '0.00')
            ->assertJsonPath('data.gift_card_amount', '5.00');

        $this->assertSame('45.00', $giftCard->fresh()->remaining_balance);
    }

    public function test_an_invalid_gift_card_code_is_rejected(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);

        $this->checkout($user, ['gift_card_code' => 'NOPE'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_checkout');

        $this->assertSame(0, Order::count());
    }

    public function test_an_expired_gift_card_is_rejected(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);
        GiftCard::create([
            'code' => 'GC-EXPIRED', 'initial_balance' => '10.00', 'remaining_balance' => '10.00',
            'active' => true, 'expires_at' => now()->subDay(),
        ]);
        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);

        $this->checkout($user, ['gift_card_code' => 'GC-EXPIRED'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_checkout');
    }

    public function test_cancelling_an_order_refunds_the_gift_card_balance(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '20.00']], stock: 10);
        $giftCard = GiftCard::create(['code' => 'GC-REFUND', 'initial_balance' => '5.00', 'remaining_balance' => '5.00', 'active' => true]);
        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);
        $orderId = $this->checkout($user, ['gift_card_code' => 'GC-REFUND'])->json('data.id');

        $this->assertSame('0.00', $giftCard->fresh()->remaining_balance);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/orders/{$orderId}/status", ['status' => 'cancelled'])
            ->assertStatus(200);

        $this->assertSame('5.00', $giftCard->fresh()->remaining_balance);
    }

    public function test_loyalty_points_redemption_is_capped_at_the_users_balance(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create(['loyalty_points_balance' => 100]);
        $variant = $this->variantWithTiers([[1, '50.00']]);
        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);

        // Requests far more than the 100-point balance - redemption should
        // cap at 100 points (a $1.00 discount at 100 points per dollar).
        $response = $this->checkout($user, ['redeem_loyalty_points' => 10000]);

        $response->assertStatus(201)->assertJsonPath('data.total', '49.00');
        $this->assertSame(0, $user->fresh()->loyalty_points_balance);
    }

    public function test_loyalty_points_redemption_is_capped_at_what_the_order_still_owes(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create(['loyalty_points_balance' => 10000]);
        $variant = $this->variantWithTiers([[1, '5.00']]);
        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);

        // 10,000 points would be worth $100, far more than the $5.00 order -
        // only enough points to zero it out should be spent.
        $this->checkout($user, ['redeem_loyalty_points' => 10000])
            ->assertStatus(201)
            ->assertJsonPath('data.total', '0.00');

        $this->assertSame(10000 - 500, $user->fresh()->loyalty_points_balance);
    }

    public function test_cancelling_an_order_refunds_redeemed_loyalty_points(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create(['loyalty_points_balance' => 500]);
        $variant = $this->variantWithTiers([[1, '10.00']], stock: 10);
        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);
        $orderId = $this->checkout($user, ['redeem_loyalty_points' => 500])->json('data.id');

        $this->assertSame(0, $user->fresh()->loyalty_points_balance);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/orders/{$orderId}/status", ['status' => 'cancelled'])
            ->assertStatus(200);

        $this->assertSame(500, $user->fresh()->loyalty_points_balance);
    }

    public function test_loyalty_points_are_earned_only_once_payment_succeeds(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '42.00']]);
        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);
        $orderId = $this->checkout($user)->json('data.id');

        // Not credited yet - the order is only awaiting_payment.
        $this->assertSame(0, $user->fresh()->loyalty_points_balance);

        app(OrderService::class)->transitionTo(Order::find($orderId), Order::STATUS_PENDING);

        $this->assertSame(42, $user->fresh()->loyalty_points_balance);
    }

    public function test_tax_is_charged_based_on_the_shipping_addresss_state(): void
    {
        $this->fakeSuccessfulPayments();

        TaxRate::create(['state' => 'CA', 'rate_percent' => '10.00']);

        $user = User::factory()->create();
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Home', 'recipient_name' => 'Test User',
            'line1' => '1 Main St', 'city' => 'LA', 'state' => 'CA', 'postal_code' => '90001', 'country' => 'US',
        ]);
        $variant = $this->variantWithTiers([[1, '100.00']]);
        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);

        $this->checkout($user, ['shipping_address_id' => $address->id])
            ->assertStatus(201)
            ->assertJsonPath('data.tax_total', '10.00')
            ->assertJsonPath('data.total', '110.00');
    }

    public function test_no_shipping_address_means_no_tax(): void
    {
        $this->fakeSuccessfulPayments();

        TaxRate::create(['state' => 'CA', 'rate_percent' => '10.00']);

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '100.00']]);
        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);

        $this->checkout($user)
            ->assertStatus(201)
            ->assertJsonPath('data.tax_total', '0.00')
            ->assertJsonPath('data.total', '100.00');
    }

    public function test_a_state_with_no_configured_rate_charges_no_tax(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Home', 'recipient_name' => 'Test User',
            'line1' => '1 Main St', 'city' => 'Portland', 'state' => 'OR', 'postal_code' => '97201', 'country' => 'US',
        ]);
        $variant = $this->variantWithTiers([[1, '100.00']]);
        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);

        $this->checkout($user, ['shipping_address_id' => $address->id])
            ->assertStatus(201)
            ->assertJsonPath('data.tax_total', '0.00');
    }

    /**
     * @param  array<int, array{0: int, 1: string}>  $tiers
     */
    private function variantWithTiers(array $tiers, int $stock = 1000): ProductVariant
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Bolt', 'slug' => 'test-bolt-'.uniqid()]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => $stock]);

        foreach ($tiers as [$minQuantity, $unitPrice]) {
            PriceTier::create([
                'product_variant_id' => $variant->id,
                'min_quantity' => $minQuantity,
                'unit_price' => $unitPrice,
            ]);
        }

        return $variant;
    }

    private function fakeSuccessfulPayments(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('createPaymentIntentFor')->andReturn([
                'id' => 'pi_test_123',
                'client_secret' => 'pi_test_secret_abc',
            ]);
            $mock->shouldReceive('retrieveClientSecret')->andReturn('pi_test_secret_abc');
        });
    }

    private function checkout(User $user, array $overrides = [])
    {
        return $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/orders', $overrides);
    }
}
