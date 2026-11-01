<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
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

    /**
     * Returns the user's Stripe Customer id, creating one on first use.
     * Everything about saved cards is scoped to this customer object rather
     * than to bare PaymentMethod ids, which is also what lets
     * listPaymentMethods/detachPaymentMethod prove a card actually belongs
     * to the requesting user instead of just any valid Stripe id.
     *
     * Assigned via a direct property set + save() rather than
     * $user->update() - stripe_customer_id is deliberately not in User's
     * $fillable list (that model is owned by another part of this round),
     * so mass assignment would silently drop it.
     */
    public function getOrCreateStripeCustomer(User $user): string
    {
        if ($user->stripe_customer_id !== null) {
            return $user->stripe_customer_id;
        }

        $customer = $this->client()->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => ['user_id' => (string) $user->id],
        ]);

        $user->stripe_customer_id = $customer->id;
        $user->save();

        return $customer->id;
    }

    /**
     * A SetupIntent collects and validates a card without charging it -
     * the frontend confirms it with Stripe.js (see
     * PaymentMethodController::store), and the resulting PaymentMethod is
     * attached to this customer by Stripe itself as part of that
     * confirmation, so nothing here needs to record it separately.
     *
     * @return array{id: string, client_secret: string}
     */
    public function createSetupIntentFor(User $user): array
    {
        $intent = $this->client()->setupIntents->create([
            'customer' => $this->getOrCreateStripeCustomer($user),
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        return [
            'id' => $intent->id,
            'client_secret' => $intent->client_secret,
        ];
    }

    /**
     * The user's saved cards, read straight from Stripe rather than mirrored
     * locally - this app never stores card details itself. A user who has
     * never saved a card (no stripe_customer_id yet) has none, without
     * needing a Stripe call to find that out.
     *
     * @return list<array{id: string, brand: ?string, last4: ?string, exp_month: ?int, exp_year: ?int}>
     */
    public function listPaymentMethods(User $user): array
    {
        if ($user->stripe_customer_id === null) {
            return [];
        }

        $methods = $this->client()->customers->allPaymentMethods(
            $user->stripe_customer_id,
            ['type' => 'card'],
        );

        return collect($methods->data)
            ->map(fn ($method) => [
                'id' => $method->id,
                'brand' => $method->card->brand ?? null,
                'last4' => $method->card->last4 ?? null,
                'exp_month' => $method->card->exp_month ?? null,
                'exp_year' => $method->card->exp_year ?? null,
            ])
            ->all();
    }

    /**
     * Detaches (unsaves) a card. Deliberately takes a bare id with no
     * ownership check of its own - PaymentMethodController::destroy proves
     * ownership first (via listPaymentMethods, which is scoped to the
     * requesting user's Stripe customer) before ever calling this.
     */
    public function detachPaymentMethod(string $paymentMethodId): void
    {
        $this->client()->paymentMethods->detach($paymentMethodId);
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
