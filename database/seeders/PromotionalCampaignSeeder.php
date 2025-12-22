<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PromotionalCampaign;

class PromotionalCampaignSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = [
            [
                'title' => '🎁 Welcome Bonus 100%',
                'description' => 'Get 100% match bonus on your first deposit up to ₱5,000!',
                'code' => 'WELCOME100',
                'type' => 'reload',
                'value' => 0,
                'percentage' => 100,
                'min_deposit' => 100,
                'max_bonus' => 5000,
                'wagering_multiplier' => 30,
                'min_vip_level' => 1,
                'max_vip_level' => null,
                'max_claims_total' => null,
                'max_claims_per_user' => 1,
                'starts_at' => now(),
                'expires_at' => null,
                'status' => 'active',
                'terms' => 'Valid for first-time depositors only. 30x wagering requirement. Expires in 7 days after claim.',
            ],
            [
                'title' => '💰 Weekend Reload 50%',
                'description' => 'Get 50% bonus on weekend deposits up to ₱2,500',
                'code' => 'WEEKEND50',
                'type' => 'reload',
                'value' => 0,
                'percentage' => 50,
                'min_deposit' => 500,
                'max_bonus' => 2500,
                'wagering_multiplier' => 25,
                'min_vip_level' => 1,
                'max_vip_level' => null,
                'max_claims_total' => null,
                'max_claims_per_user' => 4, // Once per weekend
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
                'status' => 'active',
                'terms' => 'Available every Saturday and Sunday. Minimum ₱500 deposit. 25x wagering requirement.',
            ],
            [
                'title' => '🎰 Free ₱100 No Deposit',
                'description' => 'Claim ₱100 free bonus, no deposit required!',
                'code' => 'FREE100',
                'type' => 'bonus',
                'value' => 100,
                'percentage' => null,
                'min_deposit' => null,
                'max_bonus' => null,
                'wagering_multiplier' => 40,
                'min_vip_level' => 2, // Silver and above
                'max_vip_level' => null,
                'max_claims_total' => 1000,
                'max_claims_per_user' => 1,
                'starts_at' => now(),
                'expires_at' => now()->addWeek(),
                'status' => 'active',
                'terms' => 'Limited to 1,000 claims. Silver VIP and above only. 40x wagering requirement.',
            ],
            [
                'title' => '💎 VIP Exclusive ₱500',
                'description' => 'Exclusive ₱500 bonus for Gold VIP members',
                'code' => 'GOLDVIP500',
                'type' => 'bonus',
                'value' => 500,
                'percentage' => null,
                'min_deposit' => null,
                'max_bonus' => null,
                'wagering_multiplier' => 20,
                'min_vip_level' => 3, // Gold only
                'max_vip_level' => 3,
                'max_claims_total' => null,
                'max_claims_per_user' => 1,
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
                'status' => 'active',
                'terms' => 'Exclusive to Gold VIP members. Only 20x wagering requirement! Limited time offer.',
            ],
            [
                'title' => '💸 Cashback Week',
                'description' => 'Get 10% cashback on your weekly losses',
                'code' => 'CASHBACK10',
                'type' => 'cashback',
                'value' => 0,
                'percentage' => 10,
                'min_deposit' => null,
                'max_bonus' => 5000,
                'wagering_multiplier' => 5, // Low wagering for cashback
                'min_vip_level' => 1,
                'max_vip_level' => null,
                'max_claims_total' => null,
                'max_claims_per_user' => 4, // Once per week
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
                'status' => 'active',
                'terms' => 'Calculated weekly on losses. Max ₱5,000 cashback. Only 5x wagering requirement.',
            ],
            [
                'title' => '🎯 Deposit Streak Bonus',
                'description' => 'Deposit 5 days in a row, get ₱1,000 bonus!',
                'code' => 'STREAK5',
                'type' => 'bonus',
                'value' => 1000,
                'percentage' => null,
                'min_deposit' => 500,
                'max_bonus' => null,
                'wagering_multiplier' => 15,
                'min_vip_level' => 1,
                'max_vip_level' => null,
                'max_claims_total' => null,
                'max_claims_per_user' => 1,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(2),
                'status' => 'active',
                'terms' => 'Deposit minimum ₱500 for 5 consecutive days to qualify. Bonus awarded automatically.',
            ],
            [
                'title' => '🏆 High Roller ₱10,000',
                'description' => 'Deposit ₱10,000+, get ₱2,000 bonus!',
                'code' => 'HIGHROLLER',
                'type' => 'bonus',
                'value' => 2000,
                'percentage' => null,
                'min_deposit' => 10000,
                'max_bonus' => null,
                'wagering_multiplier' => 20,
                'min_vip_level' => 3, // Gold and above
                'max_vip_level' => null,
                'max_claims_total' => null,
                'max_claims_per_user' => 10, // Multiple uses
                'starts_at' => now(),
                'expires_at' => now()->addMonths(3),
                'status' => 'active',
                'terms' => 'Minimum ₱10,000 deposit required. Can be claimed up to 10 times. Gold VIP and above only.',
            ],
            [
                'title' => '⚡ Flash Sale 200%',
                'description' => '200% bonus for the next 24 hours only!',
                'code' => 'FLASH200',
                'type' => 'reload',
                'value' => 0,
                'percentage' => 200,
                'min_deposit' => 1000,
                'max_bonus' => 10000,
                'wagering_multiplier' => 35,
                'min_vip_level' => 1,
                'max_vip_level' => null,
                'max_claims_total' => 500,
                'max_claims_per_user' => 1,
                'starts_at' => now(),
                'expires_at' => now()->addDay(),
                'status' => 'active',
                'terms' => '24-hour flash sale! Limited to 500 claims. 35x wagering requirement.',
            ],
        ];

        foreach ($campaigns as $campaign) {
            PromotionalCampaign::create($campaign);
        }

        $this->command->info('Promotional campaigns seeded successfully!');
    }
}
