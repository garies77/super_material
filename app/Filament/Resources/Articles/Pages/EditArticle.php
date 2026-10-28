<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Support\PageActions;
use App\Filament\Support\ReviewWorkflow;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    // Form dua kolom memakai lebar konten penuh; tombol simpan menempel di bawah layar saat menggulir
    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = true;

    protected function getHeaderActions(): array
    {
        return [
            ...ReviewWorkflow::pageActions($this),
            PageActions::viewOnSite(fn () => $this->record->url()),
            PageActions::more([DeleteAction::make()->visible(ReviewWorkflow::canPublish())]),
        ];
    }

    public function getSubheading(): ?string
    {
        return ReviewWorkflow::subheading($this->record);
    }
}
