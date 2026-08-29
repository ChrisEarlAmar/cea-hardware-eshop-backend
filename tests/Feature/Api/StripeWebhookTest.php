<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Stripe\Event;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bad_signature_is_rejected(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')
                ->andThrow(new \Stripe\Exception\SignatureVerificationException('bad signature'));
        });

        $this->postJson('/api/stripe/webhook', ['type' => 'payment_intent.succeeded'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'invalid_signature');
    }

    public function test_payment_succeeded_confirms_the_matching_order(): void
    {
        $order = $this->orderAwaitingPayment('pi_test_abc');
        $this->fakeVerifiedEvent('evt_1', 'payment_intent.succeeded', 'pi_test_abc');

        $this->postJson('/api/stripe/webhook', [])->assertStatus(200);

        $order->refresh();
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
    }

    /**
     * Stripe delivers at-least-once, not exactly-once - the same event id
     * can legitimately arrive twice. The second delivery must be a no-op,
     * not a second state transition.
     */
    public function test_the_same_event_id_delivered_twice_is_only_processed_once(): void
    {
        $order = $this->orderAwaitingPayment('pi_test_abc');
        $this->fakeVerifiedEvent('evt_1', 'payment_intent.succeeded', 'pi_test_abc', times: 2);

        $this->postJson('/api/stripe/webhook', [])->assertStatus(200);
        $this->postJson('/api/stripe/webhook', [])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'already_processed');

        $this->assertSame(1, $order->statusHistory()->where('to_status', Order::STATUS_PENDING)->count());
    }

    public function test_an_event_for_an_unknown_payment_intent_is_ignored_without_error(): void
    {
        $this->fakeVerifiedEvent('evt_1', 'payment_intent.succeeded', 'pi_does_not_exist');

        $this->postJson('/api/stripe/webhook', [])->assertStatus(200);
    }

    private function orderAwaitingPayment(string $paymentIntentId): Order
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Bolt', 'slug' => 'test-bolt-'.uniqid()]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => 10]);
        PriceTier::create(['product_variant_id' => $variant->id, 'min_quantity' => 1, 'unit_price' => '10.00']);

        $user = User::factory()->create();

        return Order::create([
            'user_id' => $user->id,
            'status' => Order::STATUS_AWAITING_PAYMENT,
            'total' => '10.00',
            'payment_intent_id' => $paymentIntentId,
            'payment_status' => Order::PAYMENT_REQUIRES_PAYMENT,
        ]);
    }

    private function fakeVerifiedEvent(string $eventId, string $type, string $paymentIntentId, int $times = 1): void
    {
        $event = Event::constructFrom([
            'id' => $eventId,
            'type' => $type,
            'data' => ['object' => ['id' => $paymentIntentId]],
        ]);

        $this->mock(PaymentService::class, function ($mock) use ($event, $times) {
            $mock->shouldReceive('verifyWebhookSignature')->times($times)->andReturn($event);
        });
    }
}
