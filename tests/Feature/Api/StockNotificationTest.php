<?php

namespace Tests\Feature\Api;

use App\Mail\BackInStockMail;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StockNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_sign_up_for_a_back_in_stock_notification(): void
    {
        $variant = $this->outOfStockVariant();

        $this->postJson("/api/variants/{$variant->id}/stock-notifications", [
            'email' => 'guest@example.com',
        ])->assertStatus(201);

        $this->assertDatabaseHas('stock_notifications', [
            'product_variant_id' => $variant->id, 'email' => 'guest@example.com', 'user_id' => null,
        ]);
    }

    public function test_a_signed_in_user_does_not_need_to_repeat_their_email(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        $variant = $this->outOfStockVariant();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/variants/{$variant->id}/stock-notifications", [])
            ->assertStatus(201);

        $this->assertDatabaseHas('stock_notifications', [
            'product_variant_id' => $variant->id, 'email' => 'me@example.com', 'user_id' => $user->id,
        ]);
    }

    public function test_signing_up_for_an_in_stock_item_is_rejected(): void
    {
        $variant = $this->variantWithStock(5);

        $this->postJson("/api/variants/{$variant->id}/stock-notifications", [
            'email' => 'guest@example.com',
        ])->assertStatus(422)->assertJsonPath('error.code', 'already_in_stock');
    }

    public function test_a_guest_must_supply_an_email(): void
    {
        $variant = $this->outOfStockVariant();

        $this->postJson("/api/variants/{$variant->id}/stock-notifications", [])
            ->assertStatus(422);
    }

    public function test_restocking_from_zero_emails_everyone_who_signed_up(): void
    {
        Mail::fake();

        $staff = User::factory()->create(['is_staff' => true]);
        $variant = $this->outOfStockVariant();
        StockNotification::create(['product_variant_id' => $variant->id, 'email' => 'a@example.com']);
        StockNotification::create(['product_variant_id' => $variant->id, 'email' => 'b@example.com']);

        $this->actingAs($staff, 'sanctum')->postJson("/api/admin/variants/{$variant->id}/stock-adjustments", [
            'delta' => 10, 'reason' => 'Restock',
        ])->assertStatus(201);

        Mail::assertQueued(BackInStockMail::class, 2);
        $this->assertSame(2, StockNotification::whereNotNull('notified_at')->count());
    }

    public function test_a_restock_that_never_hit_zero_does_not_notify(): void
    {
        Mail::fake();

        $staff = User::factory()->create(['is_staff' => true]);
        $variant = $this->variantWithStock(5);
        // Signing up isn't even possible while in stock, but simulate a
        // stale row (e.g. a race) to prove the trigger itself is the guard,
        // not just the signup endpoint's own check.
        StockNotification::create(['product_variant_id' => $variant->id, 'email' => 'a@example.com']);

        $this->actingAs($staff, 'sanctum')->postJson("/api/admin/variants/{$variant->id}/stock-adjustments", [
            'delta' => 10, 'reason' => 'Restock',
        ])->assertStatus(201);

        Mail::assertNothingQueued();
    }

    public function test_a_notified_signup_is_not_notified_again_on_a_later_restock(): void
    {
        Mail::fake();

        $staff = User::factory()->create(['is_staff' => true]);
        $variant = $this->outOfStockVariant();
        StockNotification::create([
            'product_variant_id' => $variant->id, 'email' => 'already@example.com', 'notified_at' => now(),
        ]);

        $this->actingAs($staff, 'sanctum')->postJson("/api/admin/variants/{$variant->id}/stock-adjustments", [
            'delta' => 10, 'reason' => 'Restock',
        ])->assertStatus(201);

        Mail::assertNothingQueued();
    }

    private function outOfStockVariant(): ProductVariant
    {
        return $this->variantWithStock(0);
    }

    private function variantWithStock(int $stock): ProductVariant
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Bolt', 'slug' => 'test-bolt-'.uniqid()]);

        return ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => $stock]);
    }
}
