<?php

namespace Tests\Feature;

use App\Mail\AbandonedCartMail;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RemindAbandonedCartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stale_cart_with_items_gets_a_reminder(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        $variant = $this->variant();
        $item = $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);
        \Illuminate\Support\Facades\DB::table('cart_items')->where('id', $item->id)->update(['updated_at' => now()->subHours(3)]);

        $this->artisan('orders:remind-abandoned-carts')->assertSuccessful();

        Mail::assertQueued(AbandonedCartMail::class, fn ($mail) => $mail->cart->is($cart));
        $this->assertNotNull($cart->fresh()->abandoned_reminder_sent_at);
    }

    public function test_a_recently_touched_cart_is_not_reminded_yet(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        $cart->items()->create(['product_variant_id' => $this->variant()->id, 'quantity' => 1]);

        $this->artisan('orders:remind-abandoned-carts');

        Mail::assertNothingQueued();
    }

    public function test_an_empty_cart_is_never_reminded(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        Cart::create(['user_id' => $user->id, 'created_at' => now()->subHours(5), 'updated_at' => now()->subHours(5)]);

        $this->artisan('orders:remind-abandoned-carts');

        Mail::assertNothingQueued();
    }

    public function test_a_guest_cart_is_never_reminded(): void
    {
        Mail::fake();

        $cart = Cart::create(['guest_token' => 'guest-token-123']);
        $item = $cart->items()->create(['product_variant_id' => $this->variant()->id, 'quantity' => 1]);
        \Illuminate\Support\Facades\DB::table('cart_items')->where('id', $item->id)->update(['updated_at' => now()->subHours(3)]);

        $this->artisan('orders:remind-abandoned-carts');

        Mail::assertNothingQueued();
    }

    public function test_a_cart_already_reminded_is_not_reminded_again(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id, 'abandoned_reminder_sent_at' => now()->subDay()]);
        $item = $cart->items()->create(['product_variant_id' => $this->variant()->id, 'quantity' => 1]);
        \Illuminate\Support\Facades\DB::table('cart_items')->where('id', $item->id)->update(['updated_at' => now()->subHours(3)]);

        $this->artisan('orders:remind-abandoned-carts');

        Mail::assertNothingQueued();
    }

    public function test_adding_a_new_item_clears_a_previous_reminder_flag(): void
    {
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id, 'abandoned_reminder_sent_at' => now()->subDay()]);
        $variant = $this->variant();

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'variant_id' => $variant->id, 'quantity' => 1,
        ]);

        $this->assertNull($cart->fresh()->abandoned_reminder_sent_at);
    }

    private function variant(): ProductVariant
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Bolt', 'slug' => 'test-bolt-'.uniqid()]);

        return ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => 100]);
    }
}
