<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Products\ProductResource;
use App\Support\MissionBoard as Missions;
use Filament\Widgets\Widget;

/** Papan misi gamifikasi di dashboard: pekerjaan berikutnya untuk pengguna ini, sesuai perannya. */
class MissionBoard extends Widget
{
    protected string $view = 'filament.widgets.mission-board';

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'missions' => Missions::for(auth()->user()),
            'createProductUrl' => ProductResource::canCreate() ? ProductResource::getUrl('create') : null,
            'createArticleUrl' => ArticleResource::canCreate() ? ArticleResource::getUrl('create') : null,
        ];
    }
}
