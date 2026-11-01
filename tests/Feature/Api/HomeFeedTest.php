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

class HomeFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_feed_is_public(): void
    {
        $this->getJson('/api/home-feed')->assertStatus(200);
    }

    public function test_featured_returns_only_featured_products_capped_at_eight(): void
    {
        for ($i = 1; $i <= 9; $i++) {
            $this->makeProduct("Featured {$i}", isFeatured: true);
        }
        $this->makeProduct('Not Featured', isFeatured: false);

        $response = $this->getJson('/api/home-feed');

        $response->assertStatus(200);
        $featured = $response->json('data.featured');

        $this->assertCount(8, $featured);
        $this->assertTrue(collect($featured)->every(fn ($p) => $p['is_featured'] === true));
        $this->assertFalse(collect($featured)->contains('name', 'Not Featured'));
    }

    public function test_featured_entries_include_the_expected_shape_and_sale_info(): void
    {
        $onSale = $this->makeProduct('On Sale Widget', isFeatured: true, saleDiscountPercent: '25.00');
        $this->variantFor($onSale, '40.00');

        $response = $this->getJson('/api/home-feed');

        $entry = collect($response->json('data.featured'))->firstWhere('name', 'On Sale Widget');

        $this->assertNotNull($entry);
        $this->assertSame($onSale->id, $entry['id']);
        $this->assertSame($onSale->slug, $entry['slug']);
        $this->assertSame('40.00', $entry['starting_price']);
        $this->assertTrue($entry['is_featured']);
        $this->assertSame('25.00', $entry['sale_discount_percent']);
    }

    public function test_an_expired_sale_does_not_report_a_discount_percent(): void
    {
        $expired = $this->makeProduct('Expired Sale Widget', isFeatured: true, saleDiscountPercent: '50.00', saleEndsAt: now()->subDay());

        $response = $this->getJson('/api/home-feed');

        $entry = collect($response->json('data.featured'))->firstWhere('name', 'Expired Sale Widget');

        $this->assertNull($entry['sale_discount_percent']);
    }

    public function test_new_arrivals_returns_the_eight_most_recently_created_products(): void
    {
        $oldest = $this->makeProduct('Oldest');
        $oldest->forceFill(['created_at' => now()->subDays(10)])->save();

        for ($i = 1; $i <= 8; $i++) {
            $product = $this->makeProduct("Newer {$i}");
            $product->forceFill(['created_at' => now()->subDays(8 - $i)])->save();
        }

        $response = $this->getJson('/api/home-feed');
        $newArrivals = $response->json('data.new_arrivals');

        $this->assertCount(8, $newArrivals);
        $this->assertFalse(collect($newArrivals)->contains('name', 'Oldest'));
        $this->assertSame('Newer 8', $newArrivals[0]['name']);
    }

    public function test_best_sellers_ranks_by_units_sold_across_paid_orders_only(): void
    {
        $customer = User::factory()->create();

        $variantA = $this->variantFor($this->makeProduct('Widget A'), '10.00');
        $variantB = $this->variantFor($this->makeProduct('Widget B'), '10.00');
        $variantC = $this->variantFor($this->makeProduct('Widget C'), '10.00');

        // Paid: Widget A x5, Widget B x2.
        $paidOrder = $this->makeOrder($customer, Order::PAYMENT_PAID);
        $this->makeItem($paidOrder, $variantA, 5);
        $this->makeItem($paidOrder, $variantB, 2);

        // Unpaid: Widget C x100 must never outrank the paid products above.
        $unpaidOrder = $this->makeOrder($customer, Order::PAYMENT_UNPAID);
        $this->makeItem($unpaidOrder, $variantC, 100);

        $response = $this->getJson('/api/home-feed');
        $bestSellers = $response->json('data.best_sellers');

        $this->assertCount(2, $bestSellers);
        $this->assertSame('Widget A', $bestSellers[0]['name']);
        $this->assertSame('Widget B', $bestSellers[1]['name']);
    }

    public function test_best_sellers_is_empty_when_no_orders_have_been_paid(): void
    {
        $this->makeProduct('Widget A');

        $response = $this->getJson('/api/home-feed');

        $this->assertSame([], $response->json('data.best_sellers'));
    }

    private function makeProduct(
        string $name,
        bool $isFeatured = false,
        ?string $saleDiscountPercent = null,
        $saleEndsAt = null,
    ): Product {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => 'product-'.uniqid(),
            'is_featured' => $isFeatured,
            'sale_discount_percent' => $saleDiscountPercent,
            'sale_ends_at' => $saleEndsAt,
        ]);
    }

    private function variantFor(Product $product, string $unitPrice): ProductVariant
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'stock' => 1000,
        ]);

        PriceTier::create([
            'product_variant_id' => $variant->id,
            'min_quantity' => 1,
            'unit_price' => $unitPrice,
        ]);

        return $variant;
    }

    private function makeOrder(User $user, string $paymentStatus): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => $paymentStatus,
            'total' => '0.00',
        ]);
    }

    private function makeItem(Order $order, ProductVariant $variant, int $quantity): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => $quantity,
            'unit_price' => '10.00',
            'line_total' => bcmul((string) $quantity, '10.00', 2),
        ]);
    }
}
