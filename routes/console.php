<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use QOR\App\Domain\Billing\UseCase\ResetPeriodUsage;
use QOR\App\Domain\Notification\UseCase\DetectNearbyReminders;
use QOR\App\Domain\Notification\UseCase\DetectRegionalPublishes;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ADMIN-23: naturally end Published events once their start time has passed.
Schedule::command('events:close-ended')->everyFiveMinutes();

// NOTIF-01: notify fans whose favorited events are approaching within the
// configured lead time. Scan interval is config-driven (T85), not hardcoded.
/** @var int $nearbyReminderScanIntervalMinutes */
$nearbyReminderScanIntervalMinutes = config('qor.notifications.nearby_reminder_scan_interval_minutes');
Schedule::call(fn () => app(DetectNearbyReminders::class)->execute())
    ->cron("*/{$nearbyReminderScanIntervalMinutes} * * * *");

// NOTIF-16: batch-notify fans about events newly published within their
// city/radius. Scan interval is config-driven (T85), not hardcoded.
/** @var int $regionalPublishScanIntervalMinutes */
$regionalPublishScanIntervalMinutes = config('qor.notifications.regional_publish_scan_interval_minutes');
Schedule::call(fn () => app(DetectRegionalPublishes::class)->execute())
    ->cron("*/{$regionalPublishScanIntervalMinutes} * * * *");

// MON-11: reset every organizer's monthly publish-quota usage at each
// calendar-month boundary.
Schedule::call(fn () => app(ResetPeriodUsage::class)->execute())
    ->monthlyOn(1, '00:00');
