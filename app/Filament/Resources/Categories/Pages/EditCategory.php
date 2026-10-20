<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Redirect;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $oldSlug = $record->slug;
        $record->update($data);

        if ($oldSlug !== $record->slug) {
            Redirect::updateOrCreate(
                ['from_path' => '/kategori/'.$oldSlug],
                ['to_path' => '/kategori/'.$record->slug, 'code' => 301],
            );
        }

        return $record;
    }
}
