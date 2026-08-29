<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_order_from_a_cart_decrements_stock_and_snapshots_prices(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00'], [50, '8.00']], stock: 100);
        $cart = $this->cartWithItem($variant, 50);

        $order = app(OrderService::class)->createFromCart($cart->fresh('items.variant'));

        $this->assertSame(Order::STATUS_AWAITING_PAYMENT, $order->status);
        $this->assertSame('400.00', $order->total);
        $this->assertCount(1, $order->items);
        $this->assertSame($variant->sku, $order->items->first()->sku);
        $this->assertSame('8.00', $order->items->first()->unit_price);
        $this->assertSame('400.00', $order->items->first()->line_total);
        $this->assertSame(50, $variant->fresh()->stock);
    }

    public function test_creating_an_order_clears_the_cart(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $cart = $this->cartWithItem($variant, 2);

        app(OrderService::class)->createFromCart($cart->fresh('items.variant'));

        $this->assertSame(0, $cart->fresh()->items()->count());
    }

    public function test_creating_an_order_with_insufficient_stock_rolls_back_everything_including_the_order(): void
    {
        $variantA = $this->variantWithTiers([[1, '10.00']], stock: 10);
        $variantB = $this->variantWithTiers([[1, '5.00']], stock: 2); // not enough for 5

        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        $cart->items()->create(['product_variant_id' => $variantA->id, 'quantity' => 3]);
        $cart->items()->create(['product_variant_id' => $variantB->id, 'quantity' => 5]);

        $threw = false;
        try {
            app(OrderService::class)->createFromCart($cart->fresh('items.variant'));
        } catch (InsufficientStockException) {
            $threw = true;
        }

        $this->assertTrue($threw);
        $this->assertSame(10, $variantA->fresh()->stock, 'Variant A\'s decrement must be rolled back too.');
        $this->assertSame(2, $variantB->fresh()->stock);
        $this->assertSame(0, Order::count(), 'No order should exist when checkout fails.');
        $this->assertSame(2, $cart->fresh()->items()->count(), 'The cart should be untouched.');
    }

    public function test_a_new_order_starts_awaiting_payment(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $cart = $this->cartWithItem($variant, 1);

        $order = app(OrderService::class)->createFromCart($cart->fresh('items.variant'));

        $this->assertSame(Order::STATUS_AWAITING_PAYMENT, $order->status);
    }

    public function test_paying_moves_an_order_from_awaiting_payment_to_pending(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $cart = $this->cartWithItem($variant, 1);
        $order = app(OrderService::class)->createFromCart($cart->fresh('items.variant'));

        app(OrderService::class)->transitionTo($order, Order::STATUS_PENDING);

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_every_transition_is_appended_to_the_status_history(): void
    {
        $order = $this->pendingOrder();

        app(OrderService::class)->transitionTo($order, Order::STATUS_CONFIRMED);

        $history = $order->fresh()->statusHistory;

        $this->assertSame([null, Order::STATUS_AWAITING_PAYMENT], [
            $history[0]->from_status,
            $history[0]->to_status,
        ]);
        $this->assertSame(Order::STATUS_AWAITING_PAYMENT, $history[1]->from_status);
        $this->assertSame(Order::STATUS_PENDING, $history[1]->to_status);
        $this->assertSame(Order::STATUS_PENDING, $history[2]->from_status);
        $this->assertSame(Order::STATUS_CONFIRMED, $history[2]->to_status);
    }

    public function test_an_order_can_move_through_the_full_valid_sequence(): void
    {
        $order = $this->pendingOrder();
        $service = app(OrderService::class);

        $service->transitionTo($order, Order::STATUS_CONFIRMED);
        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);

        $service->transitionTo($order, Order::STATUS_PROCESSING);
        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);

        $service->transitionTo($order, Order::STATUS_SHIPPED);
        $this->assertSame(Order::STATUS_SHIPPED, $order->fresh()->status);

        $service->transitionTo($order, Order::STATUS_DELIVERED);
        $this->assertSame(Order::STATUS_DELIVERED, $order->fresh()->status);
    }

    public function test_skipping_a_state_is_rejected(): void
    {
        $order = $this->pendingOrder();

        $this->expectException(InvalidOrderTransitionException::class);

        app(OrderService::class)->transitionTo($order, Order::STATUS_SHIPPED);
    }

    public function test_a_delivered_order_cannot_transition_anywhere(): void
    {
        $order = $this->pendingOrder();
        $service = app(OrderService::class);

        $service->transitionTo($order, Order::STATUS_CONFIRMED);
        $service->transitionTo($order, Order::STATUS_PROCESSING);
        $service->transitionTo($order, Order::STATUS_SHIPPED);
        $service->transitionTo($order, Order::STATUS_DELIVERED);

        $this->expectException(InvalidOrderTransitionException::class);

        $service->transitionTo($order, Order::STATUS_CANCELLED);
    }

    public function test_a_shipped_order_cannot_be_cancelled(): void
    {
        $order = $this->pendingOrder();
        $service = app(OrderService::class);

        $service->transitionTo($order, Order::STATUS_CONFIRMED);
        $service->transitionTo($order, Order::STATUS_PROCESSING);
        $service->transitionTo($order, Order::STATUS_SHIPPED);

        $this->expectException(InvalidOrderTransitionException::class);

        $service->transitionTo($order, Order::STATUS_CANCELLED);
    }

    public function test_cancelling_an_order_restores_stock(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']], stock: 100);
        $cart = $this->cartWithItem($variant, 10);
        $order = app(OrderService::class)->createFromCart($cart->fresh('items.variant'));

        $this->assertSame(90, $variant->fresh()->stock);

        app(OrderService::class)->transitionTo($order, Order::STATUS_CANCELLED);

        $this->assertSame(100, $variant->fresh()->stock);
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
    }

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

    private function cartWithItem(ProductVariant $variant, int $quantity): Cart
    {
        $cart = Cart::create(['user_id' => User::factory()->create()->id]);
        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => $quantity]);

        return $cart;
    }

    /**
     * An order in the 'pending' state, as if payment had already been
     * confirmed - the natural starting point for tests exercising the rest
     * of the fulfillment state machine.
     */
    private function pendingOrder(): Order
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $cart = $this->cartWithItem($variant, 1);

        $order = app(OrderService::class)->createFromCart($cart->fresh('items.variant'));
        app(OrderService::class)->transitionTo($order, Order::STATUS_PENDING);

        return $order->fresh();
    }
}
