<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_orders_require_authentication(): void
    {
        $this->getJson('/api/account/orders')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_a_user_with_no_account_gets_a_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/account/orders')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'no_account');
    }

    public function test_a_member_sees_orders_placed_by_other_members_of_the_same_account(): void
    {
        $this->fakeSuccessfulPayments();

        $account = Account::create(['name' => 'Acme Hardware']);
        $owner = User::factory()->create(['account_id' => $account->id, 'role' => 'owner']);
        $member = User::factory()->create(['account_id' => $account->id, 'role' => 'member']);

        $variant = $this->variantWithTiers();
        $this->actingAs($owner, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);
        $order = $this->checkout($owner);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/account/orders');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($order->json('data.id'), $ids);
        $this->assertSame($owner->id, $response->json('data.0.placed_by.id'));
    }

    public function test_orders_from_a_different_account_are_not_visible(): void
    {
        $this->fakeSuccessfulPayments();

        $accountA = Account::create(['name' => 'Account A']);
        $accountB = Account::create(['name' => 'Account B']);
        $userA = User::factory()->create(['account_id' => $accountA->id, 'role' => 'owner']);
        $userB = User::factory()->create(['account_id' => $accountB->id, 'role' => 'owner']);

        $variant = $this->variantWithTiers();
        $this->actingAs($userB, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);
        $orderB = $this->checkout($userB);

        $response = $this->actingAs($userA, 'sanctum')->getJson('/api/account/orders');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($orderB->json('data.id'), $ids);
    }

    public function test_results_are_paginated_with_meta(): void
    {
        $account = Account::create(['name' => 'Acme Hardware']);
        $owner = User::factory()->create(['account_id' => $account->id, 'role' => 'owner']);

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/account/orders');

        $response->assertStatus(200)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.total', 0);
    }

    private function variantWithTiers(int $stock = 1000): ProductVariant
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Bolt', 'slug' => 'test-bolt-'.uniqid()]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => $stock]);

        PriceTier::create(['product_variant_id' => $variant->id, 'min_quantity' => 1, 'unit_price' => '10.00']);

        return $variant;
    }

    private function fakeSuccessfulPayments(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('createPaymentIntentFor')->andReturn([
                'id' => 'pi_test_123',
                'client_secret' => 'pi_test_secret_abc',
            ]);
            $mock->shouldReceive('retrieveClientSecret')->andReturn('pi_test_secret_abc');
        });
    }

    private function checkout(User $user)
    {
        return $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/orders');
    }
}
