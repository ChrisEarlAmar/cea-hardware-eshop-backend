<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\AccountPrice;
use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_requires_authentication(): void
    {
        $this->getJson('/api/cart')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_a_user_can_add_an_item_to_their_cart(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id,
            'quantity' => 3,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.sku', $variant->sku)
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.items.0.unit_price', '10.00')
            ->assertJsonPath('data.items.0.line_total', '30.00')
            ->assertJsonPath('data.total', '30.00');
    }

    public function test_adding_the_same_variant_twice_increases_its_quantity_rather_than_duplicating(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 2,
        ]);
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 3,
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));
        $this->assertSame(5, $response->json('data.items.0.quantity'));
    }

    public function test_a_user_can_update_a_cart_items_quantity(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00'], [50, '8.00']]);

        $add = $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 2,
        ]);
        $itemId = $add->json('data.items.0.item_id');

        $response = $this->actingAs($user, 'sanctum')->patchJson("/api/cart/items/{$itemId}", [
            'quantity' => 50,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.quantity', 50)
            ->assertJsonPath('data.items.0.unit_price', '8.00');
    }

    public function test_a_user_can_remove_a_cart_item(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $add = $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);
        $itemId = $add->json('data.items.0.item_id');

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/cart/items/{$itemId}");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.items'));
    }

    public function test_a_user_cannot_modify_another_users_cart_item(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $add = $this->actingAs($owner, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);
        $itemId = $add->json('data.items.0.item_id');

        $this->actingAs($intruder, 'sanctum')
            ->patchJson("/api/cart/items/{$itemId}", ['quantity' => 5])
            ->assertStatus(404);

        $this->actingAs($intruder, 'sanctum')
            ->deleteJson("/api/cart/items/{$itemId}")
            ->assertStatus(404);
    }

    public function test_an_account_members_negotiated_price_applies_in_the_cart(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $account = Account::create(['name' => 'Acme Co']);
        $user = User::factory()->create(['account_id' => $account->id, 'role' => 'owner']);

        AccountPrice::create([
            'account_id' => $account->id,
            'product_variant_id' => $variant->id,
            'unit_price' => '4.00',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.unit_price', '4.00')
            ->assertJsonPath('data.items.0.source', 'account_price');
    }

    // Checkout ("purchase the cart") moved to OrderTest as of Stage 7 - it
    // now creates a real Order rather than just decrementing stock, so its
    // tests live alongside the rest of the order/checkout behavior.

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
}
