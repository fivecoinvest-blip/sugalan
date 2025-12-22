<?php

namespace Database\Seeders;

use App\Models\VipPromotion;
use Illuminate\Database\Seeder;

class VipPromotionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $promotions = [
            [
                'title' => '🎁 Welcome Bonus - Silver VIPs',
                'description' => 'Exclusive ₱500 bonus for Silver VIP members',
                'type' => 'bonus',
                'min_vip_level' => 2, // Silver
                'max_vip_level' => 2,
                'value' => 500,
                'percentage' => null,
                'wagering_multiplier' => 20,
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
                'max_uses' => 100,
                'max_uses_per_user' => 1,
                'status' => 'active',
                'terms' => 'Valid for first-time Silver VIP members only. Must be wagered 20x before withdrawal.',
            ],
            [
                'title' => '💎 Gold VIP Weekly Bonus',
                'description' => 'Get ₱1,000 bonus every week as a Gold VIP',
                'type' => 'bonus',
                'min_vip_level' => 3, // Gold
                'max_vip_level' => 3,
                'value' => 1000,
                'percentage' => null,
                'wagering_multiplier' => 15,
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
                'max_uses' => null, // Unlimited
                'max_uses_per_user' => 4, // Once per week for a month
                'status' => 'active',
                'terms' => 'Available once per week for Gold VIP members. 15x wagering requirement.',
            ],
            [
                'title' => '👑 Platinum VIP Cashback',
                'description' => '10% cashback on your weekly losses',
                'type' => 'cashback',
                'min_vip_level' => 4, // Platinum
                'max_vip_level' => 4,
                'value' => 0,
                'percentage' => 10,
                'wagering_multiplier' => 10,
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
                'max_uses' => null,
                'max_uses_per_user' => 4,
                'status' => 'active',
                'terms' => 'Cashback calculated on net losses from previous week. 10x wagering required.',
            ],
            [
                'title' => '💠 Diamond VIP Premium Bonus',
                'description' => 'Exclusive ₱5,000 monthly bonus for Diamond VIPs',
                'type' => 'bonus',
                'min_vip_level' => 5, // Diamond
                'max_vip_level' => 5,
                'value' => 5000,
                'percentage' => null,
                'wagering_multiplier' => 10,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(3),
                'max_uses' => null,
                'max_uses_per_user' => 3, // Once per month for 3 months
                'status' => 'active',
                'terms' => 'Exclusive to Diamond VIP members. Only 10x wagering requirement!',
            ],
            [
                'title' => '🎰 All VIPs - Weekend Reload',
                'description' => '20% bonus on weekend deposits',
                'type' => 'bonus',
                'min_vip_level' => 2, // Silver and above
                'max_vip_level' => null,
                'value' => 0,
                'percentage' => 20,
                'wagering_multiplier' => 25,
                'starts_at' => now(),
                'expires_at' => now()->addWeeks(4),
                'max_uses' => null,
                'max_uses_per_user' => 8, // 2 per weekend for a month
                'status' => 'active',
                'terms' => 'Available Friday-Sunday only. 20% bonus on deposits of ₱1,000 or more. 25x wagering.',
            ],
            [
                'title' => '🔥 Limited Time - Double Cashback',
                'description' => '15% cashback on losses - Limited time only!',
                'type' => 'cashback',
                'min_vip_level' => 3, // Gold and above
                'max_vip_level' => null,
                'value' => 0,
                'percentage' => 15,
                'wagering_multiplier' => 12,
                'starts_at' => now(),
                'expires_at' => now()->addWeeks(2),
                'max_uses' => 200,
                'max_uses_per_user' => 2,
                'status' => 'active',
                'terms' => 'Limited to first 200 claims. 15% cashback on net losses. 12x wagering requirement.',
            ],
        ];

        foreach ($promotions as $promo) {
            VipPromotion::create($promo);
        }
    }
}
