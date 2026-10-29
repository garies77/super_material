<?php

namespace App\Filament\Pages;

use App\Filament\Reports\Concerns\HasReportPeriodFilter;
use App\Filament\Reports\Widgets\ContributorPerformanceTable;
use App\Filament\Reports\Widgets\ReviewerTable;
use App\Filament\Reports\Widgets\ReviewQueueTable;
use App\Filament\Reports\Widgets\TeamKpis;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/** Kinerja tim: kiriman & kualitas per Kontributor dan waktu tunggu tinjauan Editor/Admin (App\Support\Reports\TeamMetrics). */
class TeamReport extends Dashboard
{
    use HasReportPeriodFilter;

    protected static string $routePath = '/laporan/tim';

    protected static ?string $title = 'Kinerja tim';

    protected static ?string $navigationLabel = 'Kinerja tim';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 1;

    public function getWidgets(): array
    {
        return [
            TeamKpis::class,
            ContributorPerformanceTable::class,
            ReviewerTable::class,
            ReviewQueueTable::class,
        ];
    }
}
