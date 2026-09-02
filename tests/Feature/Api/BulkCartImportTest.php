<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkCartImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_import_requires_authentication(): void
    {
        $this->postJson('/api/cart/bulk-import', ['lines' => []])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_at_least_one_line_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/cart/bulk-import', ['lines' => []])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_a_valid_line_is_added_to_the_cart(): void
    {
        $user = User::factory()->create();
        $this->variant('SKU-BOLT-1');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/cart/bulk-import', [
            'lines' => [
                ['sku' => 'SKU-BOLT-1', 'quantity' => 3],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.added')
            ->assertJsonCount(0, 'data.errors')
            ->assertJsonPath('data.added.0.sku', 'SKU-BOLT-1')
            ->assertJsonPath('data.added.0.quantity_added', 3)
            ->assertJsonPath('data.added.0.cart_quantity', 3);

        $cart = $this->actingAs($user, 'sanctum')->getJson('/api/cart');
        $this->assertSame(3, $cart->json('data.items.0.quantity'));
    }

    public function test_a_repeated_sku_across_lines_increments_the_existing_cart_line_rather_than_duplicating(): void
    {
        $user = User::factory()->create();
        $this->variant('SKU-BOLT-1');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/cart/bulk-import', [
            'lines' => [
                ['sku' => 'SKU-BOLT-1', 'quantity' => 2],
                ['sku' => 'SKU-BOLT-1', 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.added')
            ->assertJsonPath('data.added.1.cart_quantity', 7);

        $cart = $this->actingAs($user, 'sanctum')->getJson('/api/cart');
        $this->assertCount(1, $cart->json('data.items'));
        $this->assertSame(7, $cart->json('data.items.0.quantity'));
    }

    public function test_an_unknown_sku_produces_a_per_line_error_without_failing_the_whole_batch(): void
    {
        $user = User::factory()->create();
        $this->variant('SKU-BOLT-1');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/cart/bulk-import', [
            'lines' => [
                ['sku' => 'SKU-BOLT-1', 'quantity' => 1],
                ['sku' => 'DOES-NOT-EXIST', 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.added')
            ->assertJsonCount(1, 'data.errors')
            ->assertJsonPath('data.errors.0.sku', 'DOES-NOT-EXIST');

        $cart = $this->actingAs($user, 'sanctum')->getJson('/api/cart');
        $this->assertCount(1, $cart->json('data.items'));
    }

    public function test_a_quantity_below_one_produces_a_per_line_error(): void
    {
        $user = User::factory()->create();
        $this->variant('SKU-BOLT-1');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/cart/bulk-import', [
            'lines' => [
                ['sku' => 'SKU-BOLT-1', 'quantity' => 0],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.added')
            ->assertJsonCount(1, 'data.errors');
    }

    public function test_a_non_numeric_quantity_produces_a_per_line_error(): void
    {
        $user = User::factory()->create();
        $this->variant('SKU-BOLT-1');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/cart/bulk-import', [
            'lines' => [
                ['sku' => 'SKU-BOLT-1', 'quantity' => 'a lot'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.added')
            ->assertJsonCount(1, 'data.errors');
    }

    public function test_a_missing_sku_produces_a_per_line_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/cart/bulk-import', [
            'lines' => [
                ['quantity' => 2],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.added')
            ->assertJsonCount(1, 'data.errors');
    }

    private function variant(string $sku, int $stock = 100): ProductVariant
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Bolt', 'slug' => 'test-bolt-'.uniqid()]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => $sku, 'stock' => $stock]);

        PriceTier::create(['product_variant_id' => $variant->id, 'min_quantity' => 1, 'unit_price' => '10.00']);

        return $variant;
    }
}
