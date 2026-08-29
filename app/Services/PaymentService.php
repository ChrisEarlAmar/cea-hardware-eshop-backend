<?php

namespace App\Services;

use App\Models\Order;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * The only class in the app that talks to Stripe's SDK directly. Kept
 * separate from OrderService/OrderController so both the "not configured
 * yet" guard and the actual API calls have one place to change, and so
 * tests can swap this whole class out for a fake instead of needing real
 * network access or real (even test-mode) API keys to run.
 */
class PaymentService
{
    private ?StripeClient $client = null;

    /**
     * False until the developer has pasted their own free Stripe test-mode
     * secret key into .env - there is no way to collect payment without it,
     * and callers should check this before ever reserving stock for an
     * order that could never be paid for.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    /**
     * @return array{id: string, client_secret: string}
     */
    public function createPaymentIntentFor(Order $order): array
    {
        $intent = $this->client()->paymentIntents->create([
            'amount' => $this->toCents($order->total),
            'currency' => 'usd',
            'metadata' => ['order_id' => (string) $order->id],
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        return [
            'id' => $intent->id,
            'client_secret' => $intent->client_secret,
        ];
    }

    /**
     * Verifies the webhook actually came from Stripe (HMAC signature over
     * the raw body) before anything in the payload is trusted. Throws
     * Stripe\Exception\SignatureVerificationException on a bad signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): Event
    {
        return Webhook::constructEvent($payload, $signature, (string) config('services.stripe.webhook_secret'));
    }

    /**
     * Re-fetches a PaymentIntent's client secret, so a customer reloading
     * the checkout page mid-payment can resume without a new order being
     * created - the secret itself is never persisted locally.
     */
    public function retrieveClientSecret(string $paymentIntentId): ?string
    {
        return $this->client()->paymentIntents->retrieve($paymentIntentId)->client_secret;
    }

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient((string) config('services.stripe.secret'));
    }

    private function toCents(string $decimal): int
    {
        return (int) round(((float) $decimal) * 100);
    }
}
