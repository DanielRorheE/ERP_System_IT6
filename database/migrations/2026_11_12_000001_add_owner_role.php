<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 50)->unique();
            });
        }

        $ownerRoleId = DB::table('roles')->where('name', 'owner')->value('id');
        $ownerRoleId ??= DB::table('roles')->insertGetId(['name' => 'owner']);

        if (! Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) use ($ownerRoleId): void {
                $table->foreignId('role_id')->nullable()->after('password')->constrained('roles');
                $table->index('role_id');
            });
        }

        DB::table('users')->whereNull('role_id')->update(['role_id' => $ownerRoleId]);
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropForeign(['role_id']);
                $table->dropIndex(['role_id']);
                $table->dropColumn('role_id');
            });
        }

        Schema::dropIfExists('roles');
    }
};
