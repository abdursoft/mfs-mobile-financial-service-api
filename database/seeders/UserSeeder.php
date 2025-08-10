<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Create admin
        $admin = User::create([
            'name' => 'Admin User',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'pin' => Hash::make('1234'),
            'role' => 'admin',
            'phone_verified_at' => now(),
        ]);
        Wallet::create(['user_id' => $admin->id, 'balance' => 50000]);

        // Create normal users
        for ($i = 1; $i <= 3; $i++) {
            $user = User::create([
                'name' => 'Normal User '.$i,
                'phone' => '0171000000' . $i,
                'password' => Hash::make('password'),
                'pin' => Hash::make('1234'),
                'role' => 'user',
                'phone_verified_at' => now(),
            ]);
            Wallet::create(['user_id' => $user->id, 'balance' => rand(1000,999999)]);
        }

        // Create agents
        for ($i = 1; $i <= 2; $i++) {
            $agent = User::create([
                'name' => 'Agent '.$i,
                'phone' => '0172000000' . $i,
                'password' => Hash::make('password'),
                'pin' => Hash::make('1234'),
                'role' => 'agent',
                'phone_verified_at' => now(),
            ]);
            Wallet::create(['user_id' => $agent->id, 'balance' => rand(10000,9999999)]);
        }

        // Create merchants
        for ($i = 1; $i <= 3; $i++) {
            $merchant = User::create([
                'name' => 'Merchant '.$i,
                'phone' => '0173000000' . $i,
                'password' => Hash::make('password'),
                'pin' => Hash::make('1234'),
                'role' => 'merchant',
                'phone_verified_at' => now(),
            ]);
            Wallet::create(['user_id' => $merchant->id, 'balance' => rand(10000,9999999)]);
        }
    }
}
