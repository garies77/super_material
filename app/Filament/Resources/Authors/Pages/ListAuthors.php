<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Resources\Authors\AuthorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAuthors extends ListRecords
{
    protected static string $resource = AuthorResource::class;

    protected function getHeaderActions(): array
    {
        // Penulis & Kontributor sudah punya profil otomatis dan hanya boleh mengubahnya, tidak membuat penulis baru
        return [CreateAction::make()->visible(fn () => AuthorResource::canCreate())];
    }
}
