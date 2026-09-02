<?php

namespace Tests\Feature\Api\Admin;

use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ShipmentController;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * This route is not wired into routes/api.php yet - see "## ROUTES NEEDED"
 * in the parent task - so it's registered here rather than in that shared
 * file. SubstituteBindings is added back explicitly since this bypasses the
 * real routes/api.php file's "api" middleware group, which is where that
 * middleware normally comes from - without it, {order} would never resolve
 * to a real Eloquent model.
 */
class ShipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([SubstituteBindings::class, 'auth:sanctum', 'staff'])->prefix('api/admin')->group(function () {
            Route::patch('/orders/{order}/tracking', [ShipmentController::class, 'update']);
            Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
        });
    }

    public function test_an_unauthenticated_request_is_rejected_before_the_staff_check(): void
    {
        $order = $this->orderFor(User::factory()->create());

        $this->patchJson("/api/admin/orders/{$order->id}/tracking", [
            'tracking_carrier' => 'UPS', 'tracking_number' => '1Z999',
        ])->assertStatus(401);
    }

    public function test_a_regular_customer_is_forbidden_from_setting_tracking_info(): void
    {
        $customer = User::factory()->create();
        $order = $this->orderFor($customer);

        $this->actingAs($customer, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/tracking", [
                'tracking_carrier' => 'UPS', 'tracking_number' => '1Z999',
            ])
            ->assertStatus(403);
    }

    public function test_staff_can_set_the_tracking_carrier_and_number(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $order = $this->orderFor(User::factory()->create());

        $this->actingAs($staff, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/tracking", [
                'tracking_carrier' => 'UPS', 'tracking_number' => '1Z999AA1234567890',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.tracking_carrier', 'UPS')
            ->assertJsonPath('data.tracking_number', '1Z999AA1234567890');

        $order->refresh();
        $this->assertSame('UPS', $order->tracking_carrier);
        $this->assertSame('1Z999AA1234567890', $order->tracking_number);
    }

    public function test_tracking_carrier_and_number_are_required_together(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $order = $this->orderFor(User::factory()->create());

        $this->actingAs($staff, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/tracking", ['tracking_carrier' => 'UPS'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAs($staff, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/tracking", ['tracking_number' => '1Z999'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertNull($order->fresh()->tracking_carrier);
    }

    public function test_staff_can_view_a_single_orders_detail(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $customer = User::factory()->create();
        $order = $this->orderFor($customer);

        $this->actingAs($staff, 'sanctum')
            ->getJson("/api/admin/orders/{$order->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.customer.email', $customer->email)
            ->assertJsonPath('data.items.0.sku', $order->items->first()->sku);
    }

    public function test_a_regular_customer_is_forbidden_from_the_admin_order_detail_endpoint(): void
    {
        $customer = User::factory()->create();
        $order = $this->orderFor($customer);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/admin/orders/{$order->id}")
            ->assertStatus(403);
    }

    private function orderFor(User $user): Order
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Bolt', 'slug' => 'test-bolt-'.uniqid()]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => 10]);

        $order = Order::create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PROCESSING,
            'total' => '10.00',
        ]);

        $order->items()->create([
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => '10.00',
            'line_total' => '10.00',
        ]);

        return $order;
    }
}
