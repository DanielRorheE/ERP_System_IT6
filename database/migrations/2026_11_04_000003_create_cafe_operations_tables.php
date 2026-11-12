<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE products ADD COLUMN product_category_id INTEGER NULL REFERENCES product_categories(id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE products ADD COLUMN reorder_level NUMERIC NOT NULL DEFAULT 0');
            DB::statement('CREATE INDEX products_category_active_idx ON products (product_category_id, is_active)');
        } else {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('product_category_id')->nullable()->after('name')->constrained()->restrictOnDelete();
                $table->decimal('reorder_level', 19, 4)->default(0);
                $table->index(['product_category_id', 'is_active'], 'products_category_active_idx');
            });
        }

        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_category_id')->constrained()->restrictOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->decimal('price', 12, 2);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['menu_category_id', 'is_available'], 'menu_items_category_available_idx');
            $table->index(['is_available', 'name'], 'menu_items_available_name_idx');
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('yield_quantity', 12, 4)->default(1);
            $table->string('instructions', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_product_id')->constrained('products')->restrictOnDelete();
            $table->string('applies_to', 12)->default('all');
            $table->decimal('quantity', 19, 4);
            $table->timestamps();

            $table->unique(['recipe_id', 'ingredient_product_id', 'applies_to'], 'recipe_lines_ingredient_scope_uq');
            $table->index(['ingredient_product_id', 'recipe_id'], 'recipe_lines_product_recipe_idx');
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->string('contact_name', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('tax_reference', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name'], 'suppliers_active_name_idx');
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('ordered');
            $table->date('ordered_at');
            $table->date('expected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'ordered_at'], 'purchase_orders_status_date_idx');
            $table->index(['supplier_id', 'ordered_at'], 'purchase_orders_supplier_date_idx');
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_ordered', 19, 4);
            $table->decimal('unit_cost', 19, 4);
            $table->timestamps();

            $table->unique(['purchase_order_id', 'product_id'], 'purchase_order_lines_product_uq');
            $table->index(['product_id', 'purchase_order_id'], 'purchase_order_lines_product_idx');
        });

        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 40)->unique();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'received_at'], 'purchase_receipts_order_date_idx');
        });

        Schema::create('purchase_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_received', 19, 4);
            $table->decimal('unit_cost', 19, 4);
            $table->timestamps();

            $table->unique(['purchase_receipt_id', 'purchase_order_line_id'], 'purchase_receipt_lines_line_uq');
            $table->index(['purchase_order_line_id', 'purchase_receipt_id'], 'purchase_receipt_lines_order_line_idx');
        });

        Schema::create('cafe_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_type', 12)->default('takeaway');
            $table->string('status', 12)->default('paid');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->string('notes', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'paid_at'], 'cafe_orders_status_paid_idx');
            $table->index(['created_by', 'paid_at'], 'cafe_orders_cashier_date_idx');
        });

        Schema::create('cafe_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->restrictOnDelete();
            $table->string('item_name', 120);
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 14, 2);
            $table->decimal('unit_cost_snapshot', 14, 4);
            $table->timestamps();

            $table->index(['menu_item_id', 'cafe_order_id'], 'cafe_order_lines_menu_order_idx');
            $table->index(['cafe_order_id', 'menu_item_id'], 'cafe_order_lines_order_menu_idx');
        });

        Schema::create('cafe_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_order_id')->constrained()->restrictOnDelete();
            $table->string('method', 16);
            $table->decimal('amount', 14, 2);
            $table->string('reference', 120)->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['method', 'paid_at'], 'cafe_payments_method_date_idx');
            $table->index(['cafe_order_id', 'paid_at'], 'cafe_payments_order_date_idx');
        });

        Schema::create('cafe_settings', function (Blueprint $table) {
            $table->id();
            $table->string('business_name', 150)->default('OUT OF BOREDOM');
            $table->char('currency_code', 3)->default('PHP');
            $table->string('timezone', 64)->default('Asia/Manila');
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->boolean('prices_include_tax')->default(false);
            $table->foreignId('default_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('default_bin_id')->nullable()->constrained('warehouse_bins')->restrictOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('inventory_account_id')->nullable()->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('accounts_payable_account_id')->nullable()->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('sales_revenue_account_id')->nullable()->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('cost_of_goods_account_id')->nullable()->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('tax_payable_account_id')->nullable()->constrained('gl_accounts')->restrictOnDelete();
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_reorder_level_chk CHECK (reorder_level >= 0)');
            DB::statement('ALTER TABLE menu_items ADD CONSTRAINT menu_items_price_chk CHECK (price > 0)');
            DB::statement('ALTER TABLE recipes ADD CONSTRAINT recipes_yield_chk CHECK (yield_quantity > 0)');
            DB::statement('ALTER TABLE recipe_lines ADD CONSTRAINT recipe_lines_quantity_chk CHECK (quantity > 0)');
            DB::statement("ALTER TABLE recipe_lines ADD CONSTRAINT recipe_lines_scope_chk CHECK (applies_to IN ('all', 'takeaway', 'dine_in', 'delivery'))");
            DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_chk CHECK (status IN ('ordered', 'partially_received', 'received', 'cancelled'))");
            DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_amounts_chk CHECK (quantity_ordered > 0 AND unit_cost >= 0)');
            DB::statement('ALTER TABLE purchase_receipt_lines ADD CONSTRAINT purchase_receipt_lines_amounts_chk CHECK (quantity_received > 0 AND unit_cost >= 0)');
            DB::statement("ALTER TABLE cafe_orders ADD CONSTRAINT cafe_orders_type_chk CHECK (order_type IN ('takeaway', 'dine_in', 'delivery'))");
            DB::statement("ALTER TABLE cafe_orders ADD CONSTRAINT cafe_orders_status_chk CHECK (status IN ('paid', 'voided', 'refunded'))");
            DB::statement('ALTER TABLE cafe_orders ADD CONSTRAINT cafe_orders_amounts_chk CHECK (subtotal >= 0 AND discount_amount >= 0 AND discount_amount <= subtotal AND tax_rate >= 0 AND tax_amount >= 0 AND total_amount >= 0)');
            DB::statement('ALTER TABLE cafe_order_lines ADD CONSTRAINT cafe_order_lines_amounts_chk CHECK (quantity > 0 AND unit_price >= 0 AND line_total >= 0 AND unit_cost_snapshot >= 0)');
            DB::statement("ALTER TABLE cafe_payments ADD CONSTRAINT cafe_payments_method_chk CHECK (method IN ('cash', 'card', 'e_wallet'))");
            DB::statement('ALTER TABLE cafe_payments ADD CONSTRAINT cafe_payments_amount_chk CHECK (amount > 0)');
            DB::statement('ALTER TABLE cafe_settings ADD CONSTRAINT cafe_settings_tax_rate_chk CHECK (tax_rate >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cafe_settings');
        Schema::dropIfExists('cafe_payments');
        Schema::dropIfExists('cafe_order_lines');
        Schema::dropIfExists('cafe_orders');
        Schema::dropIfExists('purchase_receipt_lines');
        Schema::dropIfExists('purchase_receipts');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('recipe_lines');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX products_category_active_idx');
            DB::statement('ALTER TABLE products DROP COLUMN product_category_id');
            DB::statement('ALTER TABLE products DROP COLUMN reorder_level');
        } else {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_category_active_idx');
                $table->dropConstrainedForeignId('product_category_id');
                $table->dropColumn('reorder_level');
            });
        }
        Schema::dropIfExists('product_categories');
    }
};
