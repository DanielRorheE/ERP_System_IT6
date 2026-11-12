<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = Schema::hasTable('users');
        $ownerEmail = config('manager.user_email');

        if ($users && DB::table('users')->exists() && (! is_string($ownerEmail) || ! DB::table('users')->where('email', $ownerEmail)->exists())) {
            throw new RuntimeException('Set MANAGER_SHARED_PIN_USER_EMAIL to an existing owner account before running this destructive migration.');
        }

        foreach ([
            'cafe_settings',
            'cafe_payments',
            'cafe_order_lines',
            'cafe_orders',
            'purchase_receipt_lines',
            'purchase_receipts',
            'purchase_order_lines',
            'purchase_orders',
            'suppliers',
            'recipe_lines',
            'recipes',
            'menu_items',
            'menu_categories',
            'inventory_movements',
            'inventory_stocks',
            'warehouse_bins',
            'warehouses',
            'journal_lines',
            'journal_entries',
            'roles',
            'products',
            'product_categories',
            'gl_accounts',
            'sessions',
            'password_reset_tokens',
            'jobs',
            'job_batches',
            'failed_jobs',
            'cache_locks',
            'cache',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        if ($users && is_string($ownerEmail) && $ownerEmail !== '') {
            DB::table('users')->where('email', '<>', $ownerEmail)->delete();
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn(['name', 'email_verified_at', 'remember_token', 'created_at', 'updated_at']);
            });
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50)->unique();
        });

        $ownerRoleId = DB::table('roles')->insertGetId(['name' => 'owner']);

        Schema::table('users', function (Blueprint $table) use ($ownerRoleId): void {
            $table->foreignId('role_id')->default($ownerRoleId)->constrained('roles');
            $table->index('role_id');
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->decimal('price', 12, 2);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('stock_quantity', 12, 4)->default(0);
            $table->boolean('is_available')->default(true);
            $table->index(['is_available', 'name']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->decimal('total_amount', 14, 2);
            $table->decimal('cost_total', 14, 2)->default(0);
            $table->string('status', 12);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('This migration removes operational tables and data and cannot be reversed. Restore a database backup instead.');
    }
};
