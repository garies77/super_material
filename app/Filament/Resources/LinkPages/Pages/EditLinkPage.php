<?php

namespace App\Filament\Resources\LinkPages\Pages;

use App\Filament\Resources\LinkPages\LinkPageResource;
use App\Filament\Support\PageActions;
use App\Support\LinkBioDesign;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditLinkPage extends EditRecord
{
    protected static string $resource = LinkPageResource::class;

    // Editor dua kolom (pengaturan + pratinjau) memakai lebar konten penuh; tombol simpan menempel di bawah layar
    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = true;

    protected function getHeaderActions(): array
    {
        return [LinkPageResource::openAction()->record($this->record), PageActions::more([DeleteAction::make()])];
    }

    /** Halaman lama tanpa design tersimpan: isi semua pengaturan dari presetnya agar form tidak kosong. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['design'] = array_merge(
            LinkBioDesign::resolve($data['theme'] ?? null, $data['design'] ?? null),
            ['bg_image_path' => $data['design']['bg_image_path'] ?? null],
        );

        return $data;
    }
}
