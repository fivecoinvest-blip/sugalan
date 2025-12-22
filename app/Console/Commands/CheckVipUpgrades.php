<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\VipService;
use Illuminate\Console\Command;

class CheckVipUpgrades extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vip:check-upgrades';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all active users for VIP tier upgrades';

    /**
     * Execute the console command.
     */
    public function handle(VipService $vipService): int
    {
        $this->info('Checking VIP upgrades for all active users...');

        $upgradedCount = 0;

        User::where('status', 'active')
            ->chunk(100, function ($users) use ($vipService, &$upgradedCount) {
                foreach ($users as $user) {
                    $newLevel = $vipService->checkForUpgrade($user);
                    if ($newLevel) {
                        $upgradedCount++;
                        $this->info("User {$user->id} upgraded to {$newLevel->name}");
                    }
                }
            });

        $this->info("Upgraded {$upgradedCount} user(s).");

        return Command::SUCCESS;
    }
}
