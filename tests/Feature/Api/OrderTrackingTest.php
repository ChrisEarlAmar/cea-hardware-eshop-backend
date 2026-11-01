<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_track_their_order_by_id_and_email(): void
    {
        $this->fakeSuccessfulPayments();

        $variant = $this->variantWithTiers([[1, '10.00']], stock: 10);
        $cart = $this->postJson('/api/guest/cart/items', ['variant_id' => $variant->id, 'quantity' => 2]);
        $token = $cart->json('data.guest_token');

        $orderId = $this->withHeader('X-Guest-Token', $token)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/guest/orders', [
                'guest_email' => 'guest@example.com',
                'guest_name' => 'Jane Guest',
                'shipping_address' => [
                    'recipient_name' => 'Jane Guest',
                    'line1' => '123 Test St',
                    'city' => 'Testville',
                    'state' => 'CA',
                    'postal_code' => '90210',
                ],
            ])->json('data.id');

        $this->getJson("/api/track-order/{$orderId}?email=guest@example.com")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.total', '20.00');

        $this->getJson("/api/track-order/{$orderId}?email=someone-else@example.com")
            ->assertStatus(404);
    }

    /**
     * The gap this endpoint exists to close: an authenticated checkout
     * never sets guest_email, so GuestOrderController::show (which requires
     * user_id to be null) can never find this order - but a signed-in
     * customer should still be able to track it from a public page using
     * their own account email.
     */
    public function test_a_signed_in_customers_order_can_be_tracked_by_their_account_email(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create(['email' => 'shopper@example.com']);
        $variant = $this->variantWithTiers([[1, '10.00']], stock: 10);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);

        $orderId = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/orders')
            ->json('data.id');

        $this->getJson("/api/track-order/{$orderId}?email=shopper@example.com")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.status', 'awaiting_payment');

        $this->getJson("/api/track-order/{$orderId}?email=wrong@example.com")
            ->assertStatus(404);
    }

    public function test_tracking_without_an_email_is_rejected(): void
    {
        $this->fakeSuccessfulPayments();

        $variant = $this->variantWithTiers([[1, '10.00']], stock: 10);
        $cart = $this->postJson('/api/guest/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);
        $token = $cart->json('data.guest_token');

        $orderId = $this->withHeader('X-Guest-Token', $token)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/guest/orders', [
                'guest_email' => 'guest@example.com',
                'guest_name' => 'Jane Guest',
                'shipping_address' => [
                    'recipient_name' => 'Jane Guest',
                    'line1' => '123 Test St',
                    'city' => 'Testville',
                    'state' => 'CA',
                    'postal_code' => '90210',
                ],
            ])->json('data.id');

        $this->getJson("/api/track-order/{$orderId}")
            ->assertStatus(404);
    }

    public function test_tracking_an_unknown_order_id_is_a_404(): void
    {
        $this->getJson('/api/track-order/999999?email=nobody@example.com')
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
}
