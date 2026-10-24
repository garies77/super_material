<?php

namespace App\Filament\Resources\CustomForms\Pages;

use App\Filament\Resources\CustomForms\CustomFormResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateCustomForm extends CreateRecord
{
    protected static string $resource = CustomFormResource::class;

    // Form dua kolom memakai lebar konten penuh; tombol simpan menempel di bawah layar saat menggulir
    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = true;
}
