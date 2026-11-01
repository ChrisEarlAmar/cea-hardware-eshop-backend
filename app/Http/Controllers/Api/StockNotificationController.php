<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\StockNotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Notify me when this is back in stock" - open to guests as well as
 * signed-in users, the same optional-auth pattern PricingController already
 * established: a signed-in user's own email is used automatically, a guest
 * supplies one directly.
 */
class StockNotificationController extends Controller
{
    public function __construct(
        private readonly StockNotificationService $stockNotificationService,
    ) {
    }

    public function store(Request $request, ProductVariant $variant): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        $validated = $request->validate([
            'email' => $user ? ['nullable', 'email'] : ['required', 'email'],
        ]);

        if ($variant->stock > 0) {
            return ApiResponse::error('already_in_stock', 'This item is already in stock.', 422);
        }

        $email = $validated['email'] ?? $user?->email;

        $this->stockNotificationService->subscribe($variant, $email, $user?->id);

        return ApiResponse::success(['message' => "We'll email you when this is back in stock."], status: 201);
    }
}
