<x-app-layout>
    <x-slot name="title">Business input</x-slot>

    <div class="mx-auto max-w-5xl">
        <div class="mb-8">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-[#c85a1c]">OUT OF BOREDOM · BUSINESS INPUT</p>
            <h1 class="font-[Manrope,sans-serif] text-3xl font-bold tracking-tight text-stone-900 sm:text-4xl">Add business data</h1>
            <p class="mt-2 text-sm text-stone-500">Enter a simple sale or product so the dashboard has current information.</p>
        </div>

        @if (session('success'))
            <div class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-semibold">Please correct the highlighted information.</p>
                <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-stone-900">Add sale</h2>
                <p class="mt-1 text-xs text-stone-500">This updates revenue, profit, and recent activity.</p>
                <form class="mt-5 grid gap-4" method="POST" action="{{ route('business-input.sales.store') }}">
                    @csrf
                    <label class="grid gap-1 text-xs font-semibold text-stone-600">Order number<input name="order_number" value="{{ old('order_number', 'SALE-'.now()->format('YmdHis')) }}" required maxlength="40" class="rounded-lg border-stone-200 text-sm"></label>
                    <label class="grid gap-1 text-xs font-semibold text-stone-600">Product<select name="product_id" required class="rounded-lg border-stone-200 text-sm"><option value="">Select a product</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }} · {{ $currency }} {{ number_format((float) $product->price, 2) }} · {{ number_format((float) $product->stock_quantity, 2) }} in stock</option>@endforeach</select></label>
                    <label class="grid gap-1 text-xs font-semibold text-stone-600">Quantity<input name="quantity" value="{{ old('quantity', 1) }}" required min="1" step="1" type="number" class="rounded-lg border-stone-200 text-sm"></label>
                    <p class="-mt-2 text-xs text-stone-500">The sale total and cost are calculated from the selected product and quantity.</p>
                    <label class="grid gap-1 text-xs font-semibold text-stone-600">Date and time<input name="sale_date" value="{{ old('sale_date', now()->format('Y-m-d\\TH:i')) }}" required type="datetime-local" class="rounded-lg border-stone-200 text-sm"></label>
                    <label class="grid gap-1 text-xs font-semibold text-stone-600">Status<select name="status" class="rounded-lg border-stone-200 text-sm"><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></label>
                    <button class="rounded-lg bg-[#f07838] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#dc6328]" type="submit">Save sale</button>
                </form>
            </section>

            <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-stone-900">Add product</h2>
                <p class="mt-1 text-xs text-stone-500">This updates the product count and stock alerts.</p>
                <form class="mt-5 grid gap-4" method="POST" action="{{ route('business-input.products.store') }}">
                    @csrf
                    <label class="grid gap-1 text-xs font-semibold text-stone-600">Product name<input name="name" value="{{ old('name') }}" required maxlength="150" class="rounded-lg border-stone-200 text-sm"></label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-1 text-xs font-semibold text-stone-600">Selling price<input name="price" value="{{ old('price') }}" required min="0" step="0.01" type="number" class="rounded-lg border-stone-200 text-sm"></label>
                        <label class="grid gap-1 text-xs font-semibold text-stone-600">Cost price<input name="cost_price" value="{{ old('cost_price', 0) }}" required min="0" step="0.01" type="number" class="rounded-lg border-stone-200 text-sm"></label>
                    </div>
                    <label class="grid gap-1 text-xs font-semibold text-stone-600">Stock quantity<input name="stock_quantity" value="{{ old('stock_quantity', 0) }}" required min="0" step="0.01" type="number" class="rounded-lg border-stone-200 text-sm"></label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-stone-600"><input name="is_available" value="1" checked type="checkbox" class="rounded border-stone-300 text-orange-500"> Available for sale</label>
                    <button class="rounded-lg border border-orange-200 bg-white px-4 py-2.5 text-sm font-semibold text-stone-700 hover:bg-orange-50" type="submit">Save product</button>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
