<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // First account. Set TRACKER_ADMIN_* in .env, and change the password in Settings after signing in.
        $user = User::firstOrCreate(
            ['email' => config('tracker.admin.email')],
            ['name' => config('tracker.admin.name'), 'password' => Hash::make(config('tracker.admin.password')), 'email_verified_at' => now()],
        );

        // Personal data lives in database/seeders/Private/, which git ignores.
        if (class_exists(Private\MyPapersSeeder::class)) {
            $this->callWith(Private\MyPapersSeeder::class, ['user' => $user]);
        }
        if (class_exists(Private\MyPublicationsSeeder::class)) {
            $this->callWith(Private\MyPublicationsSeeder::class, ['user' => $user]);
        }
    }
}
