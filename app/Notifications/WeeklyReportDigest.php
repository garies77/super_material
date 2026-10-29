<?php

namespace App\Notifications;

use App\Filament\Pages\ReportCenter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email laporan mingguan ke Admin/bos: angka utama, produk & pencarian teratas, tim, dan katalog. */
class WeeklyReportDigest extends Notification
{
    /** @param  array<string, mixed>  $summary  hasil App\Support\Reports\ReportSummary::build() */
    public function __construct(public array $summary) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Laporan mingguan Supermaterial: '.$this->summary['period']['label'])
            ->markdown('mail.weekly-report', [
                'summary' => $this->summary,
                'reportUrl' => ReportCenter::getUrl(),
            ]);
    }
}
