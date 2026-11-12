<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $timezone = config('app.timezone');
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
        $todayTotals = DB::table('orders')
            ->where('status', 'completed')
            ->whereBetween('created_at', [
                Carbon::parse($today, $timezone)->startOfDay()->utc(),
                Carbon::parse($today, $timezone)->endOfDay()->utc(),
            ])
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->selectRaw('COUNT(*) as order_count')
            ->first();

        $monthStart = Carbon::now($timezone)->startOfMonth()->utc();
        $monthEnd = Carbon::now($timezone)->endOfMonth()->utc();
        $monthlyTotals = DB::table('orders')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(total_amount - cost_total), 0) as estimated_profit')
            ->first();

        $lowStock = DB::table('products')
            ->where('stock_quantity', '<=', 10)
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->get(['id', 'name', 'stock_quantity']);

        $revenueByDate = DB::table('orders')
            ->where('status', 'completed')
            ->whereBetween('created_at', [
                Carbon::parse($endDate, $timezone)->subDays(6)->startOfDay()->utc(),
                Carbon::parse($endDate, $timezone)->endOfDay()->utc(),
            ])
            ->selectRaw('DATE(created_at) as sales_date, SUM(total_amount) as total')
            ->groupBy('sales_date')
            ->pluck('total', 'sales_date');
        $trendStart = Carbon::parse($endDate, $timezone)->subDays(6)->startOfDay();
        $salesTrend = collect(range(0, 6))->map(function (int $offset) use ($trendStart, $revenueByDate): array {
            $date = $trendStart->copy()->addDays($offset);

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'total' => (float) $revenueByDate->get($date->toDateString(), 0),
            ];
        });

        $recentOrders = DB::table('orders')
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['order_number', 'total_amount', 'status', 'created_at']);

        return view('owner.dashboard', [
            'currency' => config('manager.currency', 'PHP'),
            'todayRevenue' => (float) $todayTotals->revenue,
            'todayOrderCount' => (int) $todayTotals->order_count,
            'monthlyRevenue' => (float) $monthlyTotals->revenue,
            'estimatedProfit' => (float) $monthlyTotals->estimated_profit,
            'lowStock' => $lowStock,
            'activeProductCount' => DB::table('products')->where('is_available', true)->count(),
            'recentOrders' => $recentOrders,
            'salesTrend' => $salesTrend,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }
}
