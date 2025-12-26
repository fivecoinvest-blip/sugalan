<?php

namespace App\Console\Commands;

use App\Services\SlotSessionService;
use Illuminate\Console\Command;

class ExpireSlotSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slots:expire-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire old slot game sessions';

    /**
     * Execute the console command.
     */
    public function handle(SlotSessionService $sessionService)
    {
        $this->info('Expiring old slot sessions...');
        
        $count = $sessionService->expireOldSessions();
        
        $this->info("Expired {$count} session(s)");
        
        return Command::SUCCESS;
    }
}
