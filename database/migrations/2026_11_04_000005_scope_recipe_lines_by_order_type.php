<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addedScopeColumn = ! Schema::hasColumn('recipe_lines', 'applies_to');

        if ($addedScopeColumn) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE recipe_lines ADD COLUMN applies_to VARCHAR(12) NOT NULL DEFAULT 'all'");
                DB::statement('ALTER TABLE recipe_lines ADD UNIQUE INDEX recipe_lines_ingredient_scope_uq (recipe_id, ingredient_product_id, applies_to)');
                DB::statement('ALTER TABLE recipe_lines DROP INDEX recipe_lines_ingredient_uq');
            } elseif (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE recipe_lines ADD COLUMN applies_to VARCHAR(12) NOT NULL DEFAULT 'all'");
                DB::statement('ALTER TABLE recipe_lines ADD CONSTRAINT recipe_lines_ingredient_scope_uq UNIQUE (recipe_id, ingredient_product_id, applies_to)');
                DB::statement('ALTER TABLE recipe_lines DROP CONSTRAINT recipe_lines_ingredient_uq');
            } else {
                Schema::table('recipe_lines', function (Blueprint $table) {
                    $table->dropUnique('recipe_lines_ingredient_uq');
                    $table->string('applies_to', 12)->default('all');
                    $table->unique(['recipe_id', 'ingredient_product_id', 'applies_to'], 'recipe_lines_ingredient_scope_uq');
                });
            }

            DB::table('recipe_lines')
                ->whereIn('ingredient_product_id', DB::table('products')->whereIn('sku', ['CUP-16OZ', 'LID-16OZ'])->select('id'))
                ->whereIn('recipe_id', DB::table('recipes')
                    ->join('menu_items', 'menu_items.id', '=', 'recipes.menu_item_id')
                    ->whereIn('menu_items.sku', ['DRINK-MATCHA-LATTE', 'DRINK-STRAW-MATCHA', 'DRINK-OAT-MATCHA'])
                    ->select('recipes.id'))
                ->update(['applies_to' => 'takeaway']);
        }

        if ($addedScopeColumn && in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement("ALTER TABLE recipe_lines ADD CONSTRAINT recipe_lines_scope_chk CHECK (applies_to IN ('all', 'takeaway', 'dine_in', 'delivery'))");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('recipe_lines', 'applies_to')) {
            if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
                DB::statement(DB::getDriverName() === 'mysql'
                    ? 'ALTER TABLE recipe_lines DROP CHECK recipe_lines_scope_chk'
                    : 'ALTER TABLE recipe_lines DROP CONSTRAINT recipe_lines_scope_chk');
            }

            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE recipe_lines ADD UNIQUE INDEX recipe_lines_ingredient_uq (recipe_id, ingredient_product_id)');
                DB::statement('ALTER TABLE recipe_lines DROP INDEX recipe_lines_ingredient_scope_uq');
                DB::statement('ALTER TABLE recipe_lines DROP COLUMN applies_to');
            } elseif (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE recipe_lines ADD CONSTRAINT recipe_lines_ingredient_uq UNIQUE (recipe_id, ingredient_product_id)');
                DB::statement('ALTER TABLE recipe_lines DROP CONSTRAINT recipe_lines_ingredient_scope_uq');
                DB::statement('ALTER TABLE recipe_lines DROP COLUMN applies_to');
            } else {
                Schema::table('recipe_lines', function (Blueprint $table) {
                    $table->dropUnique('recipe_lines_ingredient_scope_uq');
                    $table->dropColumn('applies_to');
                    $table->unique(['recipe_id', 'ingredient_product_id'], 'recipe_lines_ingredient_uq');
                });
            }
        }
    }
};
