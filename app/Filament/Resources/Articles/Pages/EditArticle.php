<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label('Lihat di situs')->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => $this->record->url())->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
