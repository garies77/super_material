<?php

use App\Models\IdempotencyKey;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('seo:flag-expired-prices')->dailyAt('01:00')->timezone('Asia/Jakarta');

// Kunci Idempotency-Key API hanya berlaku 24 jam (PRD 8.12.4)
Schedule::command('model:prune', ['--model' => [IdempotencyKey::class]])->dailyAt('02:00')->timezone('Asia/Jakarta');
