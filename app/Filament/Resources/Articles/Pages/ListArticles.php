<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Pages\ArticleSettingsPage;
use App\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Pengaturan tampilan global artikel, terpisah dari pembuatan artikel; khusus Admin
            Action::make('settings')->hiddenLabel()->tooltip('Pengaturan Artikel')->icon('heroicon-o-cog-6-tooth')
                ->color('gray')->url(fn () => ArticleSettingsPage::getUrl())
                ->visible(fn () => ArticleSettingsPage::canAccess()),
            CreateAction::make(),
        ];
    }
}
