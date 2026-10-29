<?php

namespace App\Filament\Pages;

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
use App\Support\Reports\ReportPeriod;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Pusat Laporan: dashboard kedua dengan filter periode yang dibagikan ke semua widget laporan
 * (App\Filament\Reports\Widgets, sengaja di luar folder widget dashboard utama agar tidak ikut tampil di sana).
 * Angka berasal dari App\Support\Reports\ReportMetrics.
 */
class ReportCenter extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/laporan';

    protected static ?string $title = 'Pusat Laporan';

    protected static ?string $navigationLabel = 'Pusat Laporan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 0;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canReview();
    }

    public function getSubheading(): ?string
    {
        return 'Periode '.ReportPeriod::fromFilters($this->filters)->label().' · dibanding periode yang sama sebelumnya. Bot tidak dihitung.';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 3])->schema([
                Select::make('period')->label('Periode')->options(ReportPeriod::PRESETS)
                    ->default('30d')->selectablePlaceholder(false),
                DatePicker::make('from')->label('Dari tanggal')->native(false)->maxDate(now())
                    ->visible(fn (Get $get) => $get('period') === 'custom'),
                DatePicker::make('until')->label('Sampai tanggal')->native(false)->maxDate(now())
                    ->visible(fn (Get $get) => $get('period') === 'custom'),
            ]),
        ]);
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

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 3];
    }
}
