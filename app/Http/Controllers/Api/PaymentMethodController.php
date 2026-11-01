<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saved cards for a signed-in user, backed by a Stripe Customer (see
 * PaymentService). Actually saving a card is a client-side Stripe.js step:
 * store() only starts that flow by handing back a SetupIntent's
 * client_secret for the frontend to confirm - this app never sees raw card
 * details, matching how the checkout PaymentIntent flow already works.
 */
class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->paymentService->listPaymentMethods($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->paymentService->isConfigured()) {
            return ApiResponse::error(
                'payment_not_configured',
                'Payment processing is not configured on this server yet.',
                500,
            );
        }

        $intent = $this->paymentService->createSetupIntentFor($request->user());

        return ApiResponse::success($intent, status: 201);
    }

    /**
     * Detaches a saved card. Ownership isn't something a local row can
     * check - cards live entirely in Stripe - so this confirms the id
     * actually appears in the requesting user's own Stripe customer's card
     * list before detaching anything, and 404s (this codebase's convention
     * for a failed ownership check) rather than 403 when it doesn't.
     */
    public function destroy(Request $request, string $paymentMethodId): JsonResponse
    {
        $owned = collect($this->paymentService->listPaymentMethods($request->user()))
            ->contains(fn (array $method) => $method['id'] === $paymentMethodId);

        if (! $owned) {
            abort(404);
        }

        $this->paymentService->detachPaymentMethod($paymentMethodId);

        return ApiResponse::success(['detached' => true]);
    }
}
