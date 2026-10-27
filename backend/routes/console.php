<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


Artisan::command('portal:push-setup', function (): void {
    if (!config('portal_push.enabled')) { $this->info('Patient portal push is disabled.'); return; }
    app(\App\Services\PortalPushTransport::class)->setup();
    $this->info('Patient portal push keys are ready (private keys were not displayed).');
})->purpose('Create persistent private Web Push keys without rotating existing subscriptions');

Artisan::command('portal:deliver-notifications {--limit=20}', function (): void {
    $count = app(\App\Services\PortalNotifications::class)->deliver(max(1, min(100, (int) $this->option('limit'))));
    $this->info('Processed '.$count.' notification delivery attempts.');
})->purpose('Send pending patient portal notifications and retry temporary failures');

\Illuminate\Support\Facades\Schedule::command('portal:deliver-notifications --limit=20')->everyMinute()->withoutOverlapping(5);


Artisan::command('owners:daily-summary {--date=}', function (): void {
    try {
        $count = app(\App\Services\OwnerNotifications::class)->daily($this->option('date') ?: null);
        $this->info('Prepared '.$count.' owner daily summaries.');
    } catch (\InvalidArgumentException $error) { $this->fail($error->getMessage()); }
})->purpose('Prepare completed Baghdad-day summaries, with three-day automatic catch-up');

Artisan::command('owners:deliver-notifications {--limit=20}', function (): void {
    $count = app(\App\Services\OwnerNotifications::class)->deliver(max(1, min(100, (int) $this->option('limit'))));
    $this->info('Processed '.$count.' owner notification delivery attempts.');
})->purpose('Deliver and retry owner Web Push notifications');

\Illuminate\Support\Facades\Schedule::command('owners:daily-summary')->everyMinute()->withoutOverlapping(30);
\Illuminate\Support\Facades\Schedule::command('owners:deliver-notifications --limit=20')->everyMinute()->withoutOverlapping(5);
