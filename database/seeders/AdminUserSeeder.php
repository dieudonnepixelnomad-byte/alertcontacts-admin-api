<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'dieudonnegwet86@alertcontacts.net'],
            [
                'name' => 'SuperAdmin',
                'email_verified_at' => now(),
                'password' => Hash::make('Admin@2025#Secure!'),
                'is_admin' => true,
            ]
        );
    }
}
