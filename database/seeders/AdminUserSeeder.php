<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\AdminUser;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Super Admin
        AdminUser::create([
            'username' => 'superadmin',
            'full_name' => 'Super Admin',
            'email' => 'admin@sugalan.com',
            'password' => Hash::make('Admin123!@#'),
            'role' => 'admin',
            'permissions' => [
                'view_dashboard',
                'manage_users',
                'manage_deposits',
                'manage_withdrawals',
                'manage_bonuses',
                'manage_games',
                'view_reports',
                'manage_admins',
                'manage_settings',
            ],
            'is_active' => true,
        ]);

        // Finance Manager
        AdminUser::create([
            'username' => 'finance',
            'full_name' => 'Finance Manager',
            'email' => 'finance@sugalan.com',
            'password' => Hash::make('Finance123!@#'),
            'role' => 'finance',
            'permissions' => [
                'view_dashboard',
                'manage_deposits',
                'manage_withdrawals',
                'view_reports',
            ],
            'is_active' => true,
        ]);

        // Support Agent
        AdminUser::create([
            'username' => 'support',
            'full_name' => 'Support Agent',
            'email' => 'support@sugalan.com',
            'password' => Hash::make('Support123!@#'),
            'role' => 'support',
            'permissions' => [
                'view_dashboard',
                'manage_users',
                'view_reports',
            ],
            'is_active' => true,
        ]);

        // Game Manager
        AdminUser::create([
            'username' => 'gamemanager',
            'full_name' => 'Game Manager',
            'email' => 'games@sugalan.com',
            'password' => Hash::make('Games123!@#'),
            'role' => 'developer',
            'permissions' => [
                'view_dashboard',
                'manage_games',
                'view_reports',
            ],
            'is_active' => true,
        ]);
    }
}
