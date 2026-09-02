<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_requires_authentication(): void
    {
        $this->getJson('/api/account/export')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_export_includes_only_the_requesting_users_own_data(): void
    {
        $user = User::factory()->create(['name' => 'Own User', 'email' => 'own@example.com']);
        $otherUser = User::factory()->create(['name' => 'Other User', 'email' => 'other@example.com']);

        $product = $this->product();

        $order = $this->orderWithItem($user, $product);
        $this->orderWithItem($otherUser, $product);

        Address::create([
            'user_id' => $user->id,
            'label' => 'Home',
            'recipient_name' => $user->name,
            'line1' => '123 Main St',
            'city' => 'Metropolis',
            'state' => 'CA',
            'postal_code' => '90000',
            'country' => 'US',
        ]);
        Address::create([
            'user_id' => $otherUser->id,
            'label' => 'Home',
            'recipient_name' => $otherUser->name,
            'line1' => '456 Other St',
            'city' => 'Gotham',
            'state' => 'NJ',
            'postal_code' => '10000',
            'country' => 'US',
        ]);

        Review::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'rating' => 5,
            'title' => 'Great',
            'body' => 'Loved it',
            'status' => Review::STATUS_APPROVED,
        ]);
        Review::create([
            'product_id' => $product->id,
            'user_id' => $otherUser->id,
            'order_id' => $order->id,
            'rating' => 1,
            'title' => 'Bad',
            'body' => 'Did not like it',
            'status' => Review::STATUS_APPROVED,
        ]);

        WishlistItem::create(['user_id' => $user->id, 'product_id' => $product->id]);
        WishlistItem::create(['user_id' => $otherUser->id, 'product_id' => $product->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/account/export');

        $response->assertStatus(200)
            ->assertJsonPath('data.profile.email', 'own@example.com');

        $profile = $response->json('data.profile');
        $this->assertArrayNotHasKey('password', $profile);

        $this->assertCount(1, $response->json('data.orders'));
        $this->assertSame($order->id, $response->json('data.orders.0.id'));
        $this->assertCount(1, $response->json('data.orders.0.items'));

        $this->assertCount(1, $response->json('data.addresses'));
        $this->assertSame('123 Main St', $response->json('data.addresses.0.line1'));

        $this->assertCount(1, $response->json('data.reviews'));
        $this->assertSame('Great', $response->json('data.reviews.0.title'));

        $this->assertCount(1, $response->json('data.wishlist_items'));
    }

    public function test_deletion_request_endpoints_require_authentication(): void
    {
        $this->postJson('/api/account/deletion-request')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');

        $this->postJson('/api/account/deletion-request/cancel')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_requesting_deletion_sets_the_timestamp(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/account/deletion-request');

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.deletion_requested_at'));
        $this->assertNotNull($user->fresh()->deletion_requested_at);
    }

    public function test_requesting_deletion_twice_does_not_change_the_timestamp(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user, 'sanctum')->postJson('/api/account/deletion-request');
        $firstTimestamp = $first->json('data.deletion_requested_at');

        $this->travel(1)->hours();

        $second = $this->actingAs($user, 'sanctum')->postJson('/api/account/deletion-request');

        $second->assertStatus(200);
        $this->assertSame($firstTimestamp, $second->json('data.deletion_requested_at'));
    }

    public function test_cancelling_deletion_clears_the_timestamp(): void
    {
        $user = User::factory()->create(['deletion_requested_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/account/deletion-request/cancel');

        $response->assertStatus(200)
            ->assertJsonPath('data.deletion_requested_at', null);

        $this->assertNull($user->fresh()->deletion_requested_at);
    }

    public function test_admin_deletion_requests_list_is_staff_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/deletion-requests')
            ->assertStatus(403);
    }

    public function test_admin_deletion_requests_list_requires_authentication(): void
    {
        $this->getJson('/api/admin/deletion-requests')
            ->assertStatus(401);
    }

    public function test_admin_deletion_requests_lists_only_users_with_a_pending_request(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $pending = User::factory()->create(['deletion_requested_at' => now()]);
        User::factory()->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/deletion-requests');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($pending->email, $response->json('data.0.email'));
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

    private function orderWithItem(User $user, Product $product): Order
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'stock' => 10,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'status' => Order::STATUS_DELIVERED,
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
