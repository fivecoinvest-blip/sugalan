<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\VipService;
use Illuminate\Console\Command;

class CheckVipDowngrades extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vip:check-downgrades {--days=90 : Number of inactive days to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all active users for VIP tier downgrades based on inactivity';

    /**
     * Execute the console command.
     */
    public function handle(VipService $vipService): int
    {
        $days = (int) $this->option('days');
        $this->info("Checking VIP downgrades for users inactive for {$days} days...");

        $downgradedCount = 0;

        // Only check users above Bronze level
        User::where('status', 'active')
            ->whereHas('vipLevel', function ($query) {
                $query->where('level', '>', 1);
            })
            ->chunk(100, function ($users) use ($vipService, &$downgradedCount, $days) {
                foreach ($users as $user) {
                    $newLevel = $vipService->checkForDowngrade($user, $days);
                    if ($newLevel) {
                        $downgradedCount++;
                        $this->info("User {$user->id} downgraded to {$newLevel->name}");
                    }
                }
            });

        $this->info("Downgraded {$downgradedCount} user(s).");

        return Command::SUCCESS;
    }
}
