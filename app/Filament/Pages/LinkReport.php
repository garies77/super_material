<?php

namespace App\Filament\Pages;

use App\Filament\Reports\Concerns\HasReportPeriodFilter;
use App\Filament\Reports\Widgets\LinkDevicesTable;
use App\Filament\Reports\Widgets\LinkItemsTable;
use App\Filament\Reports\Widgets\LinkKpis;
use App\Filament\Reports\Widgets\LinkSourcesTable;
use App\Filament\Reports\Widgets\LinkTrendChart;
use App\Models\LinkPage;
use App\Support\Reports\ReportPeriod;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Laporan Link Bio: tayangan, klik, CTR, tombol terlaris, sumber kunjungan, dan perangkat (App\Support\Reports\LinkReportMetrics). */
class LinkReport extends Dashboard
{
    use HasReportPeriodFilter;

    protected static string $routePath = '/laporan/link-bio';

    protected static ?string $title = 'Laporan Link Bio';

    protected static ?string $navigationLabel = 'Laporan Link Bio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 5;

    public function getSubheading(): ?string
    {
        return 'Periode '.ReportPeriod::fromFilters($this->filters)->label().' · dibanding periode yang sama sebelumnya. Bot tidak dihitung; tayangan dihitung sekali per kunjungan.';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 4])->schema([
                Select::make('page')->label('Halaman')->placeholder('Semua halaman')
                    ->options(fn () => LinkPage::orderBy('title')->pluck('title', 'id')),
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
            LinkKpis::class,
            LinkTrendChart::class,
            LinkDevicesTable::class,
            LinkItemsTable::class,
            LinkSourcesTable::class,
        ];
    }
}
