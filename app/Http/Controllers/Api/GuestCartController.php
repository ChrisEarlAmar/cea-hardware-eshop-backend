<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\PricingEngine;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The unauthenticated twin of CartController. A guest has no user_id to key
 * a cart by, so identity here is an opaque `X-Guest-Token` header instead:
 * the client generates nothing itself, it just echoes back whatever
 * `guest_token` the first response gave it. Deliberately a completely
 * separate controller rather than an optional-auth branch inside
 * CartController - the authenticated cart's every method assumes
 * $request->user() exists, and bolting a second identity path onto that
 * would risk both paths instead of adding one new, self-contained one.
 */
class GuestCartController extends Controller
{
    public function __construct(
        private readonly PricingEngine $pricingEngine,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->cartPayload($this->cartFor($request->header('X-Guest-Token'))));
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->cartFor($request->header('X-Guest-Token'));
        $item = $cart->items()->where('product_variant_id', $validated['variant_id'])->first();

        if ($item !== null) {
            $item->update(['quantity' => $item->quantity + $validated['quantity']]);
        } else {
            $cart->items()->create([
                'product_variant_id' => $validated['variant_id'],
                'quantity' => $validated['quantity'],
            ]);
        }

        return ApiResponse::success($this->cartPayload($cart));
    }

    public function updateItem(Request $request, CartItem $item): JsonResponse
    {
        $this->authorizeGuestItem($request, $item);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $item->update(['quantity' => $validated['quantity']]);

        return ApiResponse::success($this->cartPayload($item->cart));
    }

    public function removeItem(Request $request, CartItem $item): JsonResponse
    {
        $this->authorizeGuestItem($request, $item);

        $cart = $item->cart;
        $item->delete();

        return ApiResponse::success($this->cartPayload($cart));
    }

    private function authorizeGuestItem(Request $request, CartItem $item): void
    {
        $token = $request->header('X-Guest-Token');

        if (! $token || $item->cart->guest_token !== $token) {
            abort(404);
        }
    }

    private function cartFor(?string $guestToken): Cart
    {
        if ($guestToken !== null) {
            $cart = Cart::where('guest_token', $guestToken)->first();

            if ($cart !== null) {
                return $cart;
            }
        }

        return Cart::create(['guest_token' => (string) Str::uuid()]);
    }

    private function cartPayload(Cart $cart): array
    {
        $cart->load('items.variant');

        $lines = [];
        $totalCents = 0;

        // A guest never has an account, so pricing always falls through to
        // standard tiers - the same PricingEngine call an authenticated
        // account-less user would get, never account-override pricing.
        foreach ($cart->items as $item) {
            $priced = $this->pricingEngine->priceFor(null, $item->variant, $item->quantity);

            $lines[] = [
                'item_id' => $item->id,
                'variant_id' => $item->variant->id,
                'sku' => $item->variant->sku,
                'stock' => $item->variant->stock,
                ...$priced,
            ];

            $totalCents += $this->toCents($priced['line_total']);
        }

        return [
            'id' => $cart->id,
            'guest_token' => $cart->guest_token,
            'items' => $lines,
            'total' => number_format($totalCents / 100, 2, '.', ''),
        ];
    }

    private function toCents(string $decimal): int
    {
        return (int) round(((float) $decimal) * 100);
    }
}
