<?php

namespace App\Console\Commands;

use App\Services\BonusService;
use Illuminate\Console\Command;

class ExpireBonuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bonuses:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire old bonuses that have passed their expiration date';

    /**
     * Execute the console command.
     */
    public function handle(BonusService $bonusService): int
    {
        $this->info('Starting bonus expiration process...');

        $expiredCount = $bonusService->expireOldBonuses();

        $this->info("Expired {$expiredCount} bonus(es).");

        return Command::SUCCESS;
    }
}
