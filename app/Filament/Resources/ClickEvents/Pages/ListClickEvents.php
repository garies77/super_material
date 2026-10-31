<?php

namespace App\Filament\Resources\ClickEvents\Pages;

use App\Filament\Resources\ClickEvents\ClickEventResource;
use App\Support\CsvCell;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListClickEvents extends ListRecords
{
    protected static string $resource = ClickEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Ekspor CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(function () {
                    $query = $this->getFilteredTableQuery()->with(['product.category', 'channel']);

                    return response()->streamDownload(function () use ($query) {
                        $out = fopen('php://output', 'w');
                        fputcsv($out, ['waktu', 'produk', 'kategori', 'kanal', 'halaman_asal', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'bot']);
                        $query->reorder('id')->chunk(1000, function ($rows) use ($out) {
                            foreach ($rows as $r) {
                                // referrer & utm_* berasal dari query string pengunjung: cegah formula injection
                                fputcsv($out, CsvCell::row([$r->created_at, $r->product?->name, $r->product?->category?->name, $r->channel?->name, $r->page_url, $r->referrer, $r->utm_source, $r->utm_medium, $r->utm_campaign, $r->is_bot ? 1 : 0]));
                            }
                        });
                        fclose($out);
                    }, 'klik-'.now()->format('Ymd-His').'.csv');
                }),
        ];
    }
}
