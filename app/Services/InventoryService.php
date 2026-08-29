<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Atomically decrements a variant's stock by $quantity, or throws
     * InsufficientStockException and changes nothing.
     *
     * This is a single conditional UPDATE - "subtract, but only if there's
     * enough" - not a SELECT to check stock followed by a separate UPDATE.
     * That matters because a SELECT-then-UPDATE has a gap in the middle
     * where a second request can read the same "stock is available" state
     * before the first request writes its decrement, and both then
     * proceed to oversell. A single UPDATE has no such gap: the database
     * evaluates the WHERE clause and applies the change as one indivisible
     * operation, and the row lock it takes for that instant forces any
     * concurrent UPDATE on the same row to wait its turn and then
     * re-evaluate WHERE against the now-current (already-decremented)
     * value - so the second one to actually run correctly sees reduced
     * stock and fails instead of both succeeding.
     */
    public function decrementStock(ProductVariant $variant, int $quantity): void
    {
        $affected = DB::table('product_variants')
            ->where('id', $variant->id)
            ->where('stock', '>=', $quantity)
            ->decrement('stock', $quantity);

        if ($affected === 0) {
            throw new InsufficientStockException($variant->fresh(), $quantity);
        }
    }
}
