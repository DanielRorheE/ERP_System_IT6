<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->string('account_type', 30);
            $table->string('normal_balance', 6);
            $table->foreignId('parent_id')->nullable()->constrained('gl_accounts')->restrictOnDelete();
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['account_type', 'is_active'], 'gl_accounts_type_active_idx');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name', 150);
            $table->string('base_unit', 16);
            $table->string('costing_method', 20)->default('moving_average');
            $table->foreignId('inventory_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name'], 'products_active_name_idx');
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->string('timezone', 64)->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('warehouse_bins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('code', 48);
            $table->string('name', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['warehouse_id', 'code'], 'warehouse_bins_warehouse_code_uq');
            $table->index(['warehouse_id', 'is_active'], 'warehouse_bins_active_idx');
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_bin_id')->constrained('warehouse_bins')->restrictOnDelete();
            $table->decimal('quantity_on_hand', 19, 4)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_bin_id'], 'inventory_stocks_product_bin_uq');
            $table->index(['warehouse_bin_id', 'product_id'], 'inventory_stocks_bin_product_idx');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_bin_id')->constrained('warehouse_bins')->restrictOnDelete();
            $table->foreignId('offset_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 3);
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_cost', 19, 4);
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['product_id', 'occurred_at'], 'inventory_movements_product_date_idx');
            $table->index(['warehouse_bin_id', 'occurred_at'], 'inventory_movements_bin_date_idx');
            $table->index(['reference_type', 'reference_id'], 'inventory_movements_reference_idx');
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('description', 255);
            $table->string('source_type', 64);
            $table->unsignedBigInteger('source_id');
            $table->string('status', 10)->default('posted');
            $table->timestamps();

            $table->unique(['source_type', 'source_id'], 'journal_entries_source_uq');
            $table->index(['entry_date', 'status'], 'journal_entries_date_status_idx');
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->decimal('debit', 19, 4)->default(0);
            $table->decimal('credit', 19, 4)->default(0);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->unique(['journal_entry_id', 'line_number'], 'journal_lines_entry_line_uq');
            $table->index(['account_id', 'journal_entry_id'], 'journal_lines_account_entry_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement("ALTER TABLE gl_accounts ADD CONSTRAINT gl_accounts_normal_balance_chk CHECK (normal_balance IN ('debit', 'credit'))");
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_costing_method_chk CHECK (costing_method IN ('fifo', 'lifo', 'moving_average'))");
            DB::statement('ALTER TABLE inventory_stocks ADD CONSTRAINT inventory_stocks_quantity_chk CHECK (quantity_on_hand >= 0)');
            DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_direction_chk CHECK (direction IN ('in', 'out'))");
            DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_quantity_chk CHECK (quantity > 0 AND unit_cost >= 0)');
            DB::statement("ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_status_chk CHECK (status IN ('draft', 'posted', 'reversed'))");
            DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_amounts_chk CHECK (debit >= 0 AND credit >= 0 AND NOT (debit > 0 AND credit > 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('warehouse_bins');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('products');
        Schema::dropIfExists('gl_accounts');
    }
};
