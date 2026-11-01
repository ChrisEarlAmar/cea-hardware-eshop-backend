<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Staff-only gift card issuance. There's no destroy() - a gift card, once
 * issued, keeps existing (deactivate it instead) since past orders may
 * already reference it. Reachable only via the `staff` middleware (see
 * routes/api.php).
 */
class GiftCardController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = GiftCard::query()
            ->latest()
            ->paginate($validated['per_page'] ?? 25);

        return ApiResponse::success(
            $paginator->getCollection()->map(fn (GiftCard $giftCard) => $this->giftCardPayload($giftCard)),
            meta: [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('gift_cards', 'code')],
            'initial_balance' => ['required', 'numeric', 'min:0.01'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $giftCard = GiftCard::create([
            'code' => $validated['code'],
            'initial_balance' => $validated['initial_balance'],
            'remaining_balance' => $validated['initial_balance'],
            'expires_at' => $validated['expires_at'] ?? null,
            'active' => true,
        ]);

        $this->auditLogger->log($request->user(), 'gift_card.create', $giftCard, [
            'code' => $giftCard->code,
            'initial_balance' => (string) $giftCard->initial_balance,
        ]);

        return ApiResponse::success($this->giftCardPayload($giftCard), status: 201);
    }

    public function update(Request $request, GiftCard $giftCard): JsonResponse
    {
        $validated = $request->validate([
            'expires_at' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $giftCard->update($validated);

        $this->auditLogger->log($request->user(), 'gift_card.update', $giftCard, [
            'code' => $giftCard->code,
            'changes' => $validated,
        ]);

        return ApiResponse::success($this->giftCardPayload($giftCard->fresh()));
    }

    public function destroy(Request $request, GiftCard $giftCard): JsonResponse
    {
        $giftCard->update(['active' => false]);

        $this->auditLogger->log($request->user(), 'gift_card.deactivate', $giftCard, [
            'code' => $giftCard->code,
        ]);

        return ApiResponse::success($this->giftCardPayload($giftCard->fresh()));
    }

    private function giftCardPayload(GiftCard $giftCard): array
    {
        return [
            'id' => $giftCard->id,
            'code' => $giftCard->code,
            'initial_balance' => (string) $giftCard->initial_balance,
            'remaining_balance' => (string) $giftCard->remaining_balance,
            'expires_at' => $giftCard->expires_at?->toIso8601String(),
            'active' => $giftCard->active,
            'created_at' => $giftCard->created_at->toIso8601String(),
        ];
    }
}
