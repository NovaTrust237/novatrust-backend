<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // L'admin se connecte avec phone='admin' / password='admin123'
        $admin = User::updateOrCreate(
            ['phone' => 'admin'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'activated' => true,
            ]
        );

        Wallet::firstOrCreate(
            ['user_id' => $admin->id],
            ['balance_cfa' => 0, 'virtual_balance_cfa' => 0]
        );
    }
}
