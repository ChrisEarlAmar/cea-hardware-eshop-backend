<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_require_authentication(): void
    {
        $this->getJson('/api/orders')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_checkout_requires_an_idempotency_key(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']], stock: 10);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 3,
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/orders')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'idempotency_key_required');
    }

    public function test_checkout_creates_an_order_awaiting_payment_and_clears_the_cart(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']], stock: 10);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 3,
        ]);

        $response = $this->checkout($user);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.payment_status', 'requires_payment')
            ->assertJsonPath('data.total', '30.00')
            ->assertJsonPath('data.items.0.sku', $variant->sku)
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.client_secret', 'pi_test_secret_abc');

        $this->assertSame(7, $variant->fresh()->stock, 'Stock is reserved as soon as the order is created.');

        $cartResponse = $this->actingAs($user, 'sanctum')->getJson('/api/cart');
        $this->assertCount(0, $cartResponse->json('data.items'));
    }

    public function test_checkout_is_rejected_when_payment_is_not_configured(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']], stock: 10);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);

        $this->checkout($user)
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'payment_not_configured');

        $this->assertSame(10, $variant->fresh()->stock, 'Nothing should be reserved for an order that can never be paid.');
    }

    public function test_repeating_the_same_idempotency_key_replays_the_original_response_without_creating_a_second_order(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']], stock: 10);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 2,
        ]);

        $key = (string) Str::uuid();

        $first = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/orders');

        $second = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/orders');

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Order::count());
        $this->assertSame(8, $variant->fresh()->stock, 'The retried request must not decrement stock twice.');
    }

    public function test_reusing_an_idempotency_key_with_a_different_cart_is_rejected(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $variantA = $this->variantWithTiers([[1, '10.00']], stock: 10);
        $variantB = $this->variantWithTiers([[1, '5.00']], stock: 10);
        $key = (string) Str::uuid();

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variantA->id, 'quantity' => 1,
        ]);
        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/orders')
            ->assertStatus(201);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variantB->id, 'quantity' => 1,
        ]);
        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/orders')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'idempotency_key_reused');
    }

    public function test_a_key_still_being_processed_is_rejected_rather_than_raced(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']], stock: 10);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);

        $fingerprint = hash('sha256', "{$variant->id}:1");

        IdempotencyKey::create([
            'key' => 'in-flight-key',
            'user_id' => $user->id,
            'request_fingerprint' => $fingerprint,
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'in-flight-key')
            ->postJson('/api/orders')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'request_in_progress');
    }

    public function test_checkout_with_an_empty_cart_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->checkout($user)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'empty_cart');
    }

    public function test_checkout_fails_with_insufficient_stock_and_creates_no_order(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']], stock: 2);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 5,
        ]);

        $response = $this->checkout($user);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'insufficient_stock');

        $this->assertSame(2, $variant->fresh()->stock);
        $this->assertSame(0, Order::count());

        $cartResponse = $this->actingAs($user, 'sanctum')->getJson('/api/cart');
        $this->assertCount(1, $cartResponse->json('data.items'));
    }

    public function test_a_user_can_view_their_order_history(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);
        $this->checkout($user);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/orders');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_a_user_cannot_view_another_users_order(): void
    {
        $this->fakeSuccessfulPayments();

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $this->actingAs($owner, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);
        $order = $this->checkout($owner);
        $orderId = $order->json('data.id');

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/orders/{$orderId}")
            ->assertStatus(404);
    }

    public function test_a_user_can_transition_their_order_through_valid_states(): void
    {
        [$user, $orderId] = $this->paidOrder();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/orders/{$orderId}/status", ['status' => 'confirmed'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');
    }

    /**
     * The core proof this stage exists for: the backend rejects an illegal
     * transition regardless of what a direct API call asks for, not just
     * what a well-behaved frontend would send.
     */
    public function test_an_invalid_transition_is_rejected_with_422(): void
    {
        [$user, $orderId] = $this->paidOrder();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/orders/{$orderId}/status", ['status' => 'shipped'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_transition');

        $this->assertSame('pending', Order::find($orderId)->status);
    }

    /**
     * The customer-facing endpoint's validation whitelist has no
     * "awaiting_payment" or "pending" target at all - so there is no way
     * for a customer request to fake "payment succeeded" for themselves.
     * Only the Stripe webhook can make that specific transition.
     */
    public function test_a_customer_cannot_confirm_their_own_payment_through_the_status_endpoint(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);
        $orderId = $this->checkout($user)->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/orders/{$orderId}/status", ['status' => 'pending'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame('awaiting_payment', Order::find($orderId)->status);
    }

    public function test_an_unknown_status_value_is_rejected_by_validation(): void
    {
        [$user, $orderId] = $this->paidOrder();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/orders/{$orderId}/status", ['status' => 'not-a-real-status'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_a_user_cannot_transition_another_users_order(): void
    {
        $this->fakeSuccessfulPayments();

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $this->actingAs($owner, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);
        $order = $this->checkout($owner);
        $orderId = $order->json('data.id');

        $this->actingAs($intruder, 'sanctum')
            ->patchJson("/api/orders/{$orderId}/status", ['status' => 'confirmed'])
            ->assertStatus(404);
    }

    public function test_cancelling_restores_stock_via_the_api(): void
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']], stock: 20);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 5,
        ]);
        $order = $this->checkout($user);
        $orderId = $order->json('data.id');

        $this->assertSame(15, $variant->fresh()->stock);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/orders/{$orderId}/status", ['status' => 'cancelled'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(20, $variant->fresh()->stock);
    }

    public function test_order_history_records_every_transition(): void
    {
        [$user, $orderId] = $this->paidOrder();

        $this->actingAs($user, 'sanctum')->patchJson("/api/orders/{$orderId}/status", ['status' => 'confirmed']);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/orders/{$orderId}/history");

        $response->assertStatus(200);
        $entries = $response->json('data');

        $this->assertSame([null, 'awaiting_payment'], [$entries[0]['from_status'], $entries[0]['to_status']]);
        $this->assertSame(['awaiting_payment', 'pending'], [$entries[1]['from_status'], $entries[1]['to_status']]);
        $this->assertSame(['pending', 'confirmed'], [$entries[2]['from_status'], $entries[2]['to_status']]);
    }

    public function test_a_user_cannot_view_another_users_order_history(): void
    {
        [, $orderId] = $this->paidOrder();
        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/orders/{$orderId}/history")
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

    private function checkout(User $user)
    {
        return $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/orders');
    }

    /**
     * An order that has been checked out and had its payment confirmed
     * (simulating the Stripe webhook directly, rather than driving a whole
     * fake webhook request through every test that just needs a starting
     * point for the rest of the fulfillment state machine).
     *
     * @return array{0: User, 1: int}
     */
    private function paidOrder(): array
    {
        $this->fakeSuccessfulPayments();

        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);
        $order = $this->checkout($user);
        $orderModel = Order::find($order->json('data.id'));

        app(OrderService::class)->transitionTo($orderModel, Order::STATUS_PENDING);

        return [$user, $orderModel->id];
    }
}
