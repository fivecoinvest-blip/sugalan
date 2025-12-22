<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed VIP levels first (required by users)
        $this->call([
            VipLevelSeeder::class,
            PaymentMethodSeeder::class,
            AdminUserSeeder::class,
        ]);
        
        // Uncomment to create test users after models are ready
        // User::factory(10)->create();
        
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
