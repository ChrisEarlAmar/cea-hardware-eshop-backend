<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The concurrency proof this stage exists for. A note on how it's
     * tested: true OS-level concurrency (two requests genuinely executing
     * at the same instant) isn't reliably reproducible against this
     * project's local setup - SQLite locks the whole database file per
     * write rather than per row, its :memory: mode isn't shared between
     * separate connections, and Windows has no pcntl for forking a second
     * process. Firing two real simultaneous HTTP requests at `php artisan
     * serve` wouldn't prove anything either, since that dev server
     * processes one request at a time regardless of what the database
     * layer does.
     *
     * What actually makes the atomic UPDATE safe is that it re-checks
     * "is there enough stock" against whatever is currently committed at
     * the instant it runs, rather than trusting a value read earlier. This
     * test proves exactly that: call the decrement twice in sequence for
     * the last unit - which is precisely what happens to the *second*
     * transaction in a real race, however the database scheduled the two
     * to run - and confirm the first succeeds, the second is correctly
     * rejected, and stock lands at exactly 0, never negative.
     */
    public function test_two_purchase_attempts_for_the_last_unit_result_in_exactly_one_success(): void
    {
        $variant = $this->variantWithStock(1);
        $service = app(InventoryService::class);

        $service->decrementStock($variant, 1);

        $secondAttemptFailed = false;
        try {
            $service->decrementStock($variant, 1);
        } catch (InsufficientStockException) {
            $secondAttemptFailed = true;
        }

        $this->assertTrue($secondAttemptFailed, 'The second purchase attempt should have failed.');
        $this->assertSame(0, $variant->fresh()->stock);
    }

    public function test_a_successful_decrement_reduces_stock_by_exactly_the_requested_amount(): void
    {
        $variant = $this->variantWithStock(10);

        app(InventoryService::class)->decrementStock($variant, 4);

        $this->assertSame(6, $variant->fresh()->stock);
    }

    public function test_decrementing_more_than_available_stock_fails_and_leaves_stock_unchanged(): void
    {
        $variant = $this->variantWithStock(5);

        $this->expectException(InsufficientStockException::class);

        try {
            app(InventoryService::class)->decrementStock($variant, 10);
        } finally {
            $this->assertSame(5, $variant->fresh()->stock);
        }
    }

    private function variantWithStock(int $stock): ProductVariant
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Bolt', 'slug' => 'test-bolt-'.uniqid()]);

        return ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => $stock]);
    }
}
