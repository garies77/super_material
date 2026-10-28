<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    // Form dua kolom memakai lebar konten penuh; tombol simpan menempel di bawah layar saat menggulir
    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // author_id menunjuk tabel authors, bukan users; Penulis hanya boleh menulis atas namanya sendiri
        if (auth()->user()->writesAsOwnAuthor() || empty($data['author_id'])) {
            $data['author_id'] = auth()->user()->authorProfile()->id;
        }

        return $data;
    }
}
