<?php

namespace App\Filament\Pages;

use App\Filament\Reports\Widgets\CatalogHealthKpis;
use App\Filament\Reports\Widgets\CatalogIssuesSummaryTable;
use App\Filament\Reports\Widgets\CatalogIssuesTable;
use App\Filament\Reports\Widgets\CatalogTrendChart;
use App\Filament\Reports\Widgets\SeoScoreDistributionChart;
use App\Support\Reports\CatalogHealth;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Kesehatan katalog: kondisi data saat ini dan tren mingguannya. Tanpa filter periode; skor SEO berasal dari
 * potret harian (reports:snapshot-catalog), dibuat otomatis saat halaman pertama kali dibuka bila belum ada.
 */
class CatalogHealthReport extends Dashboard
{
    protected static string $routePath = '/laporan/katalog';

    protected static ?string $title = 'Kesehatan katalog';

    protected static ?string $navigationLabel = 'Kesehatan katalog';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canReview();
    }

    public function mount(): void
    {
        // mount() halaman berjalan sebelum pemeriksaan akses bawaan (mountCanAuthorizeAccess): cek dulu sebelum menghitung
        abort_unless(static::canAccess(), 403);

        if (! CatalogHealth::latest()) {
            CatalogHealth::snapshot();
        }
    }

    public function getSubheading(): ?string
    {
        $latest = CatalogHealth::latest();

        return 'Produk & artikel yang sudah terbit. Skor SEO dihitung tiap malam'
            .($latest ? ', terakhir '.$latest->updated_at->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i').' WIB.' : '.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')->label('Hitung ulang sekarang')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->action(function (): void {
                    CatalogHealth::snapshot();
                    Notification::make()->title('Skor katalog diperbarui')->success()->send();
                    $this->redirect(static::getUrl());
                }),
        ];
    }

    public function getWidgets(): array
    {
        return [
            CatalogHealthKpis::class,
            SeoScoreDistributionChart::class,
            CatalogIssuesSummaryTable::class,
            CatalogTrendChart::class,
            CatalogIssuesTable::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 3];
    }
}
