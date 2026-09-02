<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountPrice;
use App\Models\PriceTier;
use App\Models\ProductVariant;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Public price-break display for a variant. Works for guests too, same
 * opportunistic-auth reasoning as PricingController::quote - a signed-in
 * B2B buyer who belongs to an account with a negotiated AccountPrice for
 * this variant additionally sees their own rate as `your_price`, but that
 * value must never leak to a guest or to a different account's user.
 */
class PriceTierController extends Controller
{
    public function index(ProductVariant $variant): JsonResponse
    {
        $tiers = $variant->priceTiers()
            ->orderBy('min_quantity')
            ->get(['min_quantity', 'unit_price']);

        $user = Auth::guard('sanctum')->user();
        $account = $user?->account;

        $yourPrice = null;

        if ($account !== null) {
            $accountPrice = AccountPrice::query()
                ->where('account_id', $account->id)
                ->where('product_variant_id', $variant->id)
                ->first();

            $yourPrice = $accountPrice?->unit_price;
        }

        return ApiResponse::success([
            'variant_id' => $variant->id,
            'tiers' => $tiers->map(fn (PriceTier $tier) => [
                'min_quantity' => $tier->min_quantity,
                'unit_price' => $tier->unit_price,
            ]),
            'your_price' => $yourPrice,
        ]);
    }
}
