<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Demo accounts for the presentation environment only.
     */
    public function run(): void
    {
        $accounts = [
            ['name' => 'Station Owner', 'email' => 'owner@alnoor.com', 'role' => UserRole::Owner],
            ['name' => 'Station Manager', 'email' => 'staff@alnoor.com', 'role' => UserRole::Staff],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => 'Demo@12345',
                    'role' => $account['role'],
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
