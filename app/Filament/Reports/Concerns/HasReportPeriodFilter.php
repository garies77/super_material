<?php

namespace App\Filament\Reports\Concerns;

use App\Support\Reports\ReportPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/** Halaman laporan dengan filter periode (preset atau rentang sendiri) yang dibaca widget lewat UsesReportPeriod. */
trait HasReportPeriodFilter
{
    use HasFiltersForm;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canReview();
    }

    public function getSubheading(): ?string
    {
        return 'Periode '.ReportPeriod::fromFilters($this->filters)->label().' · dibanding periode yang sama sebelumnya.';
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

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 3];
    }
}
