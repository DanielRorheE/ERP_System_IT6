<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class OwnerUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('manager.user_email');

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Set MANAGER_SHARED_PIN_USER_EMAIL to the owner email before seeding.');
        }

        $ownerRole = Role::firstOrCreate(['name' => 'owner']);

        User::firstOrCreate(
            ['email' => $email],
            ['password' => Hash::make(Str::random(64)), 'role_id' => $ownerRole->id],
        );
    }
}
