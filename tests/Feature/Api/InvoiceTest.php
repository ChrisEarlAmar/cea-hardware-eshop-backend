<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\InvoiceController;
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
 * file. See App\Http\Controllers\Api\InvoiceController for why this is the
 * one endpoint in the app that doesn't go through App\Support\ApiResponse.
 * SubstituteBindings is added back explicitly since this bypasses the real
 * routes/api.php file's "api" middleware group, which is where that
 * middleware normally comes from - without it, {order} would never resolve
 * to a real Eloquent model.
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([SubstituteBindings::class, 'auth:sanctum'])->group(function () {
            Route::get('/api/orders/{order}/invoice', [InvoiceController::class, 'show']);
        });
    }

    public function test_invoice_download_requires_authentication(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        $this->getJson("/api/orders/{$order->id}/invoice")->assertStatus(401);
    }

    public function test_a_user_cannot_download_another_users_invoice(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = $this->orderFor($owner);

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/orders/{$order->id}/invoice")
            ->assertStatus(404);
    }

    public function test_a_user_can_download_their_own_order_invoice_as_a_pdf(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        $response = $this->actingAs($user, 'sanctum')->get("/api/orders/{$order->id}/invoice");

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString((string) $order->id, $response->headers->get('Content-Disposition'));
    }

    private function orderFor(User $user): Order
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Bolt', 'slug' => 'test-bolt-'.uniqid()]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => 10]);

        $order = Order::create([
            'user_id' => $user->id,
            'status' => Order::STATUS_DELIVERED,
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
