<?php

namespace App\Filament\Resources\LinkPages\Pages;

use App\Filament\Resources\LinkPages\LinkPageResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateLinkPage extends CreateRecord
{
    protected static string $resource = LinkPageResource::class;

    // Form dua kolom memakai lebar konten penuh; tombol simpan menempel di bawah layar saat menggulir
    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = true;
}
