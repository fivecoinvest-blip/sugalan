<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            [
                'name' => 'GCash',
                'code' => 'gcash',
                'type' => 'manual',
                'description' => 'GCash mobile wallet - Manual processing with admin approval',
                'min_deposit' => 100.00,
                'max_deposit' => 50000.00,
                'min_withdrawal' => 200.00,
                'max_withdrawal' => 50000.00,
                'is_enabled' => true,
                'supports_deposits' => true,
                'supports_withdrawals' => true,
                'display_order' => 1,
                'icon_url' => '/images/payment-methods/gcash.png',
            ],
        ];

        foreach ($paymentMethods as $method) {
            DB::table('payment_methods')->insert(array_merge($method, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
