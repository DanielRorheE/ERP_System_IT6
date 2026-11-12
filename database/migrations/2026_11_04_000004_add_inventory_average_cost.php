<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE inventory_stocks ADD COLUMN average_unit_cost NUMERIC NOT NULL DEFAULT 0');
        } else {
            Schema::table('inventory_stocks', function (Blueprint $table) {
                $table->decimal('average_unit_cost', 19, 4)->default(0);
            });
        }

        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement('ALTER TABLE inventory_stocks ADD CONSTRAINT inventory_stocks_average_cost_chk CHECK (average_unit_cost >= 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE inventory_stocks DROP COLUMN average_unit_cost');
        } else {
            Schema::table('inventory_stocks', function (Blueprint $table) {
                $table->dropColumn('average_unit_cost');
            });
        }
    }
};
