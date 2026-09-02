<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Staff-only management of a product's purchasable variants (SKU + raw
 * stock). This is a direct edit, not a ledger entry - use
 * StockAdjustmentController instead when the goal is a tracked stock
 * correction with a reason and audit history. Nested under a product for
 * `store`; `update`/`destroy` take the variant directly since it already
 * uniquely identifies its parent product. Reachable only via the `staff`
 * middleware (see routes/api.php).
 */
class ProductVariantController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100', 'unique:product_variants,sku'],
            'stock' => ['sometimes', 'integer', 'min:0'],
        ]);

        $variant = $product->variants()->create([
            'sku' => $validated['sku'],
            'stock' => $validated['stock'] ?? 0,
        ]);

        $this->auditLogger->log($request->user(), 'product_variant.create', $variant, [
            'product_id' => $product->id,
            'sku' => $variant->sku,
        ]);

        return ApiResponse::success($this->payload($variant), status: 201);
    }

    public function update(Request $request, ProductVariant $variant): JsonResponse
    {
        $validated = $request->validate([
            'sku' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('product_variants', 'sku')->ignore($variant->id),
            ],
            'stock' => ['sometimes', 'integer', 'min:0'],
        ]);

        $variant->update($validated);

        $this->auditLogger->log($request->user(), 'product_variant.update', $variant, $validated);

        return ApiResponse::success($this->payload($variant->fresh()));
    }

    /**
     * Refused with a 422 if this variant already appears on a real order -
     * see App\Http\Controllers\Api\Admin\ProductController::destroy for
     * why (order_items.product_variant_id is a restrict, not cascade,
     * foreign key).
     */
    public function destroy(Request $request, ProductVariant $variant): JsonResponse
    {
        if (DB::table('order_items')->where('product_variant_id', $variant->id)->exists()) {
            return ApiResponse::error(
                'variant_has_orders',
                'This variant appears in existing orders and cannot be deleted.',
                422,
            );
        }

        $this->auditLogger->log($request->user(), 'product_variant.delete', $variant, ['sku' => $variant->sku]);

        $variant->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    private function payload(ProductVariant $variant): array
    {
        $variant->loadMissing('priceTiers');

        return [
            'id' => $variant->id,
            'product_id' => $variant->product_id,
            'sku' => $variant->sku,
            'stock' => $variant->stock,
            'price_tiers' => $variant->priceTiers->map(fn ($tier) => [
                'id' => $tier->id,
                'min_quantity' => $tier->min_quantity,
                'unit_price' => $tier->unit_price,
            ])->values(),
            'created_at' => $variant->created_at->toIso8601String(),
        ];
    }
}
