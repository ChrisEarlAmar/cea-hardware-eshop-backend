<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function shippingAddress(): array
    {
        return [
            'recipient_name' => 'Jane Guest',
            'line1' => '123 Test St',
            'city' => 'Testville',
            'state' => 'CA',
            'postal_code' => '90210',
        ];
    }

    public function test_adding_to_a_guest_cart_without_a_token_issues_one(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $response = $this->postJson('/api/guest/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 2,
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.guest_token'));
        $this->assertSame('20.00', $response->json('data.total'));
    }

    public function test_a_returning_guest_token_reuses_the_same_cart(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $first = $this->postJson('/api/guest/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);
        $token = $first->json('data.guest_token');

        $second = $this->withHeader('X-Guest-Token', $token)->postJson('/api/guest/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);

        $this->assertSame($token, $second->json('data.guest_token'));
        $this->assertCount(1, $second->json('data.items'));
        $this->assertSame(2, $second->json('data.items.0.quantity'));
    }

    public function test_a_guest_cannot_touch_another_guests_cart_item(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $owner = $this->postJson('/api/guest/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);
        $itemId = $owner->json('data.items.0.item_id');

        $this->withHeader('X-Guest-Token', (string) Str::uuid())
            ->patchJson("/api/guest/cart/items/{$itemId}", ['quantity' => 5])
            ->assertStatus(404);

        $this->patchJson("/api/guest/cart/items/{$itemId}", ['quantity' => 5])
            ->assertStatus(404);
    }

    public function test_guest_checkout_requires_a_guest_token(): void
    {
        $this->postJson('/api/guest/orders', [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'guest_token_required');
    }

    public function test_guest_checkout_requires_an_idempotency_key(): void
    {
        $this->withHeader('X-Guest-Token', (string) Str::uuid())
            ->postJson('/api/guest/orders', [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'idempotency_key_required');
    }

    public function test_guest_checkout_creates_an_order_and_clears_the_cart(): void
    {
        $this->fakeSuccessfulPayments();

        $variant = $this->variantWithTiers([[1, '10.00']], stock: 10);

        $cart = $this->postJson('/api/guest/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 3,
        ]);
        $token = $cart->json('data.guest_token');

        $response = $this->checkout($token);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.total', '30.00')
            ->assertJsonPath('data.guest_email', 'guest@example.com')
            ->assertJsonPath('data.client_secret', 'pi_test_secret_abc');

        $this->assertSame(7, $variant->fresh()->stock);

        $order = Order::first();
        $this->assertNull($order->user_id);
        $this->assertNotNull($order->shipping_address_id);
        $this->assertSame($order->shipping_address_id, $order->billing_address_id);

        $cartResponse = $this->withHeader('X-Guest-Token', $token)->getJson('/api/guest/cart');
        $this->assertCount(0, $cartResponse->json('data.items'));
    }

    public function test_guest_checkout_with_an_invalid_coupon_is_rejected(): void
    {
        $this->fakeSuccessfulPayments();

        $variant = $this->variantWithTiers([[1, '10.00']], stock: 10);
        $cart = $this->postJson('/api/guest/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);
        $token = $cart->json('data.guest_token');

        $this->checkout($token, ['coupon_code' => 'NOPE'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_checkout');

        $this->assertSame(0, Order::count());
    }

    public function test_guest_checkout_applies_a_valid_percent_coupon(): void
    {
        $this->fakeSuccessfulPayments();

        Coupon::create([
            'code' => 'SAVE10', 'type' => Coupon::TYPE_PERCENT, 'value' => '10.00', 'active' => true,
        ]);

        $variant = $this->variantWithTiers([[1, '20.00']], stock: 10);
        $cart = $this->postJson('/api/guest/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);
        $token = $cart->json('data.guest_token');

        $response = $this->checkout($token, ['coupon_code' => 'SAVE10']);

        $response->assertStatus(201)
            ->assertJsonPath('data.total', '18.00')
            ->assertJsonPath('data.discount_total', '2.00');

        $this->assertSame(1, Coupon::first()->used_count);
    }

    public function test_a_guest_can_view_their_own_order_by_email_but_not_a_wrong_one(): void
    {
        $this->fakeSuccessfulPayments();

        $variant = $this->variantWithTiers([[1, '10.00']], stock: 10);
        $cart = $this->postJson('/api/guest/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);
        $token = $cart->json('data.guest_token');
        $orderId = $this->checkout($token)->json('data.id');

        $this->getJson("/api/guest/orders/{$orderId}?email=guest@example.com")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $orderId);

        $this->getJson("/api/guest/orders/{$orderId}?email=someone-else@example.com")
            ->assertStatus(404);

        $this->getJson("/api/guest/orders/{$orderId}")
            ->assertStatus(404);
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

    private function checkout(string $guestToken, array $overrides = [])
    {
        return $this->withHeader('X-Guest-Token', $guestToken)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/guest/orders', array_merge([
                'guest_email' => 'guest@example.com',
                'guest_name' => 'Jane Guest',
                'shipping_address' => $this->shippingAddress(),
            ], $overrides));
    }
}
