<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\ReviewController;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * NOTE: these routes are not yet wired into routes/api.php - that file is
 * owned by a separate integration pass merging every feature slice at once
 * (see this feature's task notes), so it is deliberately not touched here.
 * setUp() below registers the exact same routes locally, through the same
 * 'api' middleware group + guards, purely so this slice's controllers can
 * be exercised over HTTP ahead of that integration. See "## ROUTES NEEDED"
 * in the final report for the real routes/api.php lines to add.
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->group(function () {
            Route::get('/api/products/{product:slug}/reviews', [ReviewController::class, 'index']);

            Route::middleware('auth:sanctum')->group(function () {
                Route::post('/api/products/{product:slug}/reviews', [ReviewController::class, 'store']);

                Route::middleware('staff')->prefix('api/admin')->group(function () {
                    Route::get('/reviews', [AdminReviewController::class, 'index']);
                    Route::post('/reviews/{review}/approve', [AdminReviewController::class, 'approve']);
                    Route::post('/reviews/{review}/reject', [AdminReviewController::class, 'reject']);
                });
            });
        });
    }

    public function test_submitting_a_review_requires_authentication(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $this->postJson("/api/products/{$variant->product->slug}/reviews", [
            'rating' => 5, 'title' => 'Great', 'body' => 'Works well.',
        ])->assertStatus(401);
    }

    public function test_a_review_without_a_verified_purchase_is_rejected(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/products/{$variant->product->slug}/reviews", [
                'rating' => 5, 'title' => 'Great', 'body' => 'Works well.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'no_verified_purchase');
    }

    public function test_an_order_that_has_not_been_delivered_yet_does_not_qualify(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $this->orderFor($user, $variant, Order::STATUS_PROCESSING);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/products/{$variant->product->slug}/reviews", [
                'rating' => 5, 'title' => 'Great', 'body' => 'Works well.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'no_verified_purchase');
    }

    public function test_a_verified_purchaser_can_submit_a_review(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $order = $this->orderFor($user, $variant, Order::STATUS_DELIVERED);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/products/{$variant->product->slug}/reviews", [
                'rating' => 4, 'title' => 'Solid', 'body' => 'Did the job well.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.title', 'Solid')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('reviews', [
            'product_id' => $variant->product_id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'status' => Review::STATUS_PENDING,
        ]);
    }

    public function test_reviewing_the_same_product_twice_is_rejected(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $this->orderFor($user, $variant, Order::STATUS_DELIVERED);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/products/{$variant->product->slug}/reviews", [
                'rating' => 4, 'title' => 'Solid', 'body' => 'Did the job well.',
            ])->assertStatus(201);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/products/{$variant->product->slug}/reviews", [
                'rating' => 3, 'title' => 'Again', 'body' => 'Trying to review twice.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'already_reviewed');

        $this->assertSame(1, Review::count());
    }

    public function test_a_rating_outside_one_to_five_is_rejected(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $this->orderFor($user, $variant, Order::STATUS_DELIVERED);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/products/{$variant->product->slug}/reviews", [
                'rating' => 6, 'title' => 'Bad rating', 'body' => 'Out of range.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame(0, Review::count());
    }

    public function test_only_approved_reviews_appear_in_the_public_index(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $product = $variant->product;

        $approvedUser = User::factory()->create();
        Review::create([
            'product_id' => $product->id, 'user_id' => $approvedUser->id,
            'rating' => 5, 'title' => 'Approved', 'body' => 'Good product.',
            'status' => Review::STATUS_APPROVED,
        ]);

        $pendingUser = User::factory()->create();
        Review::create([
            'product_id' => $product->id, 'user_id' => $pendingUser->id,
            'rating' => 3, 'title' => 'Pending', 'body' => 'Still waiting on this.',
            'status' => Review::STATUS_PENDING,
        ]);

        $rejectedUser = User::factory()->create();
        Review::create([
            'product_id' => $product->id, 'user_id' => $rejectedUser->id,
            'rating' => 1, 'title' => 'Rejected', 'body' => 'This was rejected.',
            'status' => Review::STATUS_REJECTED,
        ]);

        $response = $this->getJson("/api/products/{$product->slug}/reviews");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Approved', $response->json('data.0.title'));
    }

    public function test_average_rating_and_count_are_computed_from_approved_reviews_only(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $product = $variant->product;

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        Review::create([
            'product_id' => $product->id, 'user_id' => $userA->id,
            'rating' => 5, 'title' => 'A', 'body' => 'Review body A.', 'status' => Review::STATUS_APPROVED,
        ]);
        Review::create([
            'product_id' => $product->id, 'user_id' => $userB->id,
            'rating' => 3, 'title' => 'B', 'body' => 'Review body B.', 'status' => Review::STATUS_APPROVED,
        ]);
        Review::create([
            'product_id' => $product->id, 'user_id' => $userC->id,
            'rating' => 1, 'title' => 'C', 'body' => 'Review body C.', 'status' => Review::STATUS_REJECTED,
        ]);

        $response = $this->getJson("/api/products/{$product->slug}/reviews");

        $response->assertStatus(200)
            ->assertJsonPath('meta.average_rating', 4)
            ->assertJsonPath('meta.count', 2);
    }

    public function test_a_non_staff_user_is_forbidden_from_admin_review_endpoints(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $reviewer = User::factory()->create();
        $review = Review::create([
            'product_id' => $variant->product_id, 'user_id' => $reviewer->id,
            'rating' => 4, 'title' => 'Pending', 'body' => 'Awaiting moderation.',
            'status' => Review::STATUS_PENDING,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/reviews')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->postJson("/api/admin/reviews/{$review->id}/approve")->assertStatus(403);
        $this->actingAs($user, 'sanctum')->postJson("/api/admin/reviews/{$review->id}/reject")->assertStatus(403);
    }

    public function test_staff_can_list_pending_reviews(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $reviewer = User::factory()->create();

        Review::create([
            'product_id' => $variant->product_id, 'user_id' => $reviewer->id,
            'rating' => 4, 'title' => 'Pending review', 'body' => 'Awaiting moderation.',
            'status' => Review::STATUS_PENDING,
        ]);
        Review::create([
            'product_id' => $variant->product_id, 'user_id' => User::factory()->create()->id,
            'rating' => 5, 'title' => 'Already approved', 'body' => 'Not part of the queue.',
            'status' => Review::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/reviews');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Pending review', $response->json('data.0.title'));
    }

    public function test_staff_approving_a_review_makes_it_appear_in_the_public_index(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $product = $variant->product;
        $reviewer = User::factory()->create();
        $review = Review::create([
            'product_id' => $product->id, 'user_id' => $reviewer->id,
            'rating' => 5, 'title' => 'Great tool', 'body' => 'Works exactly as advertised.',
            'status' => Review::STATUS_PENDING,
        ]);

        $this->getJson("/api/products/{$product->slug}/reviews")->assertJsonCount(0, 'data');

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/admin/reviews/{$review->id}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->getJson("/api/products/{$product->slug}/reviews")->assertJsonCount(1, 'data');
    }

    public function test_staff_rejecting_a_review_keeps_it_out_of_the_public_index(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $product = $variant->product;
        $reviewer = User::factory()->create();
        $review = Review::create([
            'product_id' => $product->id, 'user_id' => $reviewer->id,
            'rating' => 2, 'title' => 'Not great', 'body' => 'Did not meet expectations.',
            'status' => Review::STATUS_PENDING,
        ]);

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/admin/reviews/{$review->id}/reject")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        $this->getJson("/api/products/{$product->slug}/reviews")->assertJsonCount(0, 'data');
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

    private function orderFor(User $user, ProductVariant $variant, string $status): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'status' => $status,
            'total' => '10.00',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => '10.00',
            'line_total' => '10.00',
        ]);

        return $order;
    }
}
