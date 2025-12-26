<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SlotProvider;
use App\Models\SlotGame;
use Illuminate\Support\Facades\DB;

class JiliGamesSeeder extends Seeder
{
    /**
     * Seed JILI slot games through AYUT provider
     * 
     * JILI games are provided by AYUT Gaming platform
     */
    public function run(): void
    {
        // Get AYUT provider (JILI games are provided through AYUT)
        $provider = SlotProvider::where('code', 'AYUT')->first();

        if (!$provider) {
            $this->command->error('❌ AYUT provider not found. Please run AyutSlotProviderSeeder first.');
            $this->command->info('Run: php artisan db:seed --class=AyutSlotProviderSeeder');
            return;
        }

        $this->command->info('Importing JILI slot games through AYUT provider...');
        $this->command->info('');

        DB::beginTransaction();

        try {
            // JILI Slot Games Data (135 games)
            $jiliGames = $this->getJiliGamesData();

            $imported = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($jiliGames as $gameData) {
                $exists = SlotGame::where('provider_id', $provider->id)
                    ->where('game_id', $gameData['uid'])
                    ->exists();

                if ($exists) {
                    // Update existing game
                    SlotGame::where('provider_id', $provider->id)
                        ->where('game_id', $gameData['uid'])
                        ->update([
                            'name' => $gameData['name'],
                            'category' => 'slots',
                            'manufacturer' => 'JILI',
                            'thumbnail_url' => $gameData['image'] ?? null,
                            'min_bet' => 1.00,
                            'max_bet' => 10000.00,
                            'rtp' => 96.50, // Default RTP for JILI games
                            'is_active' => true,
                            'metadata' => json_encode([
                                'manufacturer' => 'JILI',
                                'game_code' => $gameData['code'],
                            ]),
                            'updated_at' => now(),
                        ]);
                    $updated++;
                } else {
                    // Create new game
                    SlotGame::create([
                        'provider_id' => $provider->id,
                        'game_id' => $gameData['uid'],
                        'name' => $gameData['name'],
                        'category' => 'slots',
                        'manufacturer' => 'JILI',
                        'thumbnail_url' => $gameData['image'] ?? null,
                        'min_bet' => 1.00,
                        'max_bet' => 10000.00,
                        'rtp' => 96.50,
                        'is_active' => true,
                        'metadata' => json_encode([
                            'manufacturer' => 'JILI',
                            'game_code' => $gameData['code'],
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $imported++;
                }

                if (($imported + $updated) % 20 == 0) {
                    $this->command->info("Processed {$imported} new, {$updated} updated games...");
                }
            }

            DB::commit();

            $this->command->info('');
            $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->command->info('✓ JILI Games Import Complete');
            $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->command->info("Provider:       AYUT Gaming");
            $this->command->info("Manufacturer:   JILI");
            $this->command->info("Games Imported: {$imported}");
            $this->command->info("Games Updated:  {$updated}");
            $this->command->info("Total JILI:     " . count($jiliGames));
            $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->command->info('');
            $this->command->info('Next steps:');
            $this->command->info('1. View games: /admin/slots/games?provider_id=' . $provider->id);
            $this->command->info('2. Filter by JILI: Search "manufacturer:JILI" in metadata');
            $this->command->info('3. Move images: Copy jili_image folder to public/storage');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error importing JILI games: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get JILI games data
     */
    private function getJiliGamesData(): array
    {
        return [
            ['code' => '2', 'name' => 'Chin Shi Huang', 'uid' => '24da72b49b0dd0e5cbef9579d09d8981', 'image' => 'jili_image/code_2 Chin Shi Huang.png'],
            ['code' => '4', 'name' => 'God Of Martial', 'uid' => '21ef8a7ddd39836979170a2e7584e333', 'image' => 'jili_image/code_4 God Of Martial.png'],
            ['code' => '5', 'name' => 'Hot Chilli', 'uid' => 'c845960c81d27d7880a636424e53964d', 'image' => 'jili_image/code_5 Hot Chilli.png'],
            ['code' => '6', 'name' => 'Fortune Tree', 'uid' => '6a7e156ceec5c581cd6b9251854fe504', 'image' => 'jili_image/code_6 Fortune Tree.png'],
            ['code' => '9', 'name' => 'War Of Dragons', 'uid' => '4b1d7ffaf9f66e6152ea93a6d0e4215b', 'image' => 'jili_image/code_9 War Of Dragons.png'],
            ['code' => '13', 'name' => 'Lucky Ball', 'uid' => '893669898cd25d9da589a384f1d004df', 'image' => 'jili_image/code_13 Lucky Ball.png'],
            ['code' => '14', 'name' => 'Hyper Burst', 'uid' => 'a47b17970036b37c1347484cf6956920', 'image' => 'jili_image/code_14 Hyper Burst.png'],
            ['code' => '16', 'name' => 'Jungle King', 'uid' => '4db0ec24ff55a685573c888efed47d7f', 'image' => 'jili_image/code_16 Jungle King.png'],
            ['code' => '17', 'name' => 'Shanghai Beauty', 'uid' => '795d0cae623cbf34d7f1aa93bbcded28', 'image' => 'jili_image/code_17 Shanghai Beauty.png'],
            ['code' => '21', 'name' => 'Fa Fa Fa', 'uid' => '54c41adcf43fdb6d385e38bc09cd77ca', 'image' => 'jili_image/code_21 Fa Fa Fa.png'],
            ['code' => '23', 'name' => 'Candy Baby', 'uid' => '2cc3b68cbcfacac2f7ef2fe19abc3c22', 'image' => null],
            ['code' => '26', 'name' => 'Hawaii Beauty', 'uid' => '6409b758471b6df30c6b137b49f4d92e', 'image' => 'jili_image/code_26 Hawaii Beauty.png'],
            ['code' => '27', 'name' => 'SevenSevenSeven', 'uid' => '61d46add6841aad4758288d68015eca6', 'image' => 'jili_image/code_27 SevenSevenSeven.png'],
            ['code' => '30', 'name' => 'Bubble Beauty', 'uid' => 'a78d2ed972aab8ba06181cc43c54a425', 'image' => 'jili_image/code_30 Bubble Beauty.png'],
            ['code' => '33', 'name' => 'FortunePig', 'uid' => '8488c76ee2afb8077fbd7eec62721215', 'image' => 'jili_image/code_33 FortunePig.png'],
            ['code' => '35', 'name' => 'Crazy777', 'uid' => '8c62471fd4e28c084a61811a3958f7a1', 'image' => 'jili_image/code_35 Crazy777.png'],
            ['code' => '36', 'name' => 'Bao boon chin', 'uid' => '8c4ebb3dc5dcf7b7fe6a26d5aadd2c3d', 'image' => 'jili_image/code_36 Bao boon chin.png'],
            ['code' => '37', 'name' => 'Night City', 'uid' => '78e29705f7c6084114f46a0aeeea1372', 'image' => 'jili_image/code_37 Night City.png'],
            ['code' => '38', 'name' => 'Fengshen', 'uid' => '09699fd0de13edbb6c4a194d7494640b', 'image' => 'jili_image/code_38 Fengshen.png'],
            ['code' => '40', 'name' => 'Crazy FaFaFa', 'uid' => 'a57a8d5176b54d4c825bd1eee8ab34df', 'image' => 'jili_image/code_40 Crazy FaFaFa.png'],
            ['code' => '43', 'name' => 'XiYangYang', 'uid' => '5a962d0e31e0d4c0798db5f331327e4f', 'image' => 'jili_image/code_43 XiYangYang.png'],
            ['code' => '44', 'name' => 'DiamondParty', 'uid' => '48d598e922e8c60643218ccda302af08', 'image' => 'jili_image/code_44 DiamondParty.png'],
            ['code' => '45', 'name' => 'Golden Bank', 'uid' => 'c3f86b78938eab1b7f34159d98796e88', 'image' => 'jili_image/code_45 Golden Bank.png'],
            ['code' => '46', 'name' => 'Dragon Treasure', 'uid' => 'c6955c14f6c28a6c2a0c28274fec7520', 'image' => 'jili_image/code_46 Dragon Treasure.png'],
            ['code' => '47', 'name' => 'Charge Buffalo', 'uid' => '984615c9385c42b3dad0db4a9ef89070', 'image' => 'jili_image/code_47 Charge Buffalo.png'],
            ['code' => '48', 'name' => 'Lucky Goldbricks', 'uid' => 'd84ef530121953240116e3b2e93f6af4', 'image' => 'jili_image/code_48 Lucky Goldbricks.png'],
            ['code' => '49', 'name' => 'Super Ace', 'uid' => 'bdfb23c974a2517198c5443adeea77a8', 'image' => 'jili_image/code_49 Super Ace.png'],
            ['code' => '51', 'name' => 'Money Coming', 'uid' => 'db249defce63610fccabfa829a405232', 'image' => 'jili_image/code_51 Money Coming.png'],
            ['code' => '58', 'name' => 'Golden Queen', 'uid' => '8de99455c2f23f6827666fd798eb80ef', 'image' => 'jili_image/code_58 Golden Queen.png'],
            ['code' => '76', 'name' => 'Party Night', 'uid' => 'd505541d522aa5ca01fc5e97cfcf2116', 'image' => 'jili_image/code_76 Party Night.png'],
            ['code' => '77', 'name' => 'Boxing King', 'uid' => '981f5f9675002fbeaaf24c4128b938d7', 'image' => 'jili_image/code_77 Boxing King.png'],
            ['code' => '78', 'name' => 'Secret Treasure', 'uid' => '1d1f267e3a078ade8e5ccd56582ac94f', 'image' => 'jili_image/code_78 Secret Treasure.png'],
            ['code' => '85', 'name' => 'Pharaoh Treasure', 'uid' => 'c7a69ab382bd1ff0e6eb65b90a793bdd', 'image' => 'jili_image/code_85 Pharaoh Treasure.png'],
            ['code' => '87', 'name' => 'Book of Gold', 'uid' => '6b283c434fd44250d83b7c2420f164f9', 'image' => 'jili_image/code_87 Book of Gold.png'],
            ['code' => '91', 'name' => 'Lucky Coming', 'uid' => 'ba858ec8e3b5e2b4da0d16b3a2330ca7', 'image' => 'jili_image/code_91 Lucky Coming.png'],
            ['code' => '92', 'name' => 'Crazy Hunter', 'uid' => '69082f28fcd46cbfd10ce7a0051f24b6', 'image' => 'jili_image/code_92 Crazy Hunter.png'],
            ['code' => '100', 'name' => 'Super Rich', 'uid' => 'b92f491a63ac84b106b056e9d46d35c5', 'image' => 'jili_image/code_100 Super Rich.png'],
            ['code' => '101', 'name' => 'Medusa', 'uid' => '2c17b7c4e2ce5b8bebf4bd10e3e958d7', 'image' => 'jili_image/code_101 Medusa.png'],
            ['code' => '102', 'name' => 'Roma X', 'uid' => 'e5ff8e72418fcc608d72ea21cc65fb70', 'image' => 'jili_image/code_102 Roma X.png'],
            ['code' => '103', 'name' => 'Golden Empire', 'uid' => '490096198e28f770a3f85adb6ee49e0f', 'image' => 'jili_image/code_103 Golden Empire.png'],
            ['code' => '106', 'name' => 'TWIN WINS', 'uid' => 'c74b3cbda5d16f77523e41c25104e602', 'image' => 'jili_image/code_106 TWIN WINS.png'],
            ['code' => '108', 'name' => 'Magic Lamp', 'uid' => '582a58791928760c28ec4cef3392a49f', 'image' => 'jili_image/code_108 Magic Lamp.png'],
            ['code' => '109', 'name' => 'Fortune Gems', 'uid' => 'a990de177577a2e6a889aaac5f57b429', 'image' => 'jili_image/code_109 Fortune Gems.png'],
            ['code' => '110', 'name' => 'Ali Baba', 'uid' => 'cc686634b4f953754b306317799f1f39', 'image' => 'jili_image/code_110 Ali Baba.png'],
            ['code' => '115', 'name' => 'Agent Ace', 'uid' => '8a4b4929e796fda657a2d38264346509', 'image' => 'jili_image/code_115 Agent Ace.png'],
            ['code' => '116', 'name' => 'Happy Taxi', 'uid' => '1ed896aae4bdc78c984021307b1dd177', 'image' => 'jili_image/code_116 Happy Taxi.png'],
            ['code' => '126', 'name' => 'Bone Fortune', 'uid' => 'aab3048abc6a88e0759679fbe26e6a8d', 'image' => 'jili_image/code_126 Bone Fortune.png'],
            ['code' => '130', 'name' => 'Thor X', 'uid' => '7e6aa773fa802aaa9cb1f2fac464736e', 'image' => 'jili_image/code_130 Thor X.png'],
            ['code' => '134', 'name' => 'Mega Ace', 'uid' => 'eba92b1d3abd5f0d37dfbe112abdf0e2', 'image' => 'jili_image/code_134 Mega Ace.png'],
            ['code' => '135', 'name' => 'Mayan Empire', 'uid' => '5c2383ef253f9c36dacec4b463d61622', 'image' => 'jili_image/code_135 Mayan Empire.png'],
            ['code' => '136', 'name' => 'Samba', 'uid' => '6d35789b2f419c1db3926350d57c58d8', 'image' => 'jili_image/code_136 Samba.png'],
            ['code' => '137', 'name' => 'Gold Rush', 'uid' => '2a5d731e0fd60f52873a24ece11f2c0b', 'image' => 'jili_image/code_137 Gold Rush.png'],
            ['code' => '142', 'name' => 'Bonus Hunter', 'uid' => '39775cdc4170e56c5f768bdee8b4fa00', 'image' => 'jili_image/code_142 Bonus Hunter.png'],
            ['code' => '144', 'name' => 'JILI CAISHEN', 'uid' => '11e330c2b23f106815f3b726d04e4316', 'image' => 'jili_image/code_144 JILI CAISHEN.png'],
            ['code' => '145', 'name' => 'Neko Fortune', 'uid' => '9a391758f755cb30ff973e08b2df6089', 'image' => 'jili_image/code_145 Neko Fortune.png'],
            ['code' => '146', 'name' => 'World Cup', 'uid' => '28374b7ad7c91838a46404f1df046e5a', 'image' => 'jili_image/code_146 World Cup.png'],
            ['code' => '153', 'name' => 'Crazy Pusher', 'uid' => '00d92d5cec10cf85623938222a6c2bb6', 'image' => 'jili_image/code_153 Crazy Pusher.png'],
            ['code' => '164', 'name' => 'Pirate Queen', 'uid' => '70999d5bcf2a1d1f1fb8c82e357317f4', 'image' => 'jili_image/code_164 Pirate Queen.png'],
            ['code' => '166', 'name' => 'Wild Racer', 'uid' => '2f0c5f96cda3c6e16b3929dd6103df8e', 'image' => 'jili_image/code_166 Wild Racer.png'],
            ['code' => '171', 'name' => 'Sin City', 'uid' => '830cac2f5da6cc1fb91cfae04b85b1e2', 'image' => 'jili_image/code_171 Sin City.png'],
            ['code' => '172', 'name' => 'Elf Bingo', 'uid' => '5cec2b309a8845b38f8e9b4e6d649ea2', 'image' => 'jili_image/code_172 Elf Bingo.png'],
            ['code' => '176', 'name' => 'Master Tiger', 'uid' => 'd2b48fe98ac2956eeefd2bc4f7e0335a', 'image' => 'jili_image/code_176 Master Tiger.png'],
            ['code' => '180', 'name' => 'Legacy of Egypt', 'uid' => '1310248a5eab24b4bf113a6e0ee7962a', 'image' => 'jili_image/code_180 Legacy of Egypt.png'],
            ['code' => '181', 'name' => 'Wild Ace', 'uid' => '9a3b65e2ae5343df349356d548f3fc4b', 'image' => 'jili_image/code_181 Wild Ace.png'],
            ['code' => '183', 'name' => 'Golden Joker', 'uid' => 'f301fe0b22d1540b1f215d282b20c642', 'image' => 'jili_image/code_183 Golden Joker.png'],
            ['code' => '191', 'name' => 'Golden Temple', 'uid' => '976c5497256c020ac012005f6bb166ad', 'image' => 'jili_image/code_191 Golden Temple.png'],
            ['code' => '193', 'name' => 'Devil Fire', 'uid' => '1b4c5865131b4967513c1ee90cba4472', 'image' => 'jili_image/code_193 Devil Fire.png'],
            ['code' => '198', 'name' => 'Sweet Land', 'uid' => '91250a55f75a3c67ed134b99bf587225', 'image' => 'jili_image/code_198 Sweet Land.png'],
            ['code' => '208', 'name' => 'Trial of Phoenix', 'uid' => 'd11ea63b63ec615ae6df589f0b0d53e1', 'image' => 'jili_image/code_208 Trial of Phoenix.png'],
            ['code' => '209', 'name' => 'Aztec Priestess', 'uid' => '6acff19b2d911a8c695ba24371964807', 'image' => 'jili_image/code_209 Aztec Priestess.png'],
            ['code' => '214', 'name' => 'King Arthur', 'uid' => 'fafab1a17a237d0fc0e50c20d2c2bf4c', 'image' => 'jili_image/code_214 King Arthur.png'],
            ['code' => '223', 'name' => 'Fortune Gems 2', 'uid' => '664fba4da609ee82b78820b1f570f4ad', 'image' => 'jili_image/code_223 Fortune Gems 2.png'],
            ['code' => '225', 'name' => 'Cricket King 18', 'uid' => 'dcf220f4e3ecca0278911a55e6f11c77', 'image' => 'jili_image/code_225 Cricket King 18.png'],
            ['code' => '226', 'name' => 'Witches Night', 'uid' => '82c5c404cf4c0790deb42a2b5653533c', 'image' => 'jili_image/code_226 Witches Night.png'],
            ['code' => '228', 'name' => 'Arena Fighter', 'uid' => '71468f38b1fa17379231d50635990c31', 'image' => 'jili_image/code_228 Arena Fighter.png'],
            ['code' => '230', 'name' => 'Cricket Sah 75', 'uid' => '6720a0ce1d06648ff390fbea832798a9', 'image' => 'jili_image/code_230 Cricket Sah 75.png'],
            ['code' => '238', 'name' => 'Bangla Beauty', 'uid' => '6b60d159f0939a45f7b4c88a9b57499a', 'image' => 'jili_image/code_238 Bangla Beauty.png'],
            ['code' => '239', 'name' => 'Dabanggg', 'uid' => '5404a45b06826911c3537fdf935c281f', 'image' => 'jili_image/code_239 Dabanggg.png'],
            ['code' => '240', 'name' => 'Party Star', 'uid' => 'bfde2986a4eb3a5a559ac8a8c64df461', 'image' => 'jili_image/code_240 Party Star.png'],
            ['code' => '252', 'name' => 'Zeus', 'uid' => '4e7c9f4fbe9b5137f21ebd485a9cfa5c', 'image' => 'jili_image/code_252 Zeus.png'],
            ['code' => '258', 'name' => 'Devil Fire 2', 'uid' => '0426ba674c9dd29de6fa023afcf0640d', 'image' => 'jili_image/code_258 Devil Fire 2.png'],
            ['code' => '259', 'name' => 'Charge Buffalo Ascent', 'uid' => '28bc4a33c985ddce6acd92422626b76f', 'image' => 'jili_image/code_259 Charge Buffalo Ascent.png'],
            ['code' => '263', 'name' => 'The Pig House', 'uid' => '824736d3e6abff8a0b7e79d784c7b113', 'image' => 'jili_image/code_263 The Pig House.png'],
            ['code' => '264', 'name' => 'Egypt\'s Glow', 'uid' => 'ddac017cb273a590b7aa0e1ad6a52bef', 'image' => 'jili_image/code_264 Egypt_s Glow.png'],
            ['code' => '299', 'name' => 'Potion Wizard', 'uid' => 'fba154365cdf8fad07565cf93bae3521', 'image' => 'jili_image/code_299 Potion Wizard.png'],
            ['code' => '300', 'name' => 'Fortune Gems 3', 'uid' => '63927e939636f45e9d6d0b3717b3b1c1', 'image' => 'jili_image/code_300 Fortune Gems 3.png'],
            ['code' => '301', 'name' => 'Jackpot Joker', 'uid' => '7ed860eef313538545ff7aa2b9290cf9', 'image' => 'jili_image/code_301 Jackpot Joker.png'],
            ['code' => '302', 'name' => 'Money Coming Expand Bets', 'uid' => '3a557646c3abb12201c0b8810a8c0966', 'image' => 'jili_image/code_302 Money Coming Expand Bets.png'],
            ['code' => '303', 'name' => 'Fortune Monkey', 'uid' => 'add95fc40f1ef0d56f5716ce45a56946', 'image' => 'jili_image/code_303 Fortune Monkey.png'],
            ['code' => '307', 'name' => 'Treasure Quest', 'uid' => '6bb74b0a57a66850b79ab5c93864cac3', 'image' => 'jili_image/code_307 Treasure Quest.png'],
            ['code' => '324', 'name' => 'Nightfall Hunting', 'uid' => 'ced5e3de03293fc6fb111298a504cfeb', 'image' => 'jili_image/code_324 Nightfall Hunting.png'],
            ['code' => '372', 'name' => '3 Pot Dragons', 'uid' => '921dce2d616e5d0577135bb2d9214946', 'image' => 'jili_image/code_372 3 Pot Dragons.png'],
            ['code' => '374', 'name' => 'Lucky Doggy', 'uid' => '4bf1d6a75d91c725f89aa5985544a087', 'image' => 'jili_image/code_374 Lucky Doggy.png'],
            ['code' => '375', 'name' => 'Poseidon', 'uid' => '50a1bcbc2ef4a5f761e0e4d338a41699', 'image' => 'jili_image/code_375 Poseidon.png'],
            ['code' => '376', 'name' => 'Shōgun', 'uid' => '68724804a3cd30c749e460256b462f00', 'image' => 'jili_image/code_376 Shōgun.png'],
            ['code' => '377', 'name' => 'Safari Mystery', 'uid' => '56dad0ca19e96dc6ee1038d374712767', 'image' => 'jili_image/code_377 Safari Mystery.png'],
            ['code' => '378', 'name' => 'Golden Bank 2', 'uid' => '3a72a27c8851be5a396f51a19654c7c3', 'image' => 'jili_image/code_378 Golden Bank 2.png'],
            ['code' => '379', 'name' => 'Money Pot', 'uid' => 'a5acbbb7ae534d303f67cb447dc8723d', 'image' => 'jili_image/code_379 Money Pot.png'],
            ['code' => '392', 'name' => 'Coin Tree', 'uid' => 'ca72a7ad1ca4fa2cdc9a1c49c8bb3332', 'image' => 'jili_image/code_392 Coin Tree.png'],
            ['code' => '394', 'name' => 'Sweet Magic', 'uid' => 'ae88afcb58415b7802e2c02c40816f17', 'image' => 'jili_image/code_394 Sweet Magic.png'],
            ['code' => '399', 'name' => '3 Coin Treasures', 'uid' => '69c1b4586b5060eefcb45bb479f03437', 'image' => 'jili_image/code_399 3 Coin Treasures.png'],
            ['code' => '400', 'name' => '3 Lucky Piggy', 'uid' => 'e09d4c9612ea540bc0afabf76e4f9148', 'image' => 'jili_image/code_400 3 Lucky Piggy.png'],
            ['code' => '403', 'name' => 'Super Ace Deluxe', 'uid' => '80aad2a10ae6a95068b50160d6c78897', 'image' => 'jili_image/code_403 Super Ace Deluxe.png'],
            ['code' => '408', 'name' => 'Safari King', 'uid' => '230032b5b7eb29148358a03a3fbda3fb', 'image' => 'jili_image/code_408 Safari King.png'],
            ['code' => '409', 'name' => 'Super Ace Joker', 'uid' => '29c66f73e3916b8eb18c2bf78886927d', 'image' => 'jili_image/code_409 Super Ace Joker.png'],
            ['code' => '421', 'name' => 'Lucky Jaguar', 'uid' => '731e642b1fee94725e7313f3dfba8f45', 'image' => 'jili_image/code_421 Lucky Jaguar.png'],
            ['code' => '422', 'name' => 'Fruity Wheel', 'uid' => '921cf987632d65b5e41ab5dffe16d95a', 'image' => 'jili_image/code_422 Fruity Wheel.png'],
            ['code' => '423', 'name' => 'Bikini Lady', 'uid' => '702565a827764d10e470a0f76398a978', 'image' => 'jili_image/code_423 Bikini Lady.png'],
            ['code' => '424', 'name' => 'Golden Empire 2', 'uid' => '8cbb88bc0bc1f7be4379cf75abc6095f', 'image' => 'jili_image/code_424 Golden Empire 2.png'],
            ['code' => '447', 'name' => 'Crystal 777 DELUXE', 'uid' => 'ee14a5ddeadf31a98419bd20cc88da85', 'image' => 'jili_image/code_447 Crystal 777 DELUXE.png'],
            ['code' => '448', 'name' => 'Rapid Gems 777', 'uid' => 'ea2fe9a169ff7b23a58ceb35ac33c52e', 'image' => 'jili_image/code_448 Rapid Gems 777.png'],
            ['code' => '458', 'name' => 'Coin Infinity Surge Reel', 'uid' => 'a1ea10a6b30f260b6d6ff17028d38913', 'image' => 'jili_image/code_458 Coin Infinity Surge Reel.png'],
            ['code' => '460', 'name' => '3 Charge Buffalo', 'uid' => '3ea8ed5f8ba2239e6cd49366afb743f8', 'image' => 'jili_image/code_460 3 Charge Buffalo.png'],
            ['code' => '461', 'name' => 'Pirate Queen 2', 'uid' => '4702eb871271aa62ef3f3d78f5d968c1', 'image' => 'jili_image/code_461 Pirate Queen 2.png'],
            ['code' => '463', 'name' => '3 LUCKY LION', 'uid' => '7af6be9d29bb593fa0f6516b14b02103', 'image' => 'jili_image/code_463 3 LUCKY LION.png'],
            ['code' => '467', 'name' => '3 Rich pigies', 'uid' => '472f684f667e272e0ccc7ac1529170ca', 'image' => 'jili_image/code_467 3 Rich pigies.png'],
            ['code' => '472', 'name' => '3 Coin Treasures 2', 'uid' => '7b4308e95fa25021bae874f9e128c8c3', 'image' => 'jili_image/code_472 3 Coin Treasures 2.png'],
            ['code' => '485', 'name' => '3 Coin Wild Horse', 'uid' => '25bff08b69ccd31c238a627b53afff36', 'image' => 'jili_image/code_485 3 Coin Wild Horse.png'],
            ['code' => '495', 'name' => 'Boxing King Title Match', 'uid' => '2d91fb4cdd53d47367369ad85b271500', 'image' => 'jili_image/code_495 Boxing King Title Match.png'],
            ['code' => '504', 'name' => 'Crazy777 2', 'uid' => 'c7a3c072d2330f0eb602ccc6016cff4b', 'image' => 'jili_image/code_504 Crazy777 2.png'],
            ['code' => '517', 'name' => 'Roma X Deluxe', 'uid' => 'b4fe8cea772a7643551a12de806472e8', 'image' => 'jili_image/code_517 Roma X Deluxe.png'],
            ['code' => '518', 'name' => 'Circus Joker 4096', 'uid' => '1ab23c1fda76ec10778b458fac552e37', 'image' => 'jili_image/code_518 Circus Joker 4096.png'],
            ['code' => '523', 'name' => 'Fortune Coins', 'uid' => 'd6d14943efe13dd3bcf1428d0f702024', 'image' => 'jili_image/code_523 Fortune Coins.png'],
            ['code' => '526', 'name' => '10 Sparkling Crown', 'uid' => '2eb2879a2e4f3c5e5d297925283c37c9', 'image' => null],
            ['code' => '529', 'name' => 'Joker Coins', 'uid' => '25d960a93c7a78c6c46619acce6d032e', 'image' => 'jili_image/code_529 Joker Coins.png'],
            ['code' => '542', 'name' => 'Super Ace II', 'uid' => '083a2fbb35612d3f7925acedece5904f', 'image' => 'jili_image/code_542 Super Ace II.png'],
            ['code' => '543', 'name' => 'Money Coming 2', 'uid' => 'b4a3e54cabeecd94ebbd1cc217a5b069', 'image' => 'jili_image/code_543 Money Coming 2.png'],
            ['code' => '545', 'name' => 'Clover Coins 3x3', 'uid' => '3836fb2d7549281c8d339b2bce8c9086', 'image' => null],
            ['code' => '573', 'name' => 'Fortune Coins 2', 'uid' => '007f5afeab86a47d96038324438c0c1f', 'image' => 'jili_image/code_573 Fortune Coins 2.png'],
            ['code' => '583', 'name' => 'Cash Coin', 'uid' => 'fe942e56d8f33522e4084e8e3aaa3523', 'image' => 'jili_image/code_583 Cash Coin.png'],
            ['code' => '605', 'name' => 'Jackpot Joker FEVER', 'uid' => '397a27f0042be1a6f6a3e09c0ae6c057', 'image' => 'jili_image/code_605 Jackpot Joker FEVER.png'],
            ['code' => '667', 'name' => 'Circus Jackpot', 'uid' => 'd95070382efcbe621f3bdb77a4ecfab8', 'image' => 'jili_image/code_667 Circus Jackpot.png'],
        ];
    }
}
