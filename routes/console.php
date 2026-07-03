<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if ((bool) env('OFFOREST_SCHEDULER_ENABLED', true)) {
    Schedule::command('offorest:upload-approved-images-to-drive')
        ->everyFiveMinutes()
        ->withoutOverlapping();

    Schedule::command('offorest:generate-listing-metadata')
        ->everyFiveMinutes()
        ->withoutOverlapping();

    Schedule::command('offorest:refresh-proxy-data')
        ->cron('*/'.max(1, (int) env('OFFOREST_PROXY_REFRESH_EVERY_MINUTES', 5)).' * * * *')
        ->withoutOverlapping();

    if ((bool) env('OFFOREST_DATABASE_BACKUP_ENABLED', true)) {
        $backupCommand = 'offorest:backup-database --keep-days='.(int) env('OFFOREST_DATABASE_BACKUP_KEEP_DAYS', 14);
        $backupEveryMinutes = max(1, (int) env('OFFOREST_DATABASE_BACKUP_EVERY_MINUTES', 30));

        if ((bool) env('OFFOREST_DATABASE_BACKUP_TO_DRIVE', true)) {
            $backupCommand .= ' --drive';
        }

        Schedule::command($backupCommand)
            ->cron('*/'.$backupEveryMinutes.' * * * *')
            ->withoutOverlapping(45)
            ->runInBackground();
    }
}
