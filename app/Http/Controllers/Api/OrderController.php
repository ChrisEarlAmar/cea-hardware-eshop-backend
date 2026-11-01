<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\PaymentStartFailedException;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()->with('items')->latest()->get();

        return ApiResponse::success($orders->map(fn (Order $order) => $this->orderPayload($order)));
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        return ApiResponse::success($this->orderPayload($order->load('items'), includePaymentSecret: true));
    }

    /**
     * Checkout: turns the current user's cart into an order and starts
     * collecting payment for it. The order is created (and stock reserved)
     * before payment is confirmed - see OrderService::createFromCart for
     * why - but if Stripe rejects the request to even start collecting
     * payment, that reservation is released immediately rather than left
     * stuck against an order nobody can ever pay for.
     *
     * Requires an Idempotency-Key header: a checkout retried after a
     * network blip or a double-click must return the exact same order
     * rather than creating a second one. This is deliberately checked
     * before touching the cart at all.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return ApiResponse::error(
                'idempotency_key_required',
                'An Idempotency-Key header is required to check out.',
                400,
            );
        }

        $validated = $request->validate([
            'shipping_address_id' => ['nullable', 'integer'],
            'billing_address_id' => ['nullable', 'integer'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'po_reference' => ['nullable', 'string', 'max:100'],
            'gift_card_code' => ['nullable', 'string', 'max:64'],
            'redeem_loyalty_points' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $extras = [
                'shipping_address_id' => $this->resolveOwnedAddressId($user, $validated['shipping_address_id'] ?? null),
                'billing_address_id' => $this->resolveOwnedAddressId($user, $validated['billing_address_id'] ?? null),
                'customer_note' => $validated['customer_note'] ?? null,
                'po_reference' => $validated['po_reference'] ?? null,
                'coupon_code' => $validated['coupon_code'] ?? null,
                'gift_card_code' => $validated['gift_card_code'] ?? null,
                'redeem_loyalty_points' => $validated['redeem_loyalty_points'] ?? null,
            ];
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error('invalid_address', $e->getMessage(), 422);
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $cart->load('items.variant');

        $fingerprint = $this->fingerprintRequest($cart, $extras);

        $existing = IdempotencyKey::where('key', $idempotencyKey)->where('user_id', $user->id)->first();

        if ($existing !== null) {
            // A successful first attempt clears the cart - so a retry
            // (network blip, client never saw the response) legitimately
            // arrives with an empty cart. Only compare fingerprints when
            // there's still something in the cart to compare against;
            // otherwise there's no way to tell "expected empty cart after
            // success" apart from "this key was reused for nothing."
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
                'user_id' => $user->id,
                'request_fingerprint' => $fingerprint,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same key won the race to
            // claim it between our lookup above and this insert.
            return ApiResponse::error(
                'request_in_progress',
                'This checkout request is already being processed. Please retry shortly.',
                409,
            );
        }

        [$status, $body] = $this->checkout($cart, $user, $extras);

        $idempotencyRecord->update(['response_status' => $status, 'response_body' => $body]);

        return response()->json($body, $status);
    }

    /**
     * Starts payment for an order that was held in STATUS_PENDING_APPROVAL
     * and has since been approved by the account owner - the B2B mirror of
     * the payment step normal checkout starts automatically. Deliberately a
     * separate, buyer-initiated call rather than something the approval
     * itself triggers: the client_secret it returns is only useful to the
     * browser session that's about to enter card details, which is never
     * the approving owner's request.
     */
    public function startPayment(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        if ($order->status !== Order::STATUS_AWAITING_PAYMENT || $order->payment_intent_id !== null) {
            return ApiResponse::error('cannot_start_payment', 'Payment cannot be started for this order.', 422);
        }

        try {
            $intent = $this->orderService->startPayment($order);
        } catch (PaymentStartFailedException $e) {
            return ApiResponse::error('payment_intent_failed', $e->getMessage(), 502);
        }

        return ApiResponse::success([
            ...$this->orderPayload($order->fresh('items')),
            'client_secret' => $intent['client_secret'],
        ]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:confirmed,processing,shipped,delivered,cancelled'],
        ]);

        try {
            $this->orderService->transitionTo($order, $validated['status'], $request->user());
        } catch (InvalidOrderTransitionException $e) {
            return ApiResponse::error('invalid_transition', $e->getMessage(), 422);
        }

        return ApiResponse::success($this->orderPayload($order->fresh('items')));
    }

    public function history(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        return ApiResponse::success(
            $order->statusHistory->map(fn ($entry) => [
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                'changed_by' => $entry->changed_by,
                'note' => $entry->note,
                'created_at' => $entry->created_at->toIso8601String(),
            ])
        );
    }

    /**
     * @param  array<string, mixed>  $extras
     * @return array{0: int, 1: array}
     */
    private function checkout(Cart $cart, User $user, array $extras = []): array
    {
        try {
            $order = $this->orderService->createFromCart($cart, $extras);
        } catch (InsufficientStockException $e) {
            return [422, ['error' => ['code' => 'insufficient_stock', 'message' => $e->getMessage()]]];
        } catch (InvalidArgumentException $e) {
            return [422, ['error' => ['code' => 'invalid_checkout', 'message' => $e->getMessage()]]];
        }

        // A B2B order over the buyer's spending limit is held for the
        // account owner to approve - payment is never started for it here,
        // see OrderService::createFromCart and OrderApprovalController.
        if ($order->status === Order::STATUS_PENDING_APPROVAL) {
            return [201, ['data' => $this->orderPayload($order->fresh('items'))]];
        }

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

    private function resolveOwnedAddressId(User $user, ?int $addressId): ?int
    {
        if ($addressId === null) {
            return null;
        }

        $address = Address::find($addressId);

        $owned = $address !== null && (
            $address->user_id === $user->id
            || ($address->account_id !== null && $address->account_id === $user->account_id)
        );

        if (! $owned) {
            throw new InvalidArgumentException('Invalid address selected.');
        }

        return $addressId;
    }

    /**
     * @param  array<string, mixed>  $extras
     */
    private function fingerprintRequest(Cart $cart, array $extras): string
    {
        $lines = $cart->items
            ->map(fn ($item) => "{$item->product_variant_id}:{$item->quantity}")
            ->sort()
            ->values()
            ->implode('|');

        // Only fold extras into the fingerprint when any were actually
        // supplied, so a plain checkout (no address/coupon/note) hashes
        // identically to before these fields existed.
        $meaningfulExtras = array_filter($extras, fn ($value) => $value !== null);

        if (empty($meaningfulExtras)) {
            return hash('sha256', $lines);
        }

        ksort($meaningfulExtras);

        return hash('sha256', $lines.'::'.json_encode($meaningfulExtras));
    }

    private function orderPayload(Order $order, bool $includePaymentSecret = false): array
    {
        $payload = [
            'id' => $order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total' => $order->total,
            'discount_total' => $order->discount_total,
            'tax_total' => $order->tax_total,
            'gift_card_amount' => $order->gift_card_amount,
            'loyalty_points_redeemed' => $order->loyalty_points_redeemed,
            'loyalty_points_earned' => $order->loyalty_points_earned,
            'po_reference' => $order->po_reference,
            'customer_note' => $order->customer_note,
            'tracking_carrier' => $order->tracking_carrier,
            'tracking_number' => $order->tracking_number,
            'shipping_address_id' => $order->shipping_address_id,
            'billing_address_id' => $order->billing_address_id,
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
