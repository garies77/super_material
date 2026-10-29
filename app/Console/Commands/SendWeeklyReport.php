<?php

namespace App\Console\Commands;

use App\Notifications\WeeklyReportDigest;
use App\Support\Reports\ReportSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/** Pekerjaan Senin pagi: kirim ringkasan minggu lalu ke penerima yang diatur di Pusat Laporan. */
class SendWeeklyReport extends Command
{
    protected $signature = 'reports:send-weekly {--to=* : Kirim ke alamat ini saja (abaikan pengaturan aktif/nonaktif)}';

    protected $description = 'Kirim email laporan mingguan (Senin–Minggu lalu) ke penerima laporan';

    public function handle(): int
    {
        $recipients = $this->option('to') ?: ReportSummary::recipients();

        if (! $this->option('to') && ! ReportSummary::isEnabled()) {
            $this->info('Laporan mingguan nonaktif. Aktifkan di Pusat Laporan → Laporan mingguan.');

            return self::SUCCESS;
        }

        if ($recipients === []) {
            $this->warn('Belum ada penerima laporan mingguan.');

            return self::SUCCESS;
        }

        $summary = ReportSummary::build(ReportSummary::lastWeek());

        foreach ($recipients as $email) {
            Notification::route('mail', $email)->notify(new WeeklyReportDigest($summary));
        }

        $this->info('Laporan '.$summary['period']['label'].' dikirim ke '.count($recipients).' penerima.');

        return self::SUCCESS;
    }
}
