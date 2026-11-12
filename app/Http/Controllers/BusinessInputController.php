<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BusinessInputController extends Controller
{
    public function create(): View
    {
        return view('owner.input', [
            'products' => DB::table('products')->orderBy('name')->get(['id', 'name', 'price', 'stock_quantity']),
            'currency' => config('manager.currency', 'PHP'),
        ]);
    }

    public function storeSale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:40', 'unique:orders,order_number'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'integer', 'min:1'],
            'sale_date' => ['required', 'date_format:Y-m-d\\TH:i'],
            'status' => ['required', 'in:completed,cancelled'],
        ]);

        DB::transaction(function () use ($validated): void {
            $product = DB::table('products')->where('id', $validated['product_id'])->lockForUpdate()->first();
            $quantity = (float) $validated['quantity'];

            if ($validated['status'] === 'completed' && (! $product->is_available || (float) $product->stock_quantity < $quantity)) {
                abort(422, 'The selected product is unavailable or does not have enough stock.');
            }

            $unitPrice = (float) $product->price;
            $lineTotal = round($unitPrice * $quantity, 2);
            $costTotal = round((float) $product->cost_price * $quantity, 2);
            $orderId = DB::table('orders')->insertGetId([
                'order_number' => $validated['order_number'],
                'total_amount' => $lineTotal,
                'cost_total' => $costTotal,
                'status' => $validated['status'],
                'created_at' => $validated['sale_date'],
            ]);

            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ]);

            if ($validated['status'] === 'completed') {
                DB::table('products')->where('id', $product->id)->decrement('stock_quantity', $quantity);
            }
        });

        return to_route('business-input')->with('success', 'Sale recorded successfully.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'numeric', 'min:0'],
            'is_available' => ['nullable', 'boolean'],
        ]);

        DB::table('products')->insert([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'cost_price' => $validated['cost_price'],
            'stock_quantity' => $validated['stock_quantity'],
            'is_available' => $request->boolean('is_available'),
        ]);

        return to_route('business-input')->with('success', 'Product added successfully.');
    }
}
