<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SlotProvider;
use Illuminate\Support\Facades\DB;

class AyutSlotProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Check if provider already exists
            $existingProvider = SlotProvider::where('code', 'AYUT')->first();

            if ($existingProvider) {
                // Update existing provider
                $existingProvider->update([
                    'name' => 'AYUT Gaming',
                    'api_url' => 'https://jsgame.live',
                    'agency_uid' => '4fcbdc0bf258b53d8fa02d85c6ddbdf6',
                    'aes_key' => 'fd1e3a6a4b3dc050c7f9238c49bf5f56',
                    'player_prefix' => 'hc57f0',
                    'is_active' => true,
                    'config' => json_encode([
                        'session_timeout_minutes' => 30,
                        'currency' => 'PHP',
                        'supports_seamless_wallet' => true,
                        'supports_transfer_wallet' => false,
                        'supports_demo_mode' => true,
                    ]),
                ]);

                $this->command->info('✓ Updated existing AYUT provider');
            } else {
                // Create new provider
                SlotProvider::create([
                    'code' => 'AYUT',
                    'name' => 'AYUT Gaming',
                    'api_url' => 'https://jsgame.live',
                    'agency_uid' => '4fcbdc0bf258b53d8fa02d85c6ddbdf6',
                    'aes_key' => 'fd1e3a6a4b3dc050c7f9238c49bf5f56',
                    'player_prefix' => 'hc57f0',
                    'is_active' => true,
                    'config' => json_encode([
                        'session_timeout_minutes' => 30,
                        'currency' => 'PHP',
                        'supports_seamless_wallet' => true,
                        'supports_transfer_wallet' => false,
                        'supports_demo_mode' => true,
                    ]),
                ]);

                $this->command->info('✓ Created AYUT provider');
            }

            DB::commit();

            $this->command->info('');
            $this->command->info('AYUT Slot Provider Configuration:');
            $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->command->info('Provider Code:   AYUT');
            $this->command->info('Provider Name:   AYUT Gaming');
            $this->command->info('API URL:         https://jsgame.live');
            $this->command->info('Agency UID:      4fcbdc0bf258b53d8fa02d85c6ddbdf6');
            $this->command->info('AES Key:         fd1e3a6a4b3dc050c7f9238c49bf5f56');
            $this->command->info('Player Prefix:   hc57f0');
            $this->command->info('Status:          Active');
            $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->command->info('');
            $this->command->info('Next steps:');
            $this->command->info('1. Sync games: POST /api/admin/slots/providers/{id}/sync');
            $this->command->info('2. View in admin: /admin/slots/providers');
            $this->command->info('3. View games: /admin/slots/games');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Failed to seed AYUT provider: ' . $e->getMessage());
            throw $e;
        }
    }
}
