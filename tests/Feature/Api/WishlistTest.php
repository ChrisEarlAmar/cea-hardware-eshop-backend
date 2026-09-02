<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggling_a_wishlist_item_requires_authentication(): void
    {
        $product = $this->product();

        $this->postJson("/api/wishlist/{$product->id}")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_toggling_adds_the_product_when_it_is_not_yet_wishlisted(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/wishlist/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.wishlisted', true);

        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_toggling_twice_adds_then_removes_the_product(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $this->actingAs($user, 'sanctum')->postJson("/api/wishlist/{$product->id}")
            ->assertJsonPath('data.wishlisted', true);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/wishlist/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.wishlisted', false);

        $this->assertDatabaseMissing('wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_index_only_lists_the_signed_in_users_own_wishlisted_products(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedProduct = $this->product('Hex Bolt', 'hex-bolt');
        $otherProduct = $this->product('Ball Bearing', 'ball-bearing');

        $this->actingAs($owner, 'sanctum')->postJson("/api/wishlist/{$ownedProduct->id}");
        $this->actingAs($otherUser, 'sanctum')->postJson("/api/wishlist/{$otherProduct->id}");

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/wishlist');

        $response->assertStatus(200);
        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertSame(['hex-bolt'], $slugs);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/wishlist')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    private function product(string $name = 'Hex Bolt', ?string $slug = null): Product
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug ?? 'product-'.uniqid(),
        ]);
    }
}
