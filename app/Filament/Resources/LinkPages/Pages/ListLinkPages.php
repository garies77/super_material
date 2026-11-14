<?php

namespace App\Filament\Resources\LinkPages\Pages;

use App\Filament\Pages\LinkReport;
use App\Filament\Resources\LinkPages\LinkPageResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLinkPages extends ListRecords
{
    protected static string $resource = LinkPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('report')->label('Laporan Link Bio')->icon('heroicon-o-chart-bar')->color('gray')
                ->url(LinkReport::getUrl()),
            CreateAction::make(),
        ];
    }
}
