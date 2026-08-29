<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProcessedWebhookEvent;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

/**
 * Confirms payment for an order once Stripe actually clears it. This is the
 * only path that can move an order from awaiting_payment to pending - the
 * customer-facing status-update endpoint's validation whitelist doesn't
 * include either of those values, so a customer can never fake this
 * themselves.
 *
 * Run `stripe listen --forward-to localhost:8000/api/stripe/webhook`
 * locally (free Stripe CLI) to exercise this without any public URL.
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderService $orderService,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('Stripe-Signature', '');

        try {
            $event = $this->paymentService->verifyWebhookSignature($request->getContent(), $signature);
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            Log::warning('Rejected Stripe webhook with an invalid signature.', ['message' => $e->getMessage()]);

            return ApiResponse::error('invalid_signature', 'Webhook signature verification failed.', 400);
        }

        // Stripe delivers at-least-once, not exactly-once - a slow response
        // or a timeout makes it retry the same event later. The unique
        // constraint on event_id, not application logic, is what makes
        // that safe to receive twice.
        try {
            ProcessedWebhookEvent::create([
                'event_id' => $event->id,
                'event_type' => $event->type,
            ]);
        } catch (UniqueConstraintViolationException) {
            return ApiResponse::success(['status' => 'already_processed']);
        }

        if ($event->type === 'payment_intent.succeeded') {
            $this->handlePaymentSucceeded($event->data->object->id);
        }

        return ApiResponse::success(['status' => 'ok']);
    }

    private function handlePaymentSucceeded(string $paymentIntentId): void
    {
        $order = Order::where('payment_intent_id', $paymentIntentId)->first();

        if ($order === null) {
            Log::warning('Stripe payment_intent.succeeded for an unknown order.', ['payment_intent_id' => $paymentIntentId]);

            return;
        }

        if (! $order->canTransitionTo(Order::STATUS_PENDING)) {
            // Already confirmed (a retried webhook) or in some other state
            // entirely - either way, there's nothing to do.
            return;
        }

        $order->update(['payment_status' => Order::PAYMENT_PAID]);
        $this->orderService->transitionTo($order, Order::STATUS_PENDING, null, 'Payment confirmed via Stripe webhook.');
    }
}
