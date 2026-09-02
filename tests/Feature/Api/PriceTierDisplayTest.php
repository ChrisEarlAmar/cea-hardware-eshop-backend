<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\PriceTierController;
use App\Models\Account;
use App\Models\AccountPrice;
use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PriceTierDisplayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * routes/api.php is being edited concurrently by other feature slices
     * of this project, so this test registers its own route at runtime
     * rather than relying on that shared file. The exact line this
     * mirrors is listed under "## ROUTES NEEDED" in this task's report.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get('/api/variants/{variant}/price-tiers', [PriceTierController::class, 'index']);
    }

    public function test_tiers_are_returned_ordered_by_min_quantity(): void
    {
        // Deliberately created out of order to prove the endpoint sorts
        // rather than just returning insertion order.
        $variant = $this->variantWithTiers([[50, '8.00'], [1, '10.00'], [10, '9.00']]);

        $response = $this->getJson("/api/variants/{$variant->id}/price-tiers");

        $response->assertStatus(200);
        $this->assertSame(
            [1, 10, 50],
            collect($response->json('data.tiers'))->pluck('min_quantity')->all()
        );
    }

    public function test_a_guest_sees_tiers_but_no_your_price(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $response = $this->getJson("/api/variants/{$variant->id}/price-tiers");

        $response->assertStatus(200)
            ->assertJsonPath('data.your_price', null);
        $this->assertNotEmpty($response->json('data.tiers'));
    }

    public function test_a_signed_in_user_with_a_negotiated_price_sees_your_price(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $account = Account::create(['name' => 'Acme Co']);
        $user = User::factory()->create(['account_id' => $account->id]);

        AccountPrice::create([
            'account_id' => $account->id,
            'product_variant_id' => $variant->id,
            'unit_price' => '6.50',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/variants/{$variant->id}/price-tiers");

        $response->assertStatus(200)
            ->assertJsonPath('data.your_price', '6.50');
    }

    public function test_a_signed_in_user_without_a_negotiated_price_sees_no_your_price(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);
        $account = Account::create(['name' => 'Acme Co']);
        $user = User::factory()->create(['account_id' => $account->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/variants/{$variant->id}/price-tiers");

        $response->assertStatus(200)
            ->assertJsonPath('data.your_price', null);
    }

    public function test_one_accounts_negotiated_price_never_appears_for_a_different_accounts_user(): void
    {
        $variant = $this->variantWithTiers([[1, '10.00']]);

        $ownAccount = Account::create(['name' => 'Acme Co']);
        $otherAccount = Account::create(['name' => 'Other Co']);
        $otherUser = User::factory()->create(['account_id' => $otherAccount->id]);

        AccountPrice::create([
            'account_id' => $ownAccount->id,
            'product_variant_id' => $variant->id,
            'unit_price' => '2.00',
        ]);

        $response = $this->actingAs($otherUser, 'sanctum')->getJson("/api/variants/{$variant->id}/price-tiers");

        $response->assertStatus(200)
            ->assertJsonPath('data.your_price', null);
    }

    /**
     * @param  array<int, array{0: int, 1: string}>  $tiers
     */
    private function variantWithTiers(array $tiers): ProductVariant
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Bolt', 'slug' => 'test-bolt-'.uniqid()]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => 1000]);

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
