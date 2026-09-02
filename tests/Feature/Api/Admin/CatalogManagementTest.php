<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    // --- staff-only guard -------------------------------------------------

    public function test_a_regular_customer_is_forbidden_from_admin_categories(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/categories')
            ->assertStatus(403);
    }

    public function test_an_unauthenticated_request_is_rejected_before_the_staff_check(): void
    {
        $this->getJson('/api/admin/categories')->assertStatus(401);
    }

    // --- categories ---------------------------------------------------

    public function test_staff_can_list_categories_with_product_counts(): void
    {
        $staff = $this->staff();
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/categories');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Bolts', 'product_count' => 1]);
    }

    public function test_staff_can_create_a_category_with_an_auto_generated_slug(): void
    {
        $staff = $this->staff();

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/admin/categories', [
            'name' => 'Hand Tools',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Hand Tools')
            ->assertJsonPath('data.slug', 'hand-tools');

        $this->assertDatabaseHas('categories', ['name' => 'Hand Tools', 'slug' => 'hand-tools']);
    }

    public function test_staff_can_update_a_category(): void
    {
        $staff = $this->staff();
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);

        $response = $this->actingAs($staff, 'sanctum')->patchJson("/api/admin/categories/{$category->id}", [
            'name' => 'Fasteners',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.name', 'Fasteners');
        $this->assertSame('Fasteners', $category->fresh()->name);
    }

    public function test_a_category_slug_must_be_unique(): void
    {
        $staff = $this->staff();
        Category::create(['name' => 'Bolts', 'slug' => 'bolts']);

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/admin/categories', [
            'name' => 'Other Bolts',
            'slug' => 'bolts',
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_a_category_with_products_cannot_be_deleted(): void
    {
        $staff = $this->staff();
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);

        $response = $this->actingAs($staff, 'sanctum')->deleteJson("/api/admin/categories/{$category->id}");

        $response->assertStatus(422)->assertJsonPath('error.code', 'category_has_products');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_an_empty_category_can_be_deleted(): void
    {
        $staff = $this->staff();
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);

        $response = $this->actingAs($staff, 'sanctum')->deleteJson("/api/admin/categories/{$category->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    // --- products -------------------------------------------------------

    public function test_staff_can_list_products_with_category_and_variant_count(): void
    {
        $staff = $this->staff();
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'HEX-1', 'stock' => 10]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/products');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Hex Bolt', 'variant_count' => 1])
            ->assertJsonPath('data.0.category.slug', 'bolts');
    }

    public function test_staff_can_create_a_product(): void
    {
        $staff = $this->staff();
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'name' => 'Carriage Bolt',
            'description' => 'A sturdy bolt.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'carriage-bolt')
            ->assertJsonPath('data.category.id', $category->id);

        $this->assertDatabaseHas('products', ['name' => 'Carriage Bolt', 'slug' => 'carriage-bolt']);
    }

    public function test_creating_a_product_requires_a_valid_category(): void
    {
        $staff = $this->staff();

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/admin/products', [
            'category_id' => 999,
            'name' => 'Carriage Bolt',
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_staff_can_update_a_product(): void
    {
        $staff = $this->staff();
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);

        $response = $this->actingAs($staff, 'sanctum')->patchJson("/api/admin/products/{$product->id}", [
            'name' => 'Hex Bolt Deluxe',
            'image_url' => '/images/hex-bolt.png',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.name', 'Hex Bolt Deluxe');
        $this->assertSame('/images/hex-bolt.png', $product->fresh()->image_url);
    }

    public function test_staff_can_view_a_single_product_with_its_variants_and_price_tiers(): void
    {
        $staff = $this->staff();
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'HEX-1', 'stock' => 10]);
        PriceTier::create(['product_variant_id' => $variant->id, 'min_quantity' => 1, 'unit_price' => '5.00']);

        $response = $this->actingAs($staff, 'sanctum')->getJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.variants.0.sku', 'HEX-1')
            ->assertJsonPath('data.variants.0.price_tiers.0.unit_price', '5.00');
    }

    public function test_a_product_with_variants_that_have_orders_cannot_be_deleted(): void
    {
        $staff = $this->staff();
        [$product, $variant] = $this->productWithVariant();
        $this->placeOrderFor($variant);

        $response = $this->actingAs($staff, 'sanctum')->deleteJson("/api/admin/products/{$product->id}");

        $response->assertStatus(422)->assertJsonPath('error.code', 'product_has_orders');
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_a_product_without_orders_can_be_deleted(): void
    {
        $staff = $this->staff();
        [$product] = $this->productWithVariant();

        $response = $this->actingAs($staff, 'sanctum')->deleteJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    // --- variants ---------------------------------------------------------

    public function test_staff_can_add_a_variant_to_a_product(): void
    {
        $staff = $this->staff();
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/admin/products/{$product->id}/variants", [
            'sku' => 'HEX-1',
            'stock' => 25,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.sku', 'HEX-1');
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'sku' => 'HEX-1', 'stock' => 25]);
    }

    public function test_a_variant_sku_must_be_unique(): void
    {
        $staff = $this->staff();
        [$product, $variant] = $this->productWithVariant();

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/admin/products/{$product->id}/variants", [
            'sku' => $variant->sku,
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_staff_can_update_a_variants_sku_and_stock(): void
    {
        $staff = $this->staff();
        [, $variant] = $this->productWithVariant();

        $response = $this->actingAs($staff, 'sanctum')->patchJson("/api/admin/variants/{$variant->id}", [
            'stock' => 42,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.stock', 42);
        $this->assertSame(42, $variant->fresh()->stock);
    }

    public function test_a_variant_with_orders_cannot_be_deleted(): void
    {
        $staff = $this->staff();
        [, $variant] = $this->productWithVariant();
        $this->placeOrderFor($variant);

        $response = $this->actingAs($staff, 'sanctum')->deleteJson("/api/admin/variants/{$variant->id}");

        $response->assertStatus(422)->assertJsonPath('error.code', 'variant_has_orders');
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id]);
    }

    public function test_a_variant_without_orders_can_be_deleted(): void
    {
        $staff = $this->staff();
        [, $variant] = $this->productWithVariant();

        $response = $this->actingAs($staff, 'sanctum')->deleteJson("/api/admin/variants/{$variant->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
    }

    // --- price tiers --------------------------------------------------

    public function test_staff_can_manage_a_variants_price_tiers(): void
    {
        $staff = $this->staff();
        [, $variant] = $this->productWithVariant();

        $createResponse = $this->actingAs($staff, 'sanctum')->postJson("/api/admin/variants/{$variant->id}/price-tiers", [
            'min_quantity' => 10,
            'unit_price' => '4.50',
        ]);
        $createResponse->assertStatus(201)->assertJsonPath('data.min_quantity', 10);
        $tierId = $createResponse->json('data.id');

        $updateResponse = $this->actingAs($staff, 'sanctum')->patchJson("/api/admin/price-tiers/{$tierId}", [
            'unit_price' => '4.25',
        ]);
        $updateResponse->assertStatus(200)->assertJsonPath('data.unit_price', '4.25');

        $deleteResponse = $this->actingAs($staff, 'sanctum')->deleteJson("/api/admin/price-tiers/{$tierId}");
        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('price_tiers', ['id' => $tierId]);
    }

    public function test_a_price_tiers_min_quantity_must_be_unique_per_variant(): void
    {
        $staff = $this->staff();
        // productWithVariant() already seeds a min_quantity=1 tier for this variant.
        [, $variant] = $this->productWithVariant();

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/admin/variants/{$variant->id}/price-tiers", [
            'min_quantity' => 1,
            'unit_price' => '6.00',
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }

    // --- helpers ------------------------------------------------------

    private function staff(): User
    {
        return User::factory()->create(['is_staff' => true]);
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function productWithVariant(): array
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt-'.uniqid()]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => 10]);
        PriceTier::create(['product_variant_id' => $variant->id, 'min_quantity' => 1, 'unit_price' => '10.00']);

        return [$product, $variant];
    }

    private function placeOrderFor(ProductVariant $variant): Order
    {
        $customer = User::factory()->create();

        $order = Order::create([
            'user_id' => $customer->id,
            'status' => Order::STATUS_AWAITING_PAYMENT,
            'total' => '10.00',
        ]);

        DB::table('order_items')->insert([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => '10.00',
            'line_total' => '10.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $order;
    }
}
