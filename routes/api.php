<?php

use App\Http\Controllers\Api\Admin\AccountController as AdminAccountController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return ApiResponse::success([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::post('/echo', function (Request $request) {
    $validated = $request->validate([
        'message' => ['required', 'string', 'max:255'],
    ]);

    return ApiResponse::success([
        'message' => $validated['message'],
    ]);
});

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:login');
Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:login');

// Stripe calls this directly, server-to-server - no Sanctum token exists
// for it to send. Trust is instead established per-request by verifying
// the HMAC signature over the raw body (see StripeWebhookController).
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

// Catalog browsing is public - no reason to require an account just to look.
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category:slug}/products', [CategoryController::class, 'products']);
Route::get('/products/{product:slug}', [ProductController::class, 'show']);
Route::get('/search', [SearchController::class, 'index']);

// Pricing quotes work for guests too (just without an account override), so
// this is intentionally not behind auth:sanctum.
Route::post('/pricing/quote', [PricingController::class, 'quote']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return ApiResponse::success($request->user()->load('account'));
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/accounts', [AccountController::class, 'store']);
    Route::post('/accounts/{account}/join', [AccountController::class, 'join']);
    Route::get('/account/users', [AccountController::class, 'members']);
    Route::delete('/account/users/{targetUser}', [AccountController::class, 'removeMember']);

    // Cart is a signed-in-user resource - one per user, no guest carts.
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::patch('/cart/items/{item}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{item}', [CartController::class, 'removeItem']);

    // Orders: POST checks out the current cart. There's no separate staff
    // role in this project's scope, so the order's own owner is who's
    // allowed to drive it through the state machine below - the backend
    // enforces which transitions are legal regardless of who's asking.
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::get('/orders/{order}/history', [OrderController::class, 'history']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    // Internal staff surface: deliberately cross-account, unlike every
    // route above. Gated by the `staff` middleware, not resource ownership.
    Route::middleware('staff')->prefix('admin')->group(function () {
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/accounts', [AdminAccountController::class, 'index']);
        Route::get('/accounts/{account}/users', [AdminAccountController::class, 'users']);
    });
});
