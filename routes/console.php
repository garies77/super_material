<?php

use App\Models\IdempotencyKey;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Penyelesai deploy GitHub Actions (hosting tanpa SSH): tetap jalan saat mode pemeliharaan,
// karena justru perintah ini yang membuka situs lagi setelah migrasi
Schedule::command('deploy:finish')->everyMinute()->withoutOverlapping(15)->evenInMaintenanceMode();

Schedule::command('seo:flag-expired-prices')->dailyAt('01:00')->timezone('Asia/Jakarta');

// Potret kesehatan katalog untuk tren mingguan di laporan Kesehatan Katalog
Schedule::command('reports:snapshot-catalog')->dailyAt('01:30')->timezone('Asia/Jakarta');

// Email laporan mingguan (Senin–Minggu lalu); penerima & aktif/nonaktif diatur di Pusat Laporan
Schedule::command('reports:send-weekly')->weeklyOn(1, '07:00')->timezone('Asia/Jakarta');

// Kunci Idempotency-Key API hanya berlaku 24 jam (PRD 8.12.4)
Schedule::command('model:prune', ['--model' => [IdempotencyKey::class]])->dailyAt('02:00')->timezone('Asia/Jakarta');
