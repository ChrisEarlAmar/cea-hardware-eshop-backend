<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Services\PricingEngine;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly PricingEngine $pricingEngine,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->cartPayload($this->cartFor($request->user()), $request->user()));
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->cartFor($request->user());
        $item = $cart->items()->where('product_variant_id', $validated['variant_id'])->first();

        if ($item !== null) {
            $item->update(['quantity' => $item->quantity + $validated['quantity']]);
        } else {
            $cart->items()->create([
                'product_variant_id' => $validated['variant_id'],
                'quantity' => $validated['quantity'],
            ]);
        }

        return ApiResponse::success($this->cartPayload($cart, $request->user()));
    }

    public function updateItem(Request $request, CartItem $item): JsonResponse
    {
        if ($item->cart->user_id !== $request->user()->id) {
            abort(404);
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $item->update(['quantity' => $validated['quantity']]);

        return ApiResponse::success($this->cartPayload($item->cart, $request->user()));
    }

    public function removeItem(Request $request, CartItem $item): JsonResponse
    {
        if ($item->cart->user_id !== $request->user()->id) {
            abort(404);
        }

        $cart = $item->cart;
        $item->delete();

        return ApiResponse::success($this->cartPayload($cart, $request->user()));
    }

    private function cartFor(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    private function cartPayload(Cart $cart, User $user): array
    {
        $cart->load('items.variant');
        $account = $user->account;

        $lines = [];
        $totalCents = 0;

        foreach ($cart->items as $item) {
            $priced = $this->pricingEngine->priceFor($account, $item->variant, $item->quantity);

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
            'items' => $lines,
            'total' => number_format($totalCents / 100, 2, '.', ''),
        ];
    }

    private function toCents(string $decimal): int
    {
        return (int) round(((float) $decimal) * 100);
    }
}
