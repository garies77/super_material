<?php

namespace App\Filament\Resources\LinkPages\Pages;

use App\Filament\Resources\LinkPages\LinkPageResource;
use App\Filament\Support\PageActions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditLinkPage extends EditRecord
{
    protected static string $resource = LinkPageResource::class;

    // Form dua kolom memakai lebar konten penuh; tombol simpan menempel di bawah layar saat menggulir
    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = true;

    protected function getHeaderActions(): array
    {
        return [LinkPageResource::openAction()->record($this->record), PageActions::more([DeleteAction::make()])];
    }
}
