<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Category;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_regular_customer_is_forbidden_from_the_admin_surface(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/orders')
            ->assertStatus(403);
    }

    public function test_an_unauthenticated_request_is_rejected_before_the_staff_check(): void
    {
        $this->getJson('/api/admin/orders')->assertStatus(401);
    }

    public function test_staff_can_see_orders_across_every_account(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);

        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $this->orderFor($customerA);
        $this->orderFor($customerB);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/orders');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_staff_can_filter_admin_orders_by_status(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $customer = User::factory()->create();
        $this->orderFor($customer);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/orders?status=awaiting_payment');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));

        $noneResponse = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/orders?status=delivered');
        $this->assertCount(0, $noneResponse->json('data'));
    }

    public function test_staff_can_list_accounts_and_their_members(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $account = Account::create(['name' => 'Acme Fasteners']);
        $owner = User::factory()->create(['account_id' => $account->id, 'role' => 'owner']);

        $accountsResponse = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/accounts');
        $accountsResponse->assertStatus(200)
            ->assertJsonFragment(['name' => 'Acme Fasteners', 'member_count' => 1]);

        $usersResponse = $this->actingAs($staff, 'sanctum')->getJson("/api/admin/accounts/{$account->id}/users");
        $usersResponse->assertStatus(200)
            ->assertJsonFragment(['email' => $owner->email]);
    }

    private function orderFor(User $user): Order
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Bolt', 'slug' => 'test-bolt-'.uniqid()]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => 10]);
        PriceTier::create(['product_variant_id' => $variant->id, 'min_quantity' => 1, 'unit_price' => '10.00']);

        return Order::create([
            'user_id' => $user->id,
            'status' => Order::STATUS_AWAITING_PAYMENT,
            'total' => '10.00',
        ]);
    }
}
