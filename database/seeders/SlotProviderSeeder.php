<?php

namespace Database\Seeders;

use App\Models\SlotProvider;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SlotProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // AYUT Gaming Platform (Test Environment)
        SlotProvider::create([
            'name' => 'AYUT',
            'code' => 'ayut',
            'agency_uid' => '4fcbdc0bf258b53d8fa02d85c6ddbdf6',
            'aes_key' => 'fd1e3a6a4b3dc050c7f9238c49bf5f56',
            'player_prefix' => 'hc57f0',
            'api_url' => 'https://jsgame.live',
            'callback_url' => null, // Will be set when we deploy
            'is_active' => true,
            'config' => [
                'environment' => 'test',
                'supports_demo' => true,
                'seamless_wallet' => true,
                'transfer_wallet' => true,
                'session_timeout' => 30, // minutes
                'currency' => 'PHP',
            ],
        ]);
    }
}
