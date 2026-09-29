<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Shared hosting tidak punya worker permanen (docs/hosting.md). Cron memanggil
 * `php artisan schedule:run` tiap menit, dan schedule inilah yang menjalankan
 * worker singkat untuk mengosongkan antrean notifikasi.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    // Kunci kedaluwarsa 5 menit, bukan default 24 jam. Shared hosting biasa
    // membunuh proses yang dianggap terlalu lama, dan proses yang mati tidak
    // sempat melepas kuncinya. Dengan default, satu worker yang dibunuh membuat
    // seluruh notifikasi berhenti sampai besok; dengan 5 menit, cron menit
    // berikutnya paling lama menunggu 5 menit lalu jalan lagi sendiri.
    ->withoutOverlapping(5);
