<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\Catalog\ProductPhotos;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    // Form dua kolom memakai lebar konten penuh; tombol simpan menempel di bawah layar saat menggulir
    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = true;

    /** @var array<int, string> galeri foto dari field "photos" (bukan kolom tabel), disimpan setelah produk diperbarui */
    protected array $photoPaths = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['photos'] = ProductPhotos::paths($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->photoPaths = array_values((array) ($data['photos'] ?? []));
        unset($data['photos']);

        return $data;
    }

    protected function afterSave(): void
    {
        ProductPhotos::sync($this->record, $this->photoPaths);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label('Lihat di situs')->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => $this->record->url())->openUrlInNewTab(),
            ProductResource::duplicateAction(),
            DeleteAction::make(),
        ];
    }
}
