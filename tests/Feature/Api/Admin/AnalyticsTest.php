<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_regular_customer_is_forbidden_from_the_analytics_overview(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/analytics/overview')
            ->assertStatus(403);
    }

    public function test_an_unauthenticated_request_is_rejected_before_the_staff_check(): void
    {
        $this->getJson('/api/admin/analytics/overview')->assertStatus(401);
    }

    public function test_overview_reports_exact_revenue_status_and_top_product_figures(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $customer = User::factory()->create();

        $variantA = $this->makeVariant('Widget A');
        $variantB = $this->makeVariant('Widget B');
        $variantC = $this->makeVariant('Widget C');

        // Paid, today: Widget A x3, Widget B x2.
        $order1 = $this->makeOrder($customer, Order::STATUS_DELIVERED, Order::PAYMENT_PAID, '30.00', now());
        $this->makeItem($order1, $variantA, 3);
        $this->makeItem($order1, $variantB, 2);

        // Paid, today: Widget A x5.
        $order2 = $this->makeOrder($customer, Order::STATUS_PENDING, Order::PAYMENT_PAID, '20.00', now());
        $this->makeItem($order2, $variantA, 5);

        // Unpaid: must be excluded from revenue, status-count still counts it,
        // and its 100 units of Widget C must never surface in top products.
        $order3 = $this->makeOrder($customer, Order::STATUS_PENDING, Order::PAYMENT_UNPAID, '999.00', now());
        $this->makeItem($order3, $variantC, 100);

        // Paid, 5 days ago: inside the 30-day trend window.
        $order4 = $this->makeOrder($customer, Order::STATUS_CONFIRMED, Order::PAYMENT_PAID, '15.00', now()->subDays(5));

        // Paid, 40 days ago: counts toward total revenue but must NOT
        // appear in the 30-day revenue trend (it's outside the window).
        $order5 = $this->makeOrder($customer, Order::STATUS_DELIVERED, Order::PAYMENT_PAID, '12.34', now()->subDays(40));

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/analytics/overview');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertSame('77.34', $data['total_revenue']);

        $orderCountByStatus = $data['order_count_by_status'];
        ksort($orderCountByStatus);
        $this->assertSame([
            'confirmed' => 1,
            'delivered' => 2,
            'pending' => 2,
        ], $orderCountByStatus);

        $this->assertCount(2, $data['top_products']);
        $this->assertSame('Widget A', $data['top_products'][0]['name']);
        $this->assertSame(8, $data['top_products'][0]['quantity_sold']);
        $this->assertSame('Widget B', $data['top_products'][1]['name']);
        $this->assertSame(2, $data['top_products'][1]['quantity_sold']);

        $revenueByDay = $data['revenue_by_day'];
        $this->assertCount(30, $revenueByDay);
        $this->assertSame(now()->subDays(29)->format('Y-m-d'), $revenueByDay[0]['date']);
        $this->assertSame(now()->format('Y-m-d'), $revenueByDay[29]['date']);

        $byDate = collect($revenueByDay)->keyBy('date');
        $this->assertSame('50.00', $byDate[now()->format('Y-m-d')]['revenue']);
        $this->assertSame('15.00', $byDate[now()->subDays(5)->format('Y-m-d')]['revenue']);
        $this->assertSame('0.00', $byDate[now()->subDays(10)->format('Y-m-d')]['revenue']);
    }

    public function test_csv_export_streams_every_order_as_csv(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $customer = User::factory()->create(['email' => 'buyer@example.com']);

        $variant = $this->makeVariant('Widget A');
        $order = $this->makeOrder($customer, Order::STATUS_DELIVERED, Order::PAYMENT_PAID, '30.00', now());
        $this->makeItem($order, $variant, 1);
        $this->makeOrder($customer, Order::STATUS_PENDING, Order::PAYMENT_UNPAID, '10.00', now());

        $response = $this->actingAs($staff, 'sanctum')->get('/api/admin/analytics/export');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition', 'attachment; filename=orders.csv');

        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $response->streamedContent())));
        $this->assertCount(3, $lines); // header + 2 orders
        $this->assertStringContainsString('buyer@example.com', $response->streamedContent());
    }

    public function test_csv_export_is_forbidden_for_a_regular_customer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/analytics/export')
            ->assertStatus(403);
    }

    public function test_csv_export_is_rejected_when_unauthenticated(): void
    {
        $this->getJson('/api/admin/analytics/export')->assertStatus(401);
    }

    private function makeVariant(string $productName): ProductVariant
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => $productName,
            'slug' => 'product-'.uniqid(),
        ]);

        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'stock' => 1000,
        ]);
    }

    private function makeOrder(User $user, string $status, string $paymentStatus, string $total, $createdAt): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'total' => $total,
        ]);

        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $order;
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
