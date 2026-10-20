<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Redirect;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

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
                ['from_path' => '/produk/'.$oldSlug],
                ['to_path' => '/produk/'.$record->slug, 'code' => 301],
            );
        }

        return $record;
    }
}
