<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_regular_customer_is_forbidden_from_adjusting_stock(): void
    {
        $user = User::factory()->create();
        $variant = $this->variant();

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/variants/{$variant->id}/stock-adjustments", [
            'delta' => 5,
            'reason' => 'Restock',
        ])->assertStatus(403);
    }

    public function test_a_positive_delta_increases_stock_and_records_history(): void
    {
        $staff = $this->staff();
        $variant = $this->variant(stock: 10);

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/admin/variants/{$variant->id}/stock-adjustments", [
            'delta' => 15,
            'reason' => 'Restock from supplier',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.delta', 15)
            ->assertJsonPath('data.resulting_stock', 25)
            ->assertJsonPath('data.reason', 'Restock from supplier')
            ->assertJsonPath('data.actor.id', $staff->id);

        $this->assertSame(25, $variant->fresh()->stock);

        $this->assertDatabaseHas('stock_adjustments', [
            'product_variant_id' => $variant->id,
            'delta' => 15,
            'reason' => 'Restock from supplier',
            'actor_id' => $staff->id,
            'resulting_stock' => 25,
        ]);
    }

    public function test_a_negative_delta_decreases_stock_and_records_history(): void
    {
        $staff = $this->staff();
        $variant = $this->variant(stock: 10);

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/admin/variants/{$variant->id}/stock-adjustments", [
            'delta' => -4,
            'reason' => 'Damaged in warehouse',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.delta', -4)
            ->assertJsonPath('data.resulting_stock', 6);

        $this->assertSame(6, $variant->fresh()->stock);
    }

    public function test_a_negative_delta_that_would_go_below_zero_is_rejected_with_no_change(): void
    {
        $staff = $this->staff();
        $variant = $this->variant(stock: 5);

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/admin/variants/{$variant->id}/stock-adjustments", [
            'delta' => -6,
            'reason' => 'Shrinkage',
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'insufficient_stock');

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertDatabaseCount('stock_adjustments', 0);
    }

    public function test_an_exact_zeroing_out_delta_is_allowed(): void
    {
        $staff = $this->staff();
        $variant = $this->variant(stock: 5);

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/admin/variants/{$variant->id}/stock-adjustments", [
            'delta' => -5,
            'reason' => 'Full write-off',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.resulting_stock', 0);
        $this->assertSame(0, $variant->fresh()->stock);
    }

    public function test_reason_and_delta_are_required(): void
    {
        $staff = $this->staff();
        $variant = $this->variant();

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/admin/variants/{$variant->id}/stock-adjustments", [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.fields.delta.0', 'The delta field is required.')
            ->assertJsonPath('error.fields.reason.0', 'The reason field is required.');
    }

    public function test_the_adjustment_history_index_lists_adjustments_newest_first(): void
    {
        $staff = $this->staff();
        $variant = $this->variant(stock: 10);

        $this->actingAs($staff, 'sanctum')->postJson("/api/admin/variants/{$variant->id}/stock-adjustments", [
            'delta' => 5,
            'reason' => 'First restock',
        ])->assertStatus(201);

        $this->actingAs($staff, 'sanctum')->postJson("/api/admin/variants/{$variant->id}/stock-adjustments", [
            'delta' => -2,
            'reason' => 'Damaged unit',
        ])->assertStatus(201);

        $response = $this->actingAs($staff, 'sanctum')->getJson("/api/admin/variants/{$variant->id}/stock-adjustments");

        $response->assertStatus(200);
        $reasons = collect($response->json('data'))->pluck('reason')->all();
        $this->assertSame(['Damaged unit', 'First restock'], $reasons);

        $resultingStocks = collect($response->json('data'))->pluck('resulting_stock')->all();
        $this->assertSame([13, 15], $resultingStocks);
    }

    private function staff(): User
    {
        return User::factory()->create(['is_staff' => true]);
    }

    private function variant(int $stock = 10): ProductVariant
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt-'.uniqid()]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => $stock]);
        PriceTier::create(['product_variant_id' => $variant->id, 'min_quantity' => 1, 'unit_price' => '10.00']);

        return $variant;
    }
}
