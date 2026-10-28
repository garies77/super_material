<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\Catalog\ProductPhotos;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    // Form dua kolom memakai lebar konten penuh; tombol simpan menempel di bawah layar saat menggulir
    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = true;

    /** @var array<int, string> galeri foto dari field "photos" (bukan kolom tabel), disimpan setelah produk dibuat */
    protected array $photoPaths = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->photoPaths = array_values((array) ($data['photos'] ?? []));
        unset($data['photos']);

        return $data;
    }

    protected function afterCreate(): void
    {
        ProductPhotos::sync($this->record, $this->photoPaths);
    }
}
