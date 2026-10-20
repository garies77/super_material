<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Redirect;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $oldSlug = $record->slug;
        $record->update($data);

        if ($oldSlug !== $record->slug) {
            Redirect::updateOrCreate(
                ['from_path' => '/artikel/'.$oldSlug],
                ['to_path' => '/artikel/'.$record->slug, 'code' => 301],
            );
        }

        return $record;
    }
}
