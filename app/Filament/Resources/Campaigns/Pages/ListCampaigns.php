<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Pages\CampaignReport;
use App\Filament\Resources\Campaigns\CampaignResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('report')->label('Laporan kampanye')->icon('heroicon-o-chart-bar')->color('gray')
                ->url(CampaignReport::getUrl()),
            CreateAction::make(),
        ];
    }
}
