<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\Admin\RfqController as AdminRfqController;
use App\Http\Controllers\Api\RfqController;
use App\Models\Category;
use App\Models\Product;
use App\Models\RfqRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RfqTest extends TestCase
{
    use RefreshDatabase;

    /**
     * routes/api.php is being edited concurrently by other feature slices
     * of this project, so this test registers its own routes at runtime
     * rather than relying on that shared file. The exact lines these
     * mirror are listed under "## ROUTES NEEDED" in this task's report -
     * that's the actual wiring for the integrator to add once every
     * concurrent slice has landed.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum'])->group(function () {
            Route::get('/api/rfq', [RfqController::class, 'index']);
            Route::post('/api/rfq', [RfqController::class, 'store']);

            Route::middleware('staff')->prefix('api/admin')->group(function () {
                Route::get('/rfq', [AdminRfqController::class, 'index']);
                Route::post('/rfq/{rfq}/respond', [AdminRfqController::class, 'respond']);
            });
        });
    }

    public function test_submitting_an_rfq_requires_authentication(): void
    {
        $product = $this->product();

        $this->postJson('/api/rfq', [
            'product_id' => $product->id,
            'quantity' => 100,
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_a_signed_in_user_can_submit_an_rfq(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/rfq', [
            'product_id' => $product->id,
            'quantity' => 500,
            'message' => 'Need a bulk quote for a job site.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.quantity', 500)
            ->assertJsonPath('data.message', 'Need a bulk quote for a job site.')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.product.id', $product->id);

        $this->assertSame(1, RfqRequest::count());
        $this->assertSame($user->id, RfqRequest::first()->user_id);
    }

    public function test_rfq_validation_rejects_a_missing_product_and_a_zero_quantity(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/rfq', [
            'quantity' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_rfq_validation_rejects_an_unknown_product_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/rfq', [
            'product_id' => 999999,
            'quantity' => 10,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_a_user_only_sees_their_own_rfqs(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = $this->product();

        RfqRequest::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'status' => RfqRequest::STATUS_OPEN,
        ]);
        RfqRequest::create([
            'user_id' => $other->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'status' => RfqRequest::STATUS_OPEN,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/rfq');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(10, $response->json('data.0.quantity'));
    }

    public function test_listing_rfqs_requires_authentication(): void
    {
        $this->getJson('/api/rfq')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_a_non_staff_user_cannot_access_the_admin_rfq_queue(): void
    {
        $user = User::factory()->create(['is_staff' => false]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/rfq')
            ->assertStatus(403);
    }

    public function test_staff_can_list_all_rfqs_and_filter_by_status(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $requester = User::factory()->create();
        $product = $this->product();

        $open = RfqRequest::create([
            'user_id' => $requester->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'status' => RfqRequest::STATUS_OPEN,
        ]);
        RfqRequest::create([
            'user_id' => $requester->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'status' => RfqRequest::STATUS_CLOSED,
        ]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/rfq?status=open');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($open->id, $response->json('data.0.id'));
        $this->assertSame($requester->email, $response->json('data.0.customer.email'));
    }

    public function test_staff_can_mark_an_rfq_responded(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $requester = User::factory()->create();
        $product = $this->product();

        $rfq = RfqRequest::create([
            'user_id' => $requester->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'status' => RfqRequest::STATUS_OPEN,
        ]);

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/admin/rfq/{$rfq->id}/respond");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'responded');

        $this->assertSame(RfqRequest::STATUS_RESPONDED, $rfq->fresh()->status);
    }

    public function test_a_non_staff_user_cannot_mark_an_rfq_responded(): void
    {
        $user = User::factory()->create(['is_staff' => false]);
        $product = $this->product();

        $rfq = RfqRequest::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'status' => RfqRequest::STATUS_OPEN,
        ]);

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/rfq/{$rfq->id}/respond")
            ->assertStatus(403);

        $this->assertSame(RfqRequest::STATUS_OPEN, $rfq->fresh()->status);
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Test Bolt',
            'slug' => 'test-bolt-'.uniqid(),
        ]);
    }
}
