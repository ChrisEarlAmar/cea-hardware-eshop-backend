<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\PricingEngine;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PricingController extends Controller
{
    public function __construct(private readonly PricingEngine $pricingEngine)
    {
    }

    /**
     * Prices one or more (variant, quantity) lines. Works for guests too -
     * pricing quotes don't require an account, they just don't get an
     * account-specific override applied. Deliberately not behind
     * auth:sanctum for that reason; instead it opportunistically resolves
     * whoever the bearer token (if any) belongs to.
     */
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $user = Auth::guard('sanctum')->user();
        $account = $user?->account;

        $lines = [];
        $totalCents = 0;

        foreach ($validated['lines'] as $line) {
            $variant = ProductVariant::findOrFail($line['variant_id']);
            $priced = $this->pricingEngine->priceFor($account, $variant, $line['quantity']);

            $lines[] = [
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                ...$priced,
            ];

            $totalCents += $this->toCents($priced['line_total']);
        }

        return ApiResponse::success([
            'lines' => $lines,
            'total' => number_format($totalCents / 100, 2, '.', ''),
        ]);
    }

    private function toCents(string $decimal): int
    {
        return (int) round(((float) $decimal) * 100);
    }
}
