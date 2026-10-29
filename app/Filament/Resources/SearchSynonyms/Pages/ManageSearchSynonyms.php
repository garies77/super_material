<?php

namespace App\Filament\Resources\SearchSynonyms\Pages;

use App\Filament\Resources\SearchSynonyms\SearchSynonymResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSearchSynonyms extends ManageRecords
{
    protected static string $resource = SearchSynonymResource::class;

    protected static ?string $title = 'Sinonim pencarian';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah sinonim'),
        ];
    }
}
