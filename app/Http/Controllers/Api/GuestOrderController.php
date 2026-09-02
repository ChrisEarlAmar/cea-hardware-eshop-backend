<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\PaymentStartFailedException;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * The unauthenticated twin of OrderController. A guest has no saved
 * addresses and no account, so this collects a one-off shipping address
 * inline instead of referencing one by id, and identifies the buyer's cart
 * by the same X-Guest-Token header GuestCartController uses rather than
 * $request->user(). Everything that doesn't depend on that identity
 * difference - stock reservation, pricing, payment start, idempotency -
 * flows through the exact same OrderService the authenticated path uses.
 */
class GuestOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
    ) {
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $email = (string) $request->query('email');

        if ($order->user_id !== null || $email === '' || strcasecmp($order->guest_email ?? '', $email) !== 0) {
            abort(404);
        }

        return ApiResponse::success($this->orderPayload($order->load('items'), includePaymentSecret: true));
    }

    public function store(Request $request): JsonResponse
    {
        $guestToken = $request->header('X-Guest-Token');

        if (! $guestToken) {
            return ApiResponse::error(
                'guest_token_required',
                'An X-Guest-Token header is required to check out as a guest.',
                400,
            );
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return ApiResponse::error(
                'idempotency_key_required',
                'An Idempotency-Key header is required to check out.',
                400,
            );
        }

        $validated = $request->validate([
            'guest_email' => ['required', 'email'],
            'guest_name' => ['required', 'string', 'max:255'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.recipient_name' => ['required', 'string', 'max:255'],
            'shipping_address.line1' => ['required', 'string', 'max:255'],
            'shipping_address.line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:255'],
            'shipping_address.state' => ['required', 'string', 'max:255'],
            'shipping_address.postal_code' => ['required', 'string', 'max:20'],
            'shipping_address.country' => ['nullable', 'string', 'size:2'],
            'shipping_address.phone' => ['nullable', 'string', 'max:30'],
        ]);

        $cart = Cart::where('guest_token', $guestToken)->first();

        if ($cart === null) {
            return ApiResponse::error('empty_cart', 'Your cart is empty.', 422);
        }

        $cart->load('items.variant');

        $fingerprint = $this->fingerprintRequest($cart, $validated);

        $existing = IdempotencyKey::where('key', $idempotencyKey)->where('guest_token', $guestToken)->first();

        if ($existing !== null) {
            if ($cart->items->isNotEmpty() && $existing->request_fingerprint !== $fingerprint) {
                return ApiResponse::error(
                    'idempotency_key_reused',
                    'This Idempotency-Key was already used for a different request.',
                    409,
                );
            }

            if ($existing->response_status === null) {
                return ApiResponse::error(
                    'request_in_progress',
                    'This checkout request is already being processed. Please retry shortly.',
                    409,
                );
            }

            return response()->json($existing->response_body, $existing->response_status);
        }

        if ($cart->items->isEmpty()) {
            return ApiResponse::error('empty_cart', 'Your cart is empty.', 422);
        }

        if (! $this->paymentService->isConfigured()) {
            return ApiResponse::error(
                'payment_not_configured',
                'Payment processing is not configured on this server yet.',
                500,
            );
        }

        try {
            $idempotencyRecord = IdempotencyKey::create([
                'key' => $idempotencyKey,
                'guest_token' => $guestToken,
                'request_fingerprint' => $fingerprint,
            ]);
        } catch (UniqueConstraintViolationException) {
            return ApiResponse::error(
                'request_in_progress',
                'This checkout request is already being processed. Please retry shortly.',
                409,
            );
        }

        [$status, $body] = $this->checkout($cart, $validated);

        $idempotencyRecord->update(['response_status' => $status, 'response_body' => $body]);

        return response()->json($body, $status);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: int, 1: array}
     */
    private function checkout(Cart $cart, array $validated): array
    {
        $shipping = $validated['shipping_address'];

        // One ad-hoc address, used for both shipping and billing - a guest
        // never gets a "bill somewhere else" option, which keeps this flow
        // to a single form. Not tied to any user or account, so it never
        // shows up in anyone's saved-address book.
        $address = Address::create([
            'user_id' => null,
            'account_id' => null,
            'label' => 'Guest checkout',
            'recipient_name' => $shipping['recipient_name'],
            'line1' => $shipping['line1'],
            'line2' => $shipping['line2'] ?? null,
            'city' => $shipping['city'],
            'state' => $shipping['state'],
            'postal_code' => $shipping['postal_code'],
            'country' => $shipping['country'] ?? 'US',
            'phone' => $shipping['phone'] ?? null,
            'is_default' => false,
        ]);

        $extras = [
            'guest_email' => $validated['guest_email'],
            'guest_name' => $validated['guest_name'],
            'customer_note' => $validated['customer_note'] ?? null,
            'coupon_code' => $validated['coupon_code'] ?? null,
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
        ];

        try {
            $order = $this->orderService->createFromCart($cart, $extras);
        } catch (InsufficientStockException $e) {
            return [422, ['error' => ['code' => 'insufficient_stock', 'message' => $e->getMessage()]]];
        } catch (InvalidArgumentException $e) {
            return [422, ['error' => ['code' => 'invalid_checkout', 'message' => $e->getMessage()]]];
        }

        // A guest never has a User row, so createFromCart can never route
        // this into STATUS_PENDING_APPROVAL - that path only ever applies
        // to a signed-in B2B member with a spending limit.
        try {
            $intent = $this->orderService->startPayment($order);
        } catch (PaymentStartFailedException $e) {
            return [502, ['error' => ['code' => 'payment_intent_failed', 'message' => $e->getMessage()]]];
        }

        return [201, ['data' => [
            ...$this->orderPayload($order->fresh('items')),
            'client_secret' => $intent['client_secret'],
        ]]];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fingerprintRequest(Cart $cart, array $validated): string
    {
        $lines = $cart->items
            ->map(fn ($item) => "{$item->product_variant_id}:{$item->quantity}")
            ->sort()
            ->values()
            ->implode('|');

        $shipping = $validated['shipping_address'];

        // Built as an explicit, fixed-order array rather than hashing
        // $validated directly - JSON key order from the client is not
        // something a retry is guaranteed to preserve, and this fingerprint
        // must be stable across two byte-different but semantically
        // identical requests.
        $meaningful = [
            'guest_email' => $validated['guest_email'],
            'guest_name' => $validated['guest_name'],
            'customer_note' => $validated['customer_note'] ?? null,
            'coupon_code' => $validated['coupon_code'] ?? null,
            'shipping_address' => [
                'recipient_name' => $shipping['recipient_name'],
                'line1' => $shipping['line1'],
                'line2' => $shipping['line2'] ?? null,
                'city' => $shipping['city'],
                'state' => $shipping['state'],
                'postal_code' => $shipping['postal_code'],
                'country' => $shipping['country'] ?? 'US',
                'phone' => $shipping['phone'] ?? null,
            ],
        ];

        return hash('sha256', $lines.'::'.json_encode($meaningful));
    }

    private function orderPayload(Order $order, bool $includePaymentSecret = false): array
    {
        $payload = [
            'id' => $order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total' => $order->total,
            'discount_total' => $order->discount_total,
            'guest_email' => $order->guest_email,
            'guest_name' => $order->guest_name,
            'tracking_carrier' => $order->tracking_carrier,
            'tracking_number' => $order->tracking_number,
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ]),
            'created_at' => $order->created_at->toIso8601String(),
        ];

        if ($includePaymentSecret
            && $order->payment_status === Order::PAYMENT_REQUIRES_PAYMENT
            && $order->payment_intent_id !== null
            && $this->paymentService->isConfigured()
        ) {
            $payload['client_secret'] = $this->paymentService->retrieveClientSecret($order->payment_intent_id);
        }

        return $payload;
    }
}
