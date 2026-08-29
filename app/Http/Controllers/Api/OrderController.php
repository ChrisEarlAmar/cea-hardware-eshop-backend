<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Controllers\Controller;
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

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $cart->load('items.variant');

        $fingerprint = $this->fingerprintCart($cart);

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

        [$status, $body] = $this->checkout($cart, $user);

        $idempotencyRecord->update(['response_status' => $status, 'response_body' => $body]);

        return response()->json($body, $status);
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
     * @return array{0: int, 1: array}
     */
    private function checkout(Cart $cart, User $user): array
    {
        try {
            $order = $this->orderService->createFromCart($cart);
        } catch (InsufficientStockException $e) {
            return [422, ['error' => ['code' => 'insufficient_stock', 'message' => $e->getMessage()]]];
        }

        try {
            $intent = $this->paymentService->createPaymentIntentFor($order);
        } catch (\Throwable $e) {
            // Stock was already reserved by createFromCart above, but there
            // is now no way to pay for this order - release the
            // reservation immediately rather than leaving it locked.
            $this->orderService->transitionTo($order, Order::STATUS_CANCELLED, $user, 'Payment could not be started.');
            report($e);

            return [502, ['error' => [
                'code' => 'payment_intent_failed',
                'message' => 'Could not start payment for this order. No charge was made; please try again.',
            ]]];
        }

        $order->update([
            'payment_intent_id' => $intent['id'],
            'payment_status' => Order::PAYMENT_REQUIRES_PAYMENT,
        ]);

        return [201, ['data' => [
            ...$this->orderPayload($order->fresh('items')),
            'client_secret' => $intent['client_secret'],
        ]]];
    }

    private function fingerprintCart(Cart $cart): string
    {
        $lines = $cart->items
            ->map(fn ($item) => "{$item->product_variant_id}:{$item->quantity}")
            ->sort()
            ->values()
            ->implode('|');

        return hash('sha256', $lines);
    }

    private function orderPayload(Order $order, bool $includePaymentSecret = false): array
    {
        $payload = [
            'id' => $order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total' => $order->total,
            'items' => $order->items->map(fn ($item) => [
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
