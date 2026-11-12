<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dashboard_requires_authentication_and_home_redirects_to_it(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_owner_dashboard_shows_business_metrics_and_recent_activity(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.test']);
        $categoryId = DB::table('categories')->insertGetId(['name' => 'Coffee']);
        DB::table('products')->insert([
            [
                'category_id' => $categoryId,
                'name' => 'House latte',
                'price' => 6.50,
                'cost_price' => 2.00,
                'stock_quantity' => 8,
                'is_available' => true,
            ],
            [
                'category_id' => $categoryId,
                'name' => 'Seasonal tea',
                'price' => 5.00,
                'cost_price' => 1.50,
                'stock_quantity' => 40,
                'is_available' => false,
            ],
        ]);
        $now = now();
        DB::table('orders')->insert([
            [
                'order_number' => 'SMS-001',
                'total_amount' => 100,
                'cost_total' => 60,
                'status' => 'completed',
                'created_at' => $now,
            ],
            [
                'order_number' => 'SMS-002',
                'total_amount' => 50,
                'cost_total' => 20,
                'status' => 'cancelled',
                'created_at' => $now,
            ],
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Today’s revenue')
            ->assertSee('PHP 100.00')
            ->assertSee('Monthly sales')
            ->assertSee('Estimated profit: PHP 40.00')
            ->assertSee('Low stock')
            ->assertSee('House latte')
            ->assertDontSee('Seasonal tea') // Unavailable products are not a stock warning.
            ->assertSee('Active products')
            ->assertSee('Recent business activity')
            ->assertSee('SMS-001')
            ->assertSee('SMS-002')
            ->assertSee('Cancelled');
    }

    public function test_owner_dashboard_rejects_an_inverted_date_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', [
                'start_date' => '2026-11-12',
                'end_date' => '2026-11-11',
            ]))
            ->assertSessionHasErrors('end_date');
    }

    public function test_final_schema_contains_only_owner_business_tables(): void
    {
        foreach (['roles', 'users', 'categories', 'products', 'orders', 'order_items'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertDatabaseHas('roles', ['name' => 'owner']);

        foreach (['cafe_orders', 'inventory_stocks', 'journal_entries', 'sessions', 'cache', 'jobs'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public function test_owner_can_add_a_sale_and_product_from_business_input(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('business-input'))
            ->assertOk()
            ->assertSee('Add sale')
            ->assertSee('Add product');

        $productId = DB::table('products')->insertGetId([
            'name' => 'House latte',
            'price' => 50,
            'cost_price' => 30,
            'stock_quantity' => 10,
            'is_available' => true,
        ]);

        $this->actingAs($user)
            ->post(route('business-input.sales.store'), [
                'order_number' => 'SALE-100',
                'product_id' => $productId,
                'quantity' => 2,
                'sale_date' => now()->format('Y-m-d\\TH:i'),
                'status' => 'completed',
            ])
            ->assertRedirect(route('business-input'));

        $this->actingAs($user)
            ->post(route('business-input.products.store'), [
                'name' => 'House latte',
                'price' => 6.50,
                'cost_price' => 2,
                'stock_quantity' => 8,
                'is_available' => true,
            ])
            ->assertRedirect(route('business-input'));

        $this->assertDatabaseHas('orders', ['order_number' => 'SALE-100', 'status' => 'completed']);
        $this->assertDatabaseHas('order_items', ['order_id' => DB::table('orders')->where('order_number', 'SALE-100')->value('id'), 'product_id' => $productId, 'quantity' => 2]);
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => 8]);
    }
}
