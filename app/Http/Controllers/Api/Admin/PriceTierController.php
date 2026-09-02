<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PriceTier;
use App\Models\ProductVariant;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Staff-only management of a variant's quantity price tiers (cliff
 * pricing - see the create_price_tiers_table migration). Nested under a
 * variant for `store`; `update`/`destroy` take the tier directly since it
 * already uniquely identifies its parent variant. Reachable only via the
 * `staff` middleware (see routes/api.php).
 */
class PriceTierController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function store(Request $request, ProductVariant $variant): JsonResponse
    {
        $validated = $request->validate([
            'min_quantity' => [
                'required', 'integer', 'min:1',
                Rule::unique('price_tiers', 'min_quantity')->where('product_variant_id', $variant->id),
            ],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $tier = $variant->priceTiers()->create($validated);

        $this->auditLogger->log($request->user(), 'price_tier.create', $tier, [
            'product_variant_id' => $variant->id,
        ]);

        return ApiResponse::success($this->payload($tier), status: 201);
    }

    public function update(Request $request, PriceTier $priceTier): JsonResponse
    {
        $validated = $request->validate([
            'min_quantity' => [
                'sometimes', 'integer', 'min:1',
                Rule::unique('price_tiers', 'min_quantity')
                    ->where('product_variant_id', $priceTier->product_variant_id)
                    ->ignore($priceTier->id),
            ],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $priceTier->update($validated);

        $this->auditLogger->log($request->user(), 'price_tier.update', $priceTier, $validated);

        return ApiResponse::success($this->payload($priceTier->fresh()));
    }

    public function destroy(Request $request, PriceTier $priceTier): JsonResponse
    {
        $this->auditLogger->log($request->user(), 'price_tier.delete', $priceTier, [
            'product_variant_id' => $priceTier->product_variant_id,
        ]);

        $priceTier->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    private function payload(PriceTier $tier): array
    {
        return [
            'id' => $tier->id,
            'product_variant_id' => $tier->product_variant_id,
            'min_quantity' => $tier->min_quantity,
            'unit_price' => $tier->unit_price,
        ];
    }
}
