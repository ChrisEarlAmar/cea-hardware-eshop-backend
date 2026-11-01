<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlsoBoughtTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_products_bought_together_in_a_paid_order_surface_for_each_other(): void
    {
        $variantA = $this->variantWithTiers([[1, '10.00']]);
        $variantB = $this->variantWithTiers([[1, '20.00']]);

        $this->paidOrder([$variantA, $variantB]);

        $response = $this->getJson("/api/products/{$variantA->product_id}/also-bought");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($variantB->product_id, $data[0]['id']);
        $this->assertSame($variantB->product->slug, $data[0]['slug']);

        $reverse = $this->getJson("/api/products/{$variantB->product_id}/also-bought")->json('data');
        $this->assertCount(1, $reverse);
        $this->assertSame($variantA->product_id, $reverse[0]['id']);
    }

    public function test_an_unpaid_orders_items_do_not_count(): void
    {
        $variantA = $this->variantWithTiers([[1, '10.00']]);
        $variantB = $this->variantWithTiers([[1, '20.00']]);

        $this->paidOrder([$variantA, $variantB], paymentStatus: Order::PAYMENT_REQUIRES_PAYMENT);

        $response = $this->getJson("/api/products/{$variantA->product_id}/also-bought");

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
    }

    public function test_ranking_favors_a_pair_bought_together_more_often(): void
    {
        $anchor = $this->variantWithTiers([[1, '10.00']]);
        $frequentPartner = $this->variantWithTiers([[1, '5.00']]);
        $rarePartner = $this->variantWithTiers([[1, '5.00']]);

        $this->paidOrder([$anchor, $frequentPartner]);
        $this->paidOrder([$anchor, $frequentPartner]);
        $this->paidOrder([$anchor, $frequentPartner]);
        $this->paidOrder([$anchor, $rarePartner]);

        $data = $this->getJson("/api/products/{$anchor->product_id}/also-bought")->json('data');

        $this->assertCount(2, $data);
        $this->assertSame($frequentPartner->product_id, $data[0]['id'], 'The pair bought together 3 times should rank first.');
        $this->assertSame($rarePartner->product_id, $data[1]['id']);
    }

    public function test_a_product_with_no_order_history_returns_an_empty_list(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $response = $this->getJson("/api/products/{$variant->product_id}/also-bought");

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
    }

    public function test_the_product_itself_never_appears_in_its_own_results(): void
    {
        $variantA = $this->variantWithTiers([[1, '10.00']]);
        $variantB = $this->variantWithTiers([[1, '20.00']]);

        // Two lines of the anchor product's own variant in the same paid
        // order, alongside one other product - the anchor must never rank
        // itself as something "also bought" with itself.
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'status' => Order::STATUS_CONFIRMED,
            'total' => '40.00',
            'payment_status' => Order::PAYMENT_PAID,
        ]);
        $this->addItem($order, $variantA, quantity: 1, unitPrice: '10.00');
        $this->addItem($order, $variantA, quantity: 1, unitPrice: '10.00');
        $this->addItem($order, $variantB, quantity: 1, unitPrice: '20.00');

        $data = $this->getJson("/api/products/{$variantA->product_id}/also-bought")->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($variantB->product_id, $data[0]['id']);
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

    /**
     * A paid (by default) order containing one line per given variant -
     * built directly against Order/OrderItem rather than driving the whole
     * checkout flow, since this feature only cares about what ends up in
     * order_items once payment has settled.
     *
     * @param  array<int, ProductVariant>  $variants
     */
    private function paidOrder(array $variants, string $paymentStatus = Order::PAYMENT_PAID): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'status' => Order::STATUS_CONFIRMED,
            'total' => '0.00',
            'payment_status' => $paymentStatus,
        ]);

        foreach ($variants as $variant) {
            $this->addItem($order, $variant, quantity: 1, unitPrice: '1.00');
        }

        return $order;
    }

    private function addItem(Order $order, ProductVariant $variant, int $quantity, string $unitPrice): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice,
        ]);
    }
}
