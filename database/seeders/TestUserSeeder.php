<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Str;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create test user with phone authentication
        $user1 = User::create([
            'phone_number' => '+639171234567',
            'username' => 'testuser',
            'password' => Hash::make('password123'),
            'auth_method' => 'phone',
            'referral_code' => Str::random(8),
            'email_verified_at' => now(),
        ]);
        
        // Create wallet for user 1
        $user1->wallet()->create([
            'real_balance' => 1000.00, // Give some starting balance for testing
            'bonus_balance' => 0,
            'locked_balance' => 0,
        ]);

        // Create another test user
        $user2 = User::create([
            'phone_number' => '+639181234567',
            'username' => 'demo',
            'password' => Hash::make('demo123'),
            'auth_method' => 'phone',
            'referral_code' => Str::random(8),
            'email_verified_at' => now(),
        ]);
        
        // Create wallet for user 2
        $user2->wallet()->create([
            'real_balance' => 500.00, // Give some starting balance for testing
            'bonus_balance' => 0,
            'locked_balance' => 0,
        ]);

        echo "✓ Test users created:\n";
        echo "  User 1: +639171234567 / password123 (Balance: ₱1,000)\n";
        echo "  User 2: +639181234567 / demo123 (Balance: ₱500)\n";
    }
}
