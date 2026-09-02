<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * B2B bulk cart import: lets a buyer paste/upload a list of SKU + quantity
 * lines instead of adding items to the cart one at a time. Deliberately a
 * new, separate controller rather than a method on CartController - see
 * this task's isolation notes - even though the "find the existing cart
 * item, increment it, else create one" logic below duplicates a few lines
 * of CartController::addItem. That duplication is the intended tradeoff for
 * keeping this slice independent of a file other work is touching.
 *
 * Request shape: this project's own choice between the two the task brief
 * allowed. The body is `{ "lines": [{ "sku": "...", "quantity": n }, ...] }`
 * - a JSON object wrapping the array, not a bare top-level array - so a
 * malformed or missing `lines` key gets one clear top-level validation
 * error, while each line's own problems (bad SKU, bad quantity) are still
 * resolved by hand per line below rather than by the validator, so one bad
 * line never fails the whole batch.
 */
class BulkCartController extends Controller
{
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
        ]);

        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

        $added = [];
        $errors = [];

        foreach ($validated['lines'] as $index => $line) {
            $lineNumber = $index + 1;
            $sku = is_array($line) ? trim((string) ($line['sku'] ?? '')) : '';
            $quantityRaw = is_array($line) ? ($line['quantity'] ?? null) : null;

            if ($sku === '') {
                $errors[] = ['line' => $lineNumber, 'sku' => $sku, 'message' => 'A SKU is required.'];

                continue;
            }

            if (! is_numeric($quantityRaw) || (int) $quantityRaw < 1) {
                $errors[] = ['line' => $lineNumber, 'sku' => $sku, 'message' => 'Quantity must be a whole number of at least 1.'];

                continue;
            }

            $variant = ProductVariant::where('sku', $sku)->first();

            if ($variant === null) {
                $errors[] = ['line' => $lineNumber, 'sku' => $sku, 'message' => 'No product found for this SKU.'];

                continue;
            }

            $quantity = (int) $quantityRaw;

            // Same "find existing cart item, increment, else create" logic
            // as CartController::addItem, duplicated deliberately - see the
            // class doc comment above.
            $item = $cart->items()->where('product_variant_id', $variant->id)->first();

            if ($item !== null) {
                $item->update(['quantity' => $item->quantity + $quantity]);
            } else {
                $item = $cart->items()->create([
                    'product_variant_id' => $variant->id,
                    'quantity' => $quantity,
                ]);
            }

            $added[] = [
                'line' => $lineNumber,
                'sku' => $variant->sku,
                'quantity_added' => $quantity,
                'cart_quantity' => $item->quantity,
            ];
        }

        return ApiResponse::success(['added' => $added, 'errors' => $errors]);
    }
}
