<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RfqRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer-facing Request-For-Quote flow: a signed-in buyer asks for a
 * custom price on a product/quantity that isn't already covered by the
 * public price-break table (see PriceTierController). Staff review and
 * respond out-of-band; App\Http\Controllers\Api\Admin\RfqController is
 * where they flag a request as handled.
 */
class RfqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rfqs = RfqRequest::query()
            ->where('user_id', $request->user()->id)
            ->with('product:id,name,slug')
            ->latest()
            ->get();

        return ApiResponse::success($rfqs->map(fn (RfqRequest $rfq) => $this->payload($rfq)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $rfq = RfqRequest::create([
            'user_id' => $request->user()->id,
            'product_id' => $validated['product_id'],
            'quantity' => $validated['quantity'],
            'message' => $validated['message'] ?? null,
            'status' => RfqRequest::STATUS_OPEN,
        ]);

        return ApiResponse::success($this->payload($rfq->fresh('product')), status: 201);
    }

    private function payload(RfqRequest $rfq): array
    {
        return [
            'id' => $rfq->id,
            'product' => $rfq->relationLoaded('product') && $rfq->product ? [
                'id' => $rfq->product->id,
                'name' => $rfq->product->name,
                'slug' => $rfq->product->slug,
            ] : null,
            'quantity' => $rfq->quantity,
            'message' => $rfq->message,
            'status' => $rfq->status,
            'created_at' => $rfq->created_at?->toIso8601String(),
        ];
    }
}
