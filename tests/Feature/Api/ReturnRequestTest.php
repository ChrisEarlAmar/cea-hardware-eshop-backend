<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\Admin\ReturnController as AdminReturnController;
use App\Http\Controllers\Api\ReturnController;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * These routes are not wired into routes/api.php yet - see the parent
 * task's "## ROUTES NEEDED" note - so this test class registers them
 * itself, the same shape they're expected to have once integrated, rather
 * than touching that shared file. SubstituteBindings has to be added back
 * explicitly here since these routes bypass the real routes/api.php file's
 * "api" middleware group, which is where that middleware normally comes
 * from - without it, {orderItem}/{returnRequest} would never resolve to
 * real Eloquent models.
 */
class ReturnRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([SubstituteBindings::class, 'auth:sanctum'])->group(function () {
            Route::post('/api/order-items/{orderItem}/return', [ReturnController::class, 'store']);
            Route::get('/api/returns', [ReturnController::class, 'index']);
        });

        Route::middleware([SubstituteBindings::class, 'auth:sanctum', 'staff'])->prefix('api/admin')->group(function () {
            Route::get('/returns', [AdminReturnController::class, 'index']);
            Route::post('/returns/{returnRequest}/approve', [AdminReturnController::class, 'approve']);
            Route::post('/returns/{returnRequest}/reject', [AdminReturnController::class, 'reject']);
            Route::post('/returns/{returnRequest}/refund', [AdminReturnController::class, 'refund']);
        });
    }

    public function test_a_return_cannot_be_requested_for_an_undelivered_order(): void
    {
        $user = User::factory()->create();
        [, $item] = $this->orderWithItem($user, Order::STATUS_PROCESSING);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/order-items/{$item->id}/return", ['reason' => 'Wrong size']);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'order_not_delivered');

        $this->assertSame(0, ReturnRequest::count());
    }

    public function test_a_return_requires_a_reason(): void
    {
        $user = User::factory()->create();
        [, $item] = $this->orderWithItem($user, Order::STATUS_DELIVERED);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/order-items/{$item->id}/return", [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_a_return_can_be_requested_for_a_delivered_order_item(): void
    {
        $user = User::factory()->create();
        [$order, $item] = $this->orderWithItem($user, Order::STATUS_DELIVERED);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/order-items/{$item->id}/return", ['reason' => 'Wrong size'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', ReturnRequest::STATUS_REQUESTED)
            ->assertJsonPath('data.order_id', $order->id);

        $this->assertSame(1, ReturnRequest::count());
    }

    public function test_a_second_return_cannot_be_requested_while_one_is_already_pending(): void
    {
        $user = User::factory()->create();
        [, $item] = $this->orderWithItem($user, Order::STATUS_DELIVERED);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/order-items/{$item->id}/return", ['reason' => 'Wrong size'])
            ->assertStatus(201);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/order-items/{$item->id}/return", ['reason' => 'Changed my mind'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'return_already_requested');

        $this->assertSame(1, ReturnRequest::count());
    }

    public function test_a_new_return_can_be_requested_once_the_previous_one_was_rejected(): void
    {
        $user = User::factory()->create();
        [, $item] = $this->orderWithItem($user, Order::STATUS_DELIVERED);

        ReturnRequest::create([
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'reason' => 'Wrong size',
            'status' => ReturnRequest::STATUS_REJECTED,
            'requested_at' => now(),
            'resolved_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/order-items/{$item->id}/return", ['reason' => 'Try again'])
            ->assertStatus(201);

        $this->assertSame(2, ReturnRequest::count());
    }

    public function test_a_user_cannot_request_a_return_for_another_users_order_item(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [, $item] = $this->orderWithItem($owner, Order::STATUS_DELIVERED);

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/order-items/{$item->id}/return", ['reason' => 'Not mine'])
            ->assertStatus(404);

        $this->assertSame(0, ReturnRequest::count());
    }

    public function test_a_user_can_list_their_own_return_requests(): void
    {
        $user = User::factory()->create();
        [, $item] = $this->orderWithItem($user, Order::STATUS_DELIVERED);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/order-items/{$item->id}/return", ['reason' => 'Wrong size']);

        $other = User::factory()->create();
        [, $otherItem] = $this->orderWithItem($other, Order::STATUS_DELIVERED);
        $this->actingAs($other, 'sanctum')
            ->postJson("/api/order-items/{$otherItem->id}/return", ['reason' => 'Damaged']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/returns');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_returns_require_authentication(): void
    {
        $this->getJson('/api/returns')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_a_regular_customer_is_forbidden_from_the_admin_returns_surface(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/returns')
            ->assertStatus(403);
    }

    public function test_an_unauthenticated_request_is_rejected_before_the_admin_staff_check(): void
    {
        $this->getJson('/api/admin/returns')->assertStatus(401);
    }

    public function test_staff_can_approve_a_requested_return(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $returnRequest = $this->requestedReturn();

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/admin/returns/{$returnRequest->id}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', ReturnRequest::STATUS_APPROVED);

        $this->assertSame($staff->id, $returnRequest->fresh()->resolved_by);
        $this->assertNotNull($returnRequest->fresh()->resolved_at);
    }

    /**
     * Proves the endpoint enforces ReturnRequest::canTransitionTo rather
     * than trusting the caller: a return that's already rejected (a
     * terminal state - see ReturnRequest::TRANSITIONS) can never be
     * rejected again.
     */
    public function test_rejecting_an_already_rejected_return_is_rejected_with_422(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $returnRequest = $this->requestedReturn();
        $returnRequest->update(['status' => ReturnRequest::STATUS_REJECTED, 'resolved_at' => now()]);

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/admin/returns/{$returnRequest->id}/reject")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_transition');
    }

    public function test_a_refund_can_only_be_recorded_for_an_approved_return(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $returnRequest = $this->requestedReturn();

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/admin/returns/{$returnRequest->id}/refund", ['refund_amount' => '10.00'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_transition');
    }

    public function test_staff_can_record_a_refund_for_an_approved_return(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $returnRequest = $this->requestedReturn();
        $returnRequest->update(['status' => ReturnRequest::STATUS_APPROVED]);

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/admin/returns/{$returnRequest->id}/refund", ['refund_amount' => '10.00'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', ReturnRequest::STATUS_REFUNDED)
            ->assertJsonPath('data.refund_amount', '10.00');
    }

    public function test_a_refund_requires_a_refund_amount(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $returnRequest = $this->requestedReturn();
        $returnRequest->update(['status' => ReturnRequest::STATUS_APPROVED]);

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/admin/returns/{$returnRequest->id}/refund", [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    /**
     * @return array{0: Order, 1: OrderItem}
     */
    private function orderWithItem(User $user, string $status): array
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Bolt', 'slug' => 'test-bolt-'.uniqid()]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => 10]);

        $order = Order::create([
            'user_id' => $user->id,
            'status' => $status,
            'total' => '10.00',
        ]);

        $item = $order->items()->create([
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => '10.00',
            'line_total' => '10.00',
        ]);

        return [$order, $item];
    }

    private function requestedReturn(): ReturnRequest
    {
        $user = User::factory()->create();
        [, $item] = $this->orderWithItem($user, Order::STATUS_DELIVERED);

        return ReturnRequest::create([
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'reason' => 'Wrong size',
            'status' => ReturnRequest::STATUS_REQUESTED,
            'requested_at' => now(),
        ]);
    }
}
