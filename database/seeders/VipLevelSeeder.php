<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VipLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vipLevels = [
            [
                'name' => 'Bronze',
                'level' => 1,
                'min_wagered_amount' => 0,
                'min_deposit_amount' => 0,
                'bonus_multiplier' => 1.00,
                'wagering_reduction' => 0,
                'cashback_percentage' => 0,
                'withdrawal_limit_daily' => 50000,
                'withdrawal_limit_weekly' => 200000,
                'withdrawal_limit_monthly' => 500000,
                'withdrawal_processing_hours' => 24,
                'color' => '#CD7F32',
                'description' => 'Welcome tier for all new players'
            ],
            [
                'name' => 'Silver',
                'level' => 2,
                'min_wagered_amount' => 1000,
                'min_deposit_amount' => 500,
                'bonus_multiplier' => 1.10,
                'wagering_reduction' => 5,
                'cashback_percentage' => 1,
                'withdrawal_limit_daily' => 100000,
                'withdrawal_limit_weekly' => 500000,
                'withdrawal_limit_monthly' => 1000000,
                'withdrawal_processing_hours' => 12,
                'color' => '#C0C0C0',
                'description' => 'Enhanced benefits for active players'
            ],
            [
                'name' => 'Gold',
                'level' => 3,
                'min_wagered_amount' => 5000,
                'min_deposit_amount' => 2000,
                'bonus_multiplier' => 1.25,
                'wagering_reduction' => 10,
                'cashback_percentage' => 2,
                'withdrawal_limit_daily' => 250000,
                'withdrawal_limit_weekly' => 1000000,
                'withdrawal_limit_monthly' => 3000000,
                'withdrawal_processing_hours' => 6,
                'color' => '#FFD700',
                'description' => 'Premium rewards for dedicated players'
            ],
            [
                'name' => 'Platinum',
                'level' => 4,
                'min_wagered_amount' => 20000,
                'min_deposit_amount' => 10000,
                'bonus_multiplier' => 1.50,
                'wagering_reduction' => 15,
                'cashback_percentage' => 3,
                'withdrawal_limit_daily' => 500000,
                'withdrawal_limit_weekly' => 2500000,
                'withdrawal_limit_monthly' => 10000000,
                'withdrawal_processing_hours' => 4,
                'color' => '#E5E4E2',
                'description' => 'Elite status with exclusive benefits'
            ],
            [
                'name' => 'Diamond',
                'level' => 5,
                'min_wagered_amount' => 100000,
                'min_deposit_amount' => 50000,
                'bonus_multiplier' => 2.00,
                'wagering_reduction' => 20,
                'cashback_percentage' => 5,
                'withdrawal_limit_daily' => 1000000,
                'withdrawal_limit_weekly' => 5000000,
                'withdrawal_limit_monthly' => 20000000,
                'withdrawal_processing_hours' => 2,
                'color' => '#B9F2FF',
                'description' => 'Ultimate VIP experience for high rollers'
            ],
        ];

        foreach ($vipLevels as $level) {
            DB::table('vip_levels')->insert(array_merge($level, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
