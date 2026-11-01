<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AlsoBoughtController extends Controller
{
    private const LIMIT = 6;

    /**
     * Up to 6 other products that have appeared in the same paid order as
     * $product, ranked by how many distinct paid orders paired them
     * together. Deliberately not restricted to the same category - the
     * whole point of "customers who bought this also bought" is that it can
     * surface something outside it. A separate, category-based "related"
     * rail already exists (RelatedProductController) for that.
     */
    public function index(Product $product): JsonResponse
    {
        $ranked = OrderItem::query()
            ->join('order_items as paired_items', 'paired_items.order_id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('product_variants as paired_variants', 'paired_variants.id', '=', 'paired_items.product_variant_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('product_variants.product_id', $product->id)
            ->where('paired_variants.product_id', '!=', $product->id)
            ->where('orders.payment_status', Order::PAYMENT_PAID)
            ->groupBy('paired_variants.product_id')
            ->orderByDesc('order_count')
            ->limit(self::LIMIT)
            ->get([
                'paired_variants.product_id as product_id',
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count'),
            ]);

        $productIds = $ranked->pluck('product_id')->all();

        if (empty($productIds)) {
            return ApiResponse::success([]);
        }

        $productsById = Product::query()
            ->whereIn('id', $productIds)
            ->get(['id', 'name', 'slug', 'image_url'])
            ->keyBy('id');

        // Re-order by rank rather than the whereIn()'s arbitrary order -
        // whereIn() does not preserve the order of the given ids.
        $alsoBought = collect($productIds)
            ->map(fn (int $id) => $productsById->get($id))
            ->filter()
            ->map(fn (Product $related) => [
                'id' => $related->id,
                'name' => $related->name,
                'slug' => $related->slug,
                'image_url' => $related->image_url,
            ])
            ->values();

        return ApiResponse::success($alsoBought);
    }
}
