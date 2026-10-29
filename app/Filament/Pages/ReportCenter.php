<?php

namespace App\Filament\Pages;

use App\Filament\Reports\Concerns\HasReportPeriodFilter;
use App\Filament\Reports\Widgets\ArticlePerformanceTable;
use App\Filament\Reports\Widgets\ChannelShareChart;
use App\Filament\Reports\Widgets\ClickOriginsTable;
use App\Filament\Reports\Widgets\ClicksTrendChart;
use App\Filament\Reports\Widgets\LeadsByFormTable;
use App\Filament\Reports\Widgets\ProductConversionTable;
use App\Filament\Reports\Widgets\ReportKpis;
use App\Filament\Reports\Widgets\TopProductsTable;
use App\Filament\Reports\Widgets\TopSearchesTable;
use App\Filament\Reports\Widgets\UtmCampaignsTable;
use App\Filament\Reports\Widgets\ZeroClickProductsTable;
use App\Filament\Reports\Widgets\ZeroResultSearchesTable;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Notifications\WeeklyReportDigest;
use App\Support\Reports\ReportPeriod;
use App\Support\Reports\ReportSummary;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Pusat Laporan: dashboard kedua dengan filter periode yang dibagikan ke semua widget laporan
 * (App\Filament\Reports\Widgets, sengaja di luar folder widget dashboard utama agar tidak ikut tampil di sana).
 * Angka berasal dari App\Support\Reports\ReportMetrics.
 */
class ReportCenter extends Dashboard
{
    use HasReportPeriodFilter;

    protected static string $routePath = '/laporan';

    protected static ?string $title = 'Pusat laporan';

    protected static ?string $navigationLabel = 'Pusat laporan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 0;

    public function getSubheading(): ?string
    {
        return 'Periode '.ReportPeriod::fromFilters($this->filters)->label().' · dibanding periode yang sama sebelumnya. Bot tidak dihitung.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')->label('Cetak / PDF')->icon(Heroicon::OutlinedPrinter)->color('gray')
                ->url(fn () => route('reports.print', array_filter($this->filters ?? [])), shouldOpenInNewTab: true),
            ActionGroup::make([
                Action::make('weeklySettings')->label('Atur penerima')->icon(Heroicon::OutlinedCog6Tooth)
                    ->modalHeading('Laporan mingguan lewat email')
                    ->modalDescription('Ringkasan Senin–Minggu lalu dikirim otomatis setiap Senin pukul 07.00 WIB.')
                    ->modalSubmitActionLabel('Simpan')
                    ->fillForm(fn () => ['enabled' => ReportSummary::isEnabled(), 'recipients' => ReportSummary::recipients()])
                    ->schema([
                        Toggle::make('enabled')->label('Kirim laporan mingguan'),
                        TagsInput::make('recipients')->label('Penerima')->placeholder('Ketik email lalu tekan Enter')
                            ->nestedRecursiveRules(['email'])->splitKeys([',', ' '])
                            ->helperText(ReportSummary::mailConfigured() ? null : 'Pengiriman email belum diatur di server (MAIL_MAILER masih "'.config('mail.default').'"); laporan belum benar-benar terkirim.'),
                    ])
                    ->action(function (array $data): void {
                        Setting::put(ReportSummary::ENABLED_SETTING, (bool) $data['enabled']);
                        Setting::put(ReportSummary::RECIPIENTS_SETTING, json_encode(array_values($data['recipients'] ?? [])));
                        AuditLog::record(request(), 'report.weekly_settings', null, ['enabled' => (bool) $data['enabled'], 'recipients' => count($data['recipients'] ?? [])]);
                        Notification::make()->title('Pengaturan laporan mingguan disimpan')->success()->send();
                    }),
                Action::make('sendSample')->label('Kirim contoh ke email saya')->icon(Heroicon::OutlinedPaperAirplane)
                    ->requiresConfirmation()
                    ->modalHeading('Kirim contoh laporan mingguan?')
                    ->modalDescription(fn () => 'Laporan minggu lalu dikirim ke '.auth()->user()->email.'.')
                    ->modalSubmitActionLabel('Kirim')
                    ->action(function (): void {
                        auth()->user()->notify(new WeeklyReportDigest(ReportSummary::build(ReportSummary::lastWeek())));
                        Notification::make()->title('Contoh laporan dikirim ke '.auth()->user()->email)
                            ->body(ReportSummary::mailConfigured() ? null : 'Catatan: email server belum diatur, jadi pesan hanya tercatat di log.')
                            ->success()->send();
                    }),
            ])->label('Laporan mingguan')->icon(Heroicon::OutlinedEnvelope)->color('gray')->button(),
        ];
    }

    public function getWidgets(): array
    {
        return [
            ReportKpis::class,
            ClicksTrendChart::class,
            ChannelShareChart::class,
            TopProductsTable::class,
            ProductConversionTable::class,
            ZeroClickProductsTable::class,
            ArticlePerformanceTable::class,
            ClickOriginsTable::class,
            TopSearchesTable::class,
            ZeroResultSearchesTable::class,
            UtmCampaignsTable::class,
            LeadsByFormTable::class,
        ];
    }
}
