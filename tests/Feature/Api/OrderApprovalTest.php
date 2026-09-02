<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderApproval;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_order_over_the_members_spending_limit_is_held_for_approval(): void
    {
        $this->fakeSuccessfulPayments();

        [$owner, $member] = $this->accountWithOwnerAndMember(spendingLimitCents: 5000);
        $variant = $this->variantWithTiers([[1, '100.00']], stock: 10);

        $this->actingAs($member, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);

        $response = $this->checkout($member);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending_approval');
        $this->assertArrayNotHasKey('client_secret', $response->json('data'));

        // Stock is still reserved even though payment hasn't started.
        $this->assertSame(9, $variant->fresh()->stock);

        $order = Order::first();
        $this->assertNotNull($order->approval);
        $this->assertSame(OrderApproval::STATUS_PENDING, $order->approval->status);
    }

    public function test_an_order_within_the_limit_checks_out_normally(): void
    {
        $this->fakeSuccessfulPayments();

        [$owner, $member] = $this->accountWithOwnerAndMember(spendingLimitCents: 100000);
        $variant = $this->variantWithTiers([[1, '100.00']], stock: 10);

        $this->actingAs($member, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);

        $this->checkout($member)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.client_secret', 'pi_test_secret_abc');
    }

    public function test_only_the_account_owner_can_see_or_decide_pending_approvals(): void
    {
        $this->fakeSuccessfulPayments();

        [$owner, $member] = $this->accountWithOwnerAndMember(spendingLimitCents: 5000);
        $variant = $this->variantWithTiers([[1, '100.00']], stock: 10);
        $this->actingAs($member, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->checkout($member);

        $approval = OrderApproval::first();

        $this->actingAs($member, 'sanctum')->getJson('/api/order-approvals')->assertStatus(403);
        $this->actingAs($member, 'sanctum')->postJson("/api/order-approvals/{$approval->id}/approve")->assertStatus(403);

        $this->actingAs($owner, 'sanctum')->getJson('/api/order-approvals')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_owner_approving_moves_the_order_to_awaiting_payment_without_starting_payment(): void
    {
        $this->fakeSuccessfulPayments();

        [$owner, $member] = $this->accountWithOwnerAndMember(spendingLimitCents: 5000);
        $variant = $this->variantWithTiers([[1, '100.00']], stock: 10);
        $this->actingAs($member, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);
        $orderId = $this->checkout($member)->json('data.id');
        $approval = OrderApproval::first();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/order-approvals/{$approval->id}/approve")
            ->assertStatus(200);

        $order = Order::find($orderId);
        $this->assertSame(Order::STATUS_AWAITING_PAYMENT, $order->status);
        $this->assertNull($order->payment_intent_id);

        // The buyer, not the approver, starts payment when they're ready.
        $this->actingAs($member, 'sanctum')
            ->postJson("/api/orders/{$orderId}/start-payment")
            ->assertStatus(200)
            ->assertJsonPath('data.client_secret', 'pi_test_secret_abc');

        $this->assertSame(Order::PAYMENT_REQUIRES_PAYMENT, $order->fresh()->payment_status);
    }

    public function test_owner_rejecting_cancels_the_order_and_restores_stock(): void
    {
        $this->fakeSuccessfulPayments();

        [$owner, $member] = $this->accountWithOwnerAndMember(spendingLimitCents: 5000);
        $variant = $this->variantWithTiers([[1, '100.00']], stock: 10);
        $this->actingAs($member, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);
        $orderId = $this->checkout($member)->json('data.id');
        $approval = OrderApproval::first();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/order-approvals/{$approval->id}/reject")
            ->assertStatus(200);

        $this->assertSame(Order::STATUS_CANCELLED, Order::find($orderId)->status);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_a_decided_approval_cannot_be_decided_again(): void
    {
        $this->fakeSuccessfulPayments();

        [$owner, $member] = $this->accountWithOwnerAndMember(spendingLimitCents: 5000);
        $variant = $this->variantWithTiers([[1, '100.00']], stock: 10);
        $this->actingAs($member, 'sanctum')->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->checkout($member);
        $approval = OrderApproval::first();

        $this->actingAs($owner, 'sanctum')->postJson("/api/order-approvals/{$approval->id}/approve")->assertStatus(200);
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/order-approvals/{$approval->id}/reject")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'already_decided');
    }

    public function test_owner_can_set_a_members_spending_limit_but_not_their_own(): void
    {
        [$owner, $member] = $this->accountWithOwnerAndMember(spendingLimitCents: null);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/account/users/{$member->id}/spending-limit", ['spending_limit_cents' => 2500])
            ->assertStatus(200)
            ->assertJsonPath('data.spending_limit_cents', 2500);

        $this->assertSame(2500, $member->fresh()->spending_limit_cents);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/account/users/{$owner->id}/spending-limit", ['spending_limit_cents' => 100])
            ->assertStatus(422);

        $this->actingAs($member, 'sanctum')
            ->patchJson("/api/account/users/{$owner->id}/spending-limit", ['spending_limit_cents' => 100])
            ->assertStatus(403);
    }

    /**
     * @return array{0: User, 1: User} owner, member
     */
    private function accountWithOwnerAndMember(?int $spendingLimitCents): array
    {
        $account = Account::create(['name' => 'Acme Co']);
        $owner = User::factory()->create(['account_id' => $account->id, 'role' => 'owner']);
        $member = User::factory()->create([
            'account_id' => $account->id,
            'role' => 'member',
            'spending_limit_cents' => $spendingLimitCents,
        ]);

        return [$owner, $member];
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
