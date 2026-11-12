<x-app-layout>
    <x-slot name="title">Owner overview</x-slot>

    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-[#c85a1c]">OUT OF BOREDOM · BUSINESS OVERVIEW</p>
                <h1 class="font-[Manrope,sans-serif] text-3xl font-bold tracking-tight text-stone-900 sm:text-4xl">Owner dashboard</h1>
                <p class="mt-2 text-sm text-stone-500">A clear view of today’s performance and business health.</p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <a class="rounded-lg bg-[#f07838] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#dc6328]" href="{{ route('business-input') }}">Add sale or product</a>
            <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-2" aria-label="Filter activity and sales trend dates">
                <label class="grid gap-1 text-xs font-semibold text-stone-600">
                    From
                    <input class="rounded-lg border-stone-200 text-sm focus:border-orange-400 focus:ring-orange-400" type="date" name="start_date" value="{{ $startDate }}">
                </label>
                <label class="grid gap-1 text-xs font-semibold text-stone-600">
                    To
                    <input class="rounded-lg border-stone-200 text-sm focus:border-orange-400 focus:ring-orange-400" type="date" name="end_date" value="{{ $endDate }}">
                </label>
                <button class="rounded-lg bg-[#f07838] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#dc6328]" type="submit">Apply</button>
                <a class="rounded-lg border border-orange-200 bg-white px-4 py-2.5 text-sm font-semibold text-stone-600 hover:bg-orange-50" href="{{ route('dashboard') }}">Today</a>
            </form>
            </div>
        </div>

        <section class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Business summary">
            <article class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-stone-500">Today’s revenue</p>
                <p class="mt-3 text-2xl font-bold tracking-tight text-stone-900">{{ $currency }} {{ number_format($todayRevenue, 2) }}</p>
                <p class="mt-2 text-xs text-stone-500">{{ number_format($todayOrderCount) }} completed order(s) today</p>
            </article>
            <article class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-stone-500">Monthly sales</p>
                <p class="mt-3 text-2xl font-bold tracking-tight text-stone-900">{{ $currency }} {{ number_format($monthlyRevenue, 2) }}</p>
                <p class="mt-2 text-xs text-stone-500">Estimated profit: {{ $currency }} {{ number_format($estimatedProfit, 2) }}</p>
            </article>
            <article class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-stone-500">Low stock</p>
                <p class="mt-3 text-2xl font-bold tracking-tight text-stone-900">{{ number_format($lowStock->count()) }}</p>
                <p class="mt-2 text-xs text-stone-500">Products at 10 units or below</p>
            </article>
            <article class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-stone-500">Active products</p>
                <p class="mt-3 text-2xl font-bold tracking-tight text-stone-900">{{ number_format($activeProductCount) }}</p>
                <p class="mt-2 text-xs text-stone-500">Currently available for sale</p>
            </article>
        </section>

        <section class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-5">
            <article class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm lg:col-span-3">
                <div class="mb-5">
                    <h2 class="text-base font-bold text-stone-900">Daily sales trend</h2>
                    <p class="mt-1 text-xs text-stone-500">Completed sales for the seven days through {{ $endDate }}</p>
                </div>
                @php($maxDailySales = max(1, (float) $salesTrend->max('total')))
                <div class="grid grid-cols-7 gap-2 sm:gap-4" role="img" aria-label="Daily sales trend for the seven days through {{ $endDate }}">
                    @foreach ($salesTrend as $day)
                        <div class="grid content-end gap-2 text-center">
                            <span class="truncate text-[10px] text-stone-500">{{ $currency }} {{ number_format($day['total'], 0) }}</span>
                            <div class="flex h-32 items-end justify-center rounded-lg bg-stone-50">
                                <div class="w-1/2 rounded-t-md bg-[#f07838]" style="height: {{ $day['total'] > 0 ? max(5, ($day['total'] / $maxDailySales) * 100) : 0 }}%"></div>
                            </div>
                            <span class="text-[10px] font-semibold text-stone-500">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm lg:col-span-2">
                <div class="mb-4">
                    <h2 class="text-base font-bold text-stone-900">Low stock alerts</h2>
                    <p class="mt-1 text-xs text-stone-500">Items with 10 units or fewer</p>
                </div>
                <ul class="divide-y divide-stone-100">
                    @forelse ($lowStock as $product)
                        <li class="flex items-center justify-between gap-4 py-3">
                            <span class="min-w-0 truncate text-sm font-medium text-stone-800">{{ $product->name }}</span>
                            <span class="shrink-0 rounded-full bg-orange-50 px-2.5 py-1 text-xs font-bold text-[#a84312]">{{ number_format((float) $product->stock_quantity, 2) }} left</span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-stone-500">No low-stock products.</li>
                    @endforelse
                </ul>
            </article>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
            <div class="mb-4">
                <h2 class="text-base font-bold text-stone-900">Recent business activity</h2>
                <p class="mt-1 text-xs text-stone-500">Latest orders in the selected date range</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-stone-100 text-[10px] font-bold uppercase tracking-wider text-stone-500">
                            <th class="px-3 py-3">Order #</th>
                            <th class="px-3 py-3">Total amount</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3 text-right">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td class="px-3 py-3 font-semibold text-stone-800">{{ $order->order_number }}</td>
                                <td class="px-3 py-3 text-stone-700">{{ $currency }} {{ number_format((float) $order->total_amount, 2) }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $order->status === 'completed' ? 'bg-orange-50 text-[#a84312]' : 'bg-stone-100 text-stone-600' }}">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-right text-xs text-stone-500">{{ \Illuminate\Support\Carbon::parse($order->created_at)->timezone(config('app.timezone'))->format('M j, Y · g:i A') }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-3 py-8 text-center text-sm text-stone-500" colspan="4">No orders in this date range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <p class="mt-5 text-center text-xs text-stone-400">OUT OF BOREDOM · Business Owner Overview</p>
    </div>
</x-app-layout>
