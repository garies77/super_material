<?php

namespace App\Filament\Reports\Concerns;

/** Widget Laporan Link Bio: filter periode Pusat Laporan plus pilihan halaman. */
trait FiltersLinkPage
{
    use UsesReportPeriod;

    protected function linkPageId(): ?int
    {
        $id = $this->pageFilters['page'] ?? null;

        return filled($id) ? (int) $id : null;
    }
}
