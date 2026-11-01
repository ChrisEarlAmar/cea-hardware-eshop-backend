<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductSummaryResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The homepage's merchandising feed: three independently-computed rails
 * (featured / best sellers / new arrivals) returned in one payload, so the
 * homepage needs a single request instead of three. Entirely public - no
 * auth of any kind, staff or otherwise, is involved in what shows up here.
 */
class HomeFeedController extends Controller
{
    private const SECTION_LIMIT = 8;

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'featured' => ProductSummaryResource::collection($this->featured()),
            'best_sellers' => ProductSummaryResource::collection($this->bestSellers()),
            'new_arrivals' => ProductSummaryResource::collection($this->newArrivals()),
        ]);
    }

    private function featured(): EloquentCollection
    {
        return Product::query()
            ->featured()
            ->withStartingPrice()
            ->latest()
            ->take(self::SECTION_LIMIT)
            ->get();
    }

    private function newArrivals(): EloquentCollection
    {
        return Product::query()
            ->withStartingPrice()
            ->sortBy('newest')
            ->take(self::SECTION_LIMIT)
            ->get();
    }

    /**
     * Top-selling products by units sold, counting only items that belong
     * to a paid order - the same reasoning (and join shape) as
     * Admin\AnalyticsController::topProducts(), grouped one level up by
     * product rather than variant since this rail merchandises products,
     * not SKUs. Not shared as a common helper with that controller because
     * the two return different shapes: that one is a staff report (just
     * id/name/quantity_sold), this one needs full public listing fields.
     */
    private function bestSellers(): Collection
    {
        $ranked = OrderItem::query()
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', Order::PAYMENT_PAID)
            ->groupBy('product_variants.product_id')
            ->orderByDesc('quantity_sold')
            ->limit(self::SECTION_LIMIT)
            ->get([
                'product_variants.product_id as product_id',
                DB::raw('SUM(order_items.quantity) as quantity_sold'),
            ]);

        $productIds = $ranked->pluck('product_id')->all();

        if (empty($productIds)) {
            return Collection::make();
        }

        $products = Product::query()
            ->withStartingPrice()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        // Re-order to match the ranking above - whereIn() does not
        // preserve the order its own array of ids was given in.
        return Collection::make($productIds)
            ->map(fn ($id) => $products->get($id))
            ->filter()
            ->values();
    }
}
