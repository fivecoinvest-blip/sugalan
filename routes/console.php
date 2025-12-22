<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled Tasks
Schedule::command('bonuses:expire')->daily();
Schedule::command('vip:cashback weekly')->weekly();
Schedule::command('vip:cashback monthly')->monthly();
Schedule::command('vip:check-upgrades')->daily();
Schedule::command('vip:check-downgrades')->monthly(); // Check for downgrades monthly

// Automated Backups
Schedule::command('backup:database --compress --retention=30')->daily()->at('02:00');
Schedule::command('backup:database --type=schema --compress')->weekly()->sundays()->at('03:00');
Schedule::command('backup:database --type=data --compress')->weekly()->wednesdays()->at('03:00');
