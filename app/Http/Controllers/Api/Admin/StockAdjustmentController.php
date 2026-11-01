<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Services\AuditLogger;
use App\Services\StockNotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Manual stock corrections (restock, shrinkage, damage write-off, a
 * physical recount, etc.) - separate from the automatic decrement
 * InventoryService performs at checkout. Every adjustment is applied as a
 * single conditional UPDATE, the same technique as
 * InventoryService::decrementStock(): "add delta, but only if the result
 * won't go negative", evaluated and applied by the database as one
 * indivisible operation rather than a read-then-write with a gap in the
 * middle. Races are unlikely for an admin action, but the pattern costs
 * nothing extra and keeps every stock mutation in this codebase safe the
 * same way. Reachable only via the `staff` middleware (see routes/api.php).
 */
class StockAdjustmentController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly StockNotificationService $stockNotificationService,
    ) {
    }

    public function index(ProductVariant $variant): JsonResponse
    {
        return ApiResponse::success(
            $variant->stockAdjustments()
                ->with('actor:id,name,email')
                ->get()
                ->map(fn (StockAdjustment $adjustment) => $this->payload($adjustment))
        );
    }

    public function store(Request $request, ProductVariant $variant): JsonResponse
    {
        $validated = $request->validate([
            'delta' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $delta = (int) $validated['delta'];
        $previousStock = $variant->stock;

        // stock + delta >= 0  <=>  stock >= -delta. For a positive delta
        // (restock) -delta is negative and the check always passes; for a
        // negative delta it requires enough stock on hand first.
        $affected = DB::table('product_variants')
            ->where('id', $variant->id)
            ->where('stock', '>=', -$delta)
            ->increment('stock', $delta);

        if ($affected === 0) {
            return ApiResponse::error(
                'insufficient_stock',
                'This adjustment would bring stock below zero.',
                422,
            );
        }

        $variant->refresh();

        $this->stockNotificationService->notifyIfBackInStock($variant, $previousStock);

        $adjustment = StockAdjustment::create([
            'product_variant_id' => $variant->id,
            'delta' => $delta,
            'reason' => $validated['reason'],
            'actor_id' => $request->user()->id,
            'resulting_stock' => $variant->stock,
        ]);

        // created_at isn't fillable and $timestamps is disabled on this
        // model (see StockAdjustment) - it's populated only by the
        // column's DB-level useCurrent() default, which Eloquent's
        // in-memory instance from create() doesn't see until reloaded.
        $adjustment->refresh();

        $this->auditLogger->log($request->user(), 'stock_adjustment.create', $adjustment, [
            'product_variant_id' => $variant->id,
            'delta' => $delta,
            'resulting_stock' => $variant->stock,
        ]);

        return ApiResponse::success($this->payload($adjustment->load('actor:id,name,email')), status: 201);
    }

    private function payload(StockAdjustment $adjustment): array
    {
        return [
            'id' => $adjustment->id,
            'product_variant_id' => $adjustment->product_variant_id,
            'delta' => $adjustment->delta,
            'reason' => $adjustment->reason,
            'resulting_stock' => $adjustment->resulting_stock,
            'actor' => $adjustment->relationLoaded('actor') && $adjustment->actor ? [
                'id' => $adjustment->actor->id,
                'name' => $adjustment->actor->name,
            ] : null,
            'created_at' => $adjustment->created_at->toIso8601String(),
        ];
    }
}
