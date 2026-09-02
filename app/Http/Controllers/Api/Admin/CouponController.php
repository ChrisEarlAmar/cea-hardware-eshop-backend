<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Staff-only coupon management. destroy() is deliberately a soft
 * deactivation (active = false) rather than a hard delete: used_count and
 * the coupon's history of past use need to survive even once a coupon is
 * retired, and any order that already referenced this coupon should keep
 * resolving it. Reachable only via the `staff` middleware (see
 * routes/api.php).
 */
class CouponController extends Controller
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

        $paginator = Coupon::query()
            ->latest()
            ->paginate($validated['per_page'] ?? 25);

        return ApiResponse::success(
            $paginator->getCollection()->map(fn (Coupon $coupon) => $this->couponPayload($coupon)),
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
        $validated = $this->validateCoupon($request);

        $coupon = Coupon::create([
            ...$validated,
            'active' => $validated['active'] ?? true,
            // Explicit rather than relying on the DB column default - the
            // in-memory model wouldn't otherwise pick up used_count until a
            // fresh() reload, and the response below is built from this
            // very instance.
            'used_count' => 0,
        ]);

        $this->auditLogger->log($request->user(), 'coupon.create', $coupon, [
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => (string) $coupon->value,
        ]);

        return ApiResponse::success($this->couponPayload($coupon), status: 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $validated = $this->validateCoupon($request, $coupon);

        $coupon->update([
            ...$validated,
            'active' => $validated['active'] ?? $coupon->active,
        ]);

        $this->auditLogger->log($request->user(), 'coupon.update', $coupon, [
            'code' => $coupon->code,
            'changes' => $validated,
        ]);

        return ApiResponse::success($this->couponPayload($coupon->fresh()));
    }

    /**
     * Soft-deactivates rather than deletes - see class docblock.
     */
    public function destroy(Request $request, Coupon $coupon): JsonResponse
    {
        $coupon->update(['active' => false]);

        $this->auditLogger->log($request->user(), 'coupon.deactivate', $coupon, [
            'code' => $coupon->code,
        ]);

        return ApiResponse::success($this->couponPayload($coupon->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCoupon(Request $request, ?Coupon $coupon = null): array
    {
        $validator = Validator::make($request->all(), [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('coupons', 'code')->ignore($coupon?->id),
            ],
            'type' => ['required', Rule::in([Coupon::TYPE_PERCENT, Coupon::TYPE_FIXED])],
            'value' => ['required', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'min_order_total' => ['nullable', 'numeric', 'min:0'],
            'expires_at' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $validator->after(function (ValidatorContract $validator) use ($request) {
            $type = $request->input('type');
            $value = $request->input('value');

            if ($type === Coupon::TYPE_PERCENT && $value !== null && is_numeric($value) && (float) $value > 100) {
                $validator->errors()->add('value', 'A percent coupon cannot exceed 100.');
            }
        });

        return $validator->validate();
    }

    private function couponPayload(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => (string) $coupon->value,
            'max_uses' => $coupon->max_uses,
            'used_count' => $coupon->used_count,
            'min_order_total' => $coupon->min_order_total !== null ? (string) $coupon->min_order_total : null,
            'expires_at' => $coupon->expires_at?->toIso8601String(),
            'active' => $coupon->active,
            'created_at' => $coupon->created_at->toIso8601String(),
        ];
    }
}
