<?php

namespace App\Filament\Resources\Ads\Pages;

use App\Filament\Pages\AdReport;
use App\Filament\Resources\Ads\AdResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAds extends ListRecords
{
    protected static string $resource = AdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('report')->label('Laporan iklan')->icon('heroicon-o-chart-bar')->color('gray')
                ->url(AdReport::getUrl()),
            CreateAction::make(),
        ];
    }
}
