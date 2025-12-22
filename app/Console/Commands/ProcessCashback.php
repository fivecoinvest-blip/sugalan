<?php

namespace App\Console\Commands;

use App\Services\VipService;
use Illuminate\Console\Command;

class ProcessCashback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vip:cashback {period=weekly : The period to process (weekly or monthly)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process VIP cashback bonuses for eligible users';

    /**
     * Execute the console command.
     */
    public function handle(VipService $vipService): int
    {
        $period = $this->argument('period');

        if (!in_array($period, ['weekly', 'monthly'])) {
            $this->error('Invalid period. Use "weekly" or "monthly".');
            return Command::FAILURE;
        }

        $this->info("Starting {$period} cashback processing...");

        $processedCount = $vipService->processCashback($period);

        $this->info("Processed cashback for {$processedCount} user(s).");

        return Command::SUCCESS;
    }
}
