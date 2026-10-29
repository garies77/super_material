<?php

namespace App\Filament\Reports\Concerns;

use App\Support\Reports\CsvExport;
use App\Support\Reports\ReportPeriod;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Widget Pusat Laporan: membaca filter periode halaman dan menyediakan tombol ekspor CSV. */
trait UsesReportPeriod
{
    use InteractsWithPageFilters;

    protected function period(): ReportPeriod
    {
        return ReportPeriod::fromFilters($this->pageFilters);
    }

    /**
     * @param  array<string, string>  $columns  kunci baris => judul kolom CSV
     * @param  callable(): iterable<array<string, mixed>>  $rows
     */
    protected function exportAction(string $name, array $columns, callable $rows): Action
    {
        // Tombol ikon (dengan tooltip) agar tidak menghimpit judul pada kartu laporan yang sempit
        return Action::make('export')->label('Ekspor CSV')->tooltip('Ekspor CSV')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')->iconButton()
            ->action(fn () => CsvExport::download($name.'-'.$this->period()->from->format('Ymd').'-'.$this->period()->to->format('Ymd'), $columns, $rows()));
    }
}
