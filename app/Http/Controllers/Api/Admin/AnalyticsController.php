<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Staff-only reporting surface. Everything here is deliberately read-only -
 * no route on this controller ever mutates an order - so it's fine for it
 * to run heavier aggregate queries than the rest of the admin surface.
 */
class AnalyticsController extends Controller
{
    private const REVENUE_TREND_DAYS = 30;

    private const TOP_PRODUCTS_LIMIT = 5;

    public function overview(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'total_revenue' => $this->totalRevenue(),
            'order_count_by_status' => $this->orderCountByStatus(),
            'top_products' => $this->topProducts(),
            'revenue_by_day' => $this->revenueByDay(),
        ]);
    }

    /**
     * A CSV export legitimately doesn't go through ApiResponse - that
     * envelope is JSON-only, and a spreadsheet is what this endpoint is
     * for. streamDownload() writes rows straight to the output buffer via
     * fputcsv() instead of building the whole file in memory, so this
     * stays cheap even once there are years of order history.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID', 'Status', 'Payment Status', 'Total', 'Discount Total', 'Customer Email', 'Created At',
            ]);

            Order::query()
                ->with('user:id,email')
                ->orderBy('id')
                ->chunk(200, function ($orders) use ($handle) {
                    foreach ($orders as $order) {
                        fputcsv($handle, [
                            $order->id,
                            $order->status,
                            $order->payment_status,
                            $order->total,
                            $order->discount_total,
                            $order->user->email ?? $order->guest_email,
                            $order->created_at->toIso8601String(),
                        ]);
                    }
                });

            fclose($handle);
        }, 'orders.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function totalRevenue(): string
    {
        $sum = (float) Order::query()
            ->where('payment_status', Order::PAYMENT_PAID)
            ->sum('total');

        return number_format($sum, 2, '.', '');
    }

    /**
     * @return array<string, int>
     */
    private function orderCountByStatus(): array
    {
        return Order::query()
            ->select('status', DB::raw('COUNT(*) as aggregate_count'))
            ->groupBy('status')
            ->pluck('aggregate_count', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Top products by units sold, counting only items that belong to a
     * paid order - an unpaid cart-turned-order shouldn't make a product
     * look more popular than it's actually been.
     *
     * @return array<int, array{product_id: int, name: string, quantity_sold: int}>
     */
    private function topProducts(): array
    {
        return OrderItem::query()
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', Order::PAYMENT_PAID)
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('quantity_sold')
            ->limit(self::TOP_PRODUCTS_LIMIT)
            ->get([
                'products.id as product_id',
                'products.name as name',
                DB::raw('SUM(order_items.quantity) as quantity_sold'),
            ])
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'name' => $row->name,
                'quantity_sold' => (int) $row->quantity_sold,
            ])
            ->all();
    }

    /**
     * Revenue per day for the trailing 30-day window, zero-filled for days
     * with no paid orders so a chart gets a continuous x-axis instead of
     * gaps. Grouped in PHP rather than with a DB-specific DATE()/date_trunc
     * call, since this project's tests run on sqlite while production runs
     * on mysql and this keeps the query portable between the two.
     *
     * @return array<int, array{date: string, revenue: string}>
     */
    private function revenueByDay(): array
    {
        $start = now()->startOfDay()->subDays(self::REVENUE_TREND_DAYS - 1);
        $end = now()->endOfDay();

        $revenueByDate = [];

        Order::query()
            ->where('payment_status', Order::PAYMENT_PAID)
            ->whereBetween('created_at', [$start, $end])
            ->get(['total', 'created_at'])
            ->each(function (Order $order) use (&$revenueByDate) {
                $date = $order->created_at->format('Y-m-d');
                $revenueByDate[$date] = ($revenueByDate[$date] ?? 0) + (float) $order->total;
            });

        $days = [];

        for ($i = self::REVENUE_TREND_DAYS - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $days[] = [
                'date' => $date,
                'revenue' => number_format($revenueByDate[$date] ?? 0, 2, '.', ''),
            ];
        }

        return $days;
    }
}
