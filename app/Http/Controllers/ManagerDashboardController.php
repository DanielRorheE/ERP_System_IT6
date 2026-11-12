<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManagerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $settings = DB::table('cafe_settings')->first();
        $timezone = $settings->timezone ?? config('app.timezone');
        $today = Carbon::now($timezone)->toDateString();
        $startDate = $validated['start_date'] ?? $validated['end_date'] ?? $today;
        $endDate = $validated['end_date'] ?? $validated['start_date'] ?? $today;

        if ($startDate > $endDate) {
            throw ValidationException::withMessages([
                'end_date' => 'The end date must be on or after the start date.',
            ]);
        }
        $periodStart = Carbon::parse($startDate, $timezone)->startOfDay()->utc();
        $periodEnd = Carbon::parse($endDate, $timezone)->endOfDay()->utc();

        $completedOrders = DB::table('cafe_orders')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$periodStart, $periodEnd]);
        $salesSummary = (clone $completedOrders)
            ->selectRaw('COALESCE(SUM(total_amount), 0) as gross_revenue')
            ->selectRaw('COUNT(*) as order_count')
            ->first();

        $stockByProduct = DB::table('inventory_stocks')
            ->join('warehouse_bins', 'warehouse_bins.id', '=', 'inventory_stocks.warehouse_bin_id')
            ->when(
                $settings?->default_warehouse_id,
                fn ($query, $warehouseId) => $query->where('warehouse_bins.warehouse_id', $warehouseId),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->groupBy('inventory_stocks.product_id')
            ->select('inventory_stocks.product_id')
            ->selectRaw('SUM(inventory_stocks.quantity_on_hand) as stock_quantity');

        $lowStock = DB::table('products')
            ->leftJoinSub($stockByProduct, 'stock_totals', function ($join): void {
                $join->on('stock_totals.product_id', '=', 'products.id');
            })
            ->where(function ($query): void {
                $query->whereRaw('COALESCE(stock_totals.stock_quantity, 0) <= 10')
                    ->orWhere('products.is_active', false);
            })
            ->select(
                'products.id',
                'products.sku',
                'products.name',
                'products.base_unit',
                'products.is_active',
            )
            ->selectRaw('COALESCE(stock_totals.stock_quantity, 0) as stock_quantity')
            ->orderBy('stock_quantity')
            ->orderBy('products.name')
            ->get();

        $topProducts = DB::table('cafe_order_lines')
            ->join('cafe_orders', 'cafe_orders.id', '=', 'cafe_order_lines.cafe_order_id')
            ->where('cafe_orders.status', 'paid')
            ->whereBetween('cafe_orders.paid_at', [$periodStart, $periodEnd])
            ->groupBy('cafe_order_lines.menu_item_id', 'cafe_order_lines.item_name')
            ->select('cafe_order_lines.menu_item_id', 'cafe_order_lines.item_name')
            ->selectRaw('SUM(cafe_order_lines.quantity) as units_sold')
            ->selectRaw('SUM(cafe_order_lines.line_total) as sales')
            ->orderByDesc('units_sold')
            ->orderBy('cafe_order_lines.item_name')
            ->limit(5)
            ->get();

        $activeStaff = DB::table('cafe_orders')
            ->join('users', 'users.id', '=', 'cafe_orders.created_by')
            ->where('cafe_orders.status', 'paid')
            ->whereBetween('cafe_orders.paid_at', [$periodStart, $periodEnd])
            ->distinct()
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email']);

        $paymentBreakdown = DB::table('cafe_payments')
            ->join('cafe_orders', 'cafe_orders.id', '=', 'cafe_payments.cafe_order_id')
            ->where('cafe_orders.status', 'paid')
            ->whereBetween('cafe_payments.paid_at', [$periodStart, $periodEnd])
            ->groupBy('cafe_payments.method')
            ->select('cafe_payments.method')
            ->selectRaw('COUNT(*) as payment_count')
            ->selectRaw('SUM(cafe_payments.amount) as total_amount')
            ->orderByDesc('total_amount')
            ->get();

        $recentOrders = (clone $completedOrders)
            ->leftJoin('users', 'users.id', '=', 'cafe_orders.created_by')
            ->orderByDesc('cafe_orders.paid_at')
            ->limit(8)
            ->get([
                'cafe_orders.id',
                'cafe_orders.order_number',
                'cafe_orders.order_type',
                'cafe_orders.total_amount',
                'cafe_orders.paid_at',
                'users.name as cashier',
            ]);

        $trendStart = Carbon::parse($endDate, $timezone)->subDays(6)->startOfDay();
        $trendEnd = Carbon::parse($endDate, $timezone)->endOfDay();
        $trendOrders = DB::table('cafe_orders')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$trendStart->copy()->utc(), $trendEnd->copy()->utc()])
            ->get(['total_amount', 'paid_at']);
        $trendByDate = $trendOrders->groupBy(
            fn ($order) => Carbon::parse($order->paid_at, 'UTC')->setTimezone($timezone)->toDateString(),
        );
        $revenueTrend = collect(range(0, 6))->map(function (int $offset) use ($trendStart, $trendByDate): array {
            $date = $trendStart->copy()->addDays($offset);

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'revenue' => (float) $trendByDate->get($date->toDateString(), collect())->sum('total_amount'),
            ];
        });

        return view('manager.dashboard', [
            'currency' => $settings->currency_code ?? 'PHP',
            'timezone' => $timezone,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'grossRevenue' => (float) $salesSummary->gross_revenue,
            'orderCount' => (int) $salesSummary->order_count,
            'lowStock' => $lowStock,
            'topProducts' => $topProducts,
            'activeStaff' => $activeStaff,
            'paymentBreakdown' => $paymentBreakdown,
            'recentOrders' => $recentOrders,
            'revenueTrend' => $revenueTrend,
        ]);
    }
}
