<?php

use App\Http\Controllers\Api\Admin\AccountController as AdminAccountController;
use App\Http\Controllers\Api\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\Admin\DeletionRequestController as AdminDeletionRequestController;
use App\Http\Controllers\Api\Admin\GiftCardController as AdminGiftCardController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PriceTierController as AdminPriceTierController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ProductVariantController as AdminProductVariantController;
use App\Http\Controllers\Api\Admin\ReturnController as AdminReturnController;
use App\Http\Controllers\Api\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\Admin\RfqController as AdminRfqController;
use App\Http\Controllers\Api\Admin\ShipmentController;
use App\Http\Controllers\Api\Admin\ProductQuestionController as AdminProductQuestionController;
use App\Http\Controllers\Api\Admin\StockAdjustmentController as AdminStockAdjustmentController;
use App\Http\Controllers\Api\Admin\TaxRateController as AdminTaxRateController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AccountDataController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AlsoBoughtController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BulkCartController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CompanyOrderController;
use App\Http\Controllers\Api\GuestCartController;
use App\Http\Controllers\Api\GuestOrderController;
use App\Http\Controllers\Api\HomeFeedController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderApprovalController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderTrackingController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\PriceTierController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\ProductAnswerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductQuestionController;
use App\Http\Controllers\Api\RelatedProductController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\RfqController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\StockNotificationController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\WishlistController;
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
Route::get('/products/{product}/related', [RelatedProductController::class, 'index']);
Route::get('/products/{product}/also-bought', [AlsoBoughtController::class, 'index']);
Route::get('/products/{product:slug}/reviews', [ReviewController::class, 'index']);
Route::get('/products/{product}/questions', [ProductQuestionController::class, 'index']);
Route::get('/search', [SearchController::class, 'index']);
Route::get('/search/facets', [SearchController::class, 'facets']);
Route::get('/search/autocomplete', [SearchController::class, 'autocomplete']);

// Curated/derived listings for the homepage - featured picks, best sellers,
// new arrivals. See HomeFeedController.
Route::get('/home-feed', [HomeFeedController::class, 'index']);

// Pricing quotes work for guests too (just without an account override), so
// this is intentionally not behind auth:sanctum.
Route::post('/pricing/quote', [PricingController::class, 'quote']);

// Price-break display works for guests too (same reasoning as the pricing
// quote above); a signed-in B2B buyer additionally sees their own
// negotiated `your_price` for this variant - see PriceTierController.
Route::get('/variants/{variant}/price-tiers', [PriceTierController::class, 'index']);

// Guests can ask to be notified too - optional auth, the same pattern
// PricingController already established.
Route::post('/variants/{variant}/stock-notifications', [StockNotificationController::class, 'store']);

// A guest checkout: identity is the X-Guest-Token header, not a Sanctum
// token, so none of this needs auth:sanctum. See GuestCartController /
// GuestOrderController for why these are separate controllers rather than
// branches inside the authenticated ones.
Route::get('/guest/cart', [GuestCartController::class, 'show']);
Route::post('/guest/cart/items', [GuestCartController::class, 'addItem']);
Route::patch('/guest/cart/items/{item}', [GuestCartController::class, 'updateItem']);
Route::delete('/guest/cart/items/{item}', [GuestCartController::class, 'removeItem']);
Route::post('/guest/orders', [GuestOrderController::class, 'store']);
Route::get('/guest/orders/{order}', [GuestOrderController::class, 'show']);

// General "track my order" lookup by id + email - unlike the guest-only
// endpoint above, this also matches a signed-in customer's own order
// against their account email, so it works regardless of how the order was
// placed. See OrderTrackingController.
Route::get('/track-order/{order}', [OrderTrackingController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return ApiResponse::success($request->user()->load('account'));
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/accounts', [AccountController::class, 'store']);
    Route::post('/accounts/{account}/join', [AccountController::class, 'join']);
    Route::get('/account/users', [AccountController::class, 'members']);
    Route::delete('/account/users/{targetUser}', [AccountController::class, 'removeMember']);
    Route::patch('/account/users/{targetUser}/spending-limit', [AccountController::class, 'updateMemberSpendingLimit']);

    // Self-service data export/deletion request - see AccountDataController.
    Route::get('/account/export', [AccountDataController::class, 'export']);
    Route::post('/account/deletion-request', [AccountDataController::class, 'requestDeletion']);
    Route::post('/account/deletion-request/cancel', [AccountDataController::class, 'cancelDeletion']);

    // B2B: every order placed by any member of the requester's own account,
    // not just their own - see CompanyOrderController.
    Route::get('/account/orders', [CompanyOrderController::class, 'index']);

    // A personal address, or (if the user belongs to one) their account's
    // shared address book - see AddressController.
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::patch('/addresses/{address}', [AddressController::class, 'update']);
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);

    // Cart is a signed-in-user resource - one per user. A visitor who isn't
    // signed in uses the parallel /guest/cart routes above instead.
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::patch('/cart/items/{item}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{item}', [CartController::class, 'removeItem']);

    // B2B bulk ordering: paste/upload sku,quantity lines straight into the
    // cart - see BulkCartController (a separate controller from CartController
    // above, not an extra method on it).
    Route::post('/cart/bulk-import', [BulkCartController::class, 'import']);

    // Orders: POST checks out the current cart. There's no separate staff
    // role in this project's scope, so the order's own owner is who's
    // allowed to drive it through the state machine below - the backend
    // enforces which transitions are legal regardless of who's asking.
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{order}/start-payment', [OrderController::class, 'startPayment']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::get('/orders/{order}/history', [OrderController::class, 'history']);
    Route::get('/orders/{order}/invoice', [InvoiceController::class, 'show']);

    // B2B: an account owner reviewing orders members placed over their
    // spending limit. See OrderApprovalController.
    Route::get('/order-approvals', [OrderApprovalController::class, 'index']);
    Route::post('/order-approvals/{approval}/approve', [OrderApprovalController::class, 'approve']);
    Route::post('/order-approvals/{approval}/reject', [OrderApprovalController::class, 'reject']);

    // Returns/RMA - see ReturnController for the customer-facing half and
    // Admin\ReturnController (below) for staff decisions.
    Route::post('/order-items/{orderItem}/return', [ReturnController::class, 'store']);
    Route::get('/returns', [ReturnController::class, 'index']);

    // Product reviews - public reads live above; only submitting one
    // requires a signed-in, verified-purchase requester.
    Route::post('/products/{product:slug}/reviews', [ReviewController::class, 'store']);

    // Product Q&A - unmoderated, unlike reviews (see ProductQuestionController).
    Route::post('/products/{product}/questions', [ProductQuestionController::class, 'store']);
    Route::post('/questions/{question}/answers', [ProductAnswerController::class, 'store']);

    // Saved cards via Stripe - see PaymentService's Customer/SetupIntent
    // methods and PaymentMethodController.
    Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
    Route::post('/payment-methods', [PaymentMethodController::class, 'store']);
    Route::delete('/payment-methods/{paymentMethodId}', [PaymentMethodController::class, 'destroy']);

    // A product a signed-in user has saved for later - see WishlistController.
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/{product}', [WishlistController::class, 'toggle']);

    // Request-for-quote - see RfqController for the customer-facing half
    // and Admin\RfqController (below) for staff follow-up.
    Route::get('/rfq', [RfqController::class, 'index']);
    Route::post('/rfq', [RfqController::class, 'store']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    // Internal staff surface: deliberately cross-account, unlike every
    // route above. Gated by the `staff` middleware, not resource ownership.
    Route::middleware('staff')->prefix('admin')->group(function () {
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
        Route::patch('/orders/{order}/tracking', [ShipmentController::class, 'update']);

        Route::get('/accounts', [AdminAccountController::class, 'index']);
        Route::get('/accounts/{account}/users', [AdminAccountController::class, 'users']);

        Route::get('/deletion-requests', [AdminDeletionRequestController::class, 'index']);

        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::patch('/categories/{category}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);

        Route::get('/products', [AdminProductController::class, 'index']);
        Route::get('/products/{product}', [AdminProductController::class, 'show']);
        Route::post('/products', [AdminProductController::class, 'store']);
        Route::patch('/products/{product}', [AdminProductController::class, 'update']);
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);

        Route::post('/products/{product}/variants', [AdminProductVariantController::class, 'store']);
        Route::patch('/variants/{variant}', [AdminProductVariantController::class, 'update']);
        Route::delete('/variants/{variant}', [AdminProductVariantController::class, 'destroy']);

        Route::post('/variants/{variant}/price-tiers', [AdminPriceTierController::class, 'store']);
        Route::patch('/price-tiers/{priceTier}', [AdminPriceTierController::class, 'update']);
        Route::delete('/price-tiers/{priceTier}', [AdminPriceTierController::class, 'destroy']);

        Route::get('/variants/{variant}/stock-adjustments', [AdminStockAdjustmentController::class, 'index']);
        Route::post('/variants/{variant}/stock-adjustments', [AdminStockAdjustmentController::class, 'store']);

        Route::get('/analytics/overview', [AdminAnalyticsController::class, 'overview']);
        Route::get('/analytics/export', [AdminAnalyticsController::class, 'exportCsv']);

        Route::get('/audit-log', [AdminAuditLogController::class, 'index']);

        Route::get('/coupons', [AdminCouponController::class, 'index']);
        Route::post('/coupons', [AdminCouponController::class, 'store']);
        Route::patch('/coupons/{coupon}', [AdminCouponController::class, 'update']);
        Route::delete('/coupons/{coupon}', [AdminCouponController::class, 'destroy']);

        Route::get('/gift-cards', [AdminGiftCardController::class, 'index']);
        Route::post('/gift-cards', [AdminGiftCardController::class, 'store']);
        Route::patch('/gift-cards/{giftCard}', [AdminGiftCardController::class, 'update']);
        Route::delete('/gift-cards/{giftCard}', [AdminGiftCardController::class, 'destroy']);

        Route::get('/tax-rates', [AdminTaxRateController::class, 'index']);
        Route::post('/tax-rates', [AdminTaxRateController::class, 'store']);
        Route::patch('/tax-rates/{taxRate}', [AdminTaxRateController::class, 'update']);
        Route::delete('/tax-rates/{taxRate}', [AdminTaxRateController::class, 'destroy']);

        Route::get('/reviews', [AdminReviewController::class, 'index']);
        Route::post('/reviews/{review}/approve', [AdminReviewController::class, 'approve']);
        Route::post('/reviews/{review}/reject', [AdminReviewController::class, 'reject']);

        Route::get('/returns', [AdminReturnController::class, 'index']);
        Route::post('/returns/{returnRequest}/approve', [AdminReturnController::class, 'approve']);
        Route::post('/returns/{returnRequest}/reject', [AdminReturnController::class, 'reject']);
        Route::post('/returns/{returnRequest}/refund', [AdminReturnController::class, 'refund']);

        Route::get('/rfq', [AdminRfqController::class, 'index']);
        Route::post('/rfq/{rfq}/respond', [AdminRfqController::class, 'respond']);

        Route::delete('/questions/{question}', [AdminProductQuestionController::class, 'destroyQuestion']);
        Route::delete('/answers/{answer}', [AdminProductQuestionController::class, 'destroyAnswer']);
    });
});
