<?php

namespace App\Filament\Support;

use App\Models\LinkItem;
use App\Models\LinkPage;
use App\Models\Product;
use Closure;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Pratinjau ponsel di samping form Link Bio (seperti editor Linktree): halaman dirender dari isi form yang belum disimpan
 * memakai view publik yang sama (link-page) di dalam iframe srcdoc, jadi gaya situs tidak bercampur dengan gaya admin.
 *
 * Field biasa cukup diberi ->afterStateUpdated(LinkBioPreview::refresh()) agar hanya panel ini yang di-render ulang.
 */
class LinkBioPreview
{
    public const KEY = 'linkBioPreview';

    public static function make(): View
    {
        return View::make('filament.link-bio-preview')->key(self::KEY)
            ->viewData(fn (View $component, ?LinkPage $record) => [
                'html' => self::html((array) ($component->getLivewire()->data ?? []), $record),
                'url' => $record?->is_published ? $record->url() : null,
            ]);
    }

    public static function refresh(): Closure
    {
        return function (Component $component): void {
            $component->getRootContainer()->getComponent(self::KEY, withHidden: true)?->partiallyRender();
        };
    }

    /** @param  array<string, mixed>  $state  isi form saat ini */
    public static function html(array $state, ?LinkPage $record = null): string
    {
        $page = new LinkPage([
            'title' => filled($state['title'] ?? null) ? $state['title'] : 'Nama Anda',
            'slug' => $state['slug'] ?? $record?->slug ?? 'pratinjau',
            'bio' => $state['bio'] ?? null,
            'theme' => $state['theme'] ?? 'light',
            'design' => is_array($state['design'] ?? null) ? $state['design'] : null,
            'socials' => array_values((array) ($state['socials'] ?? [])),
            'avatar_path' => self::storedPath($state['avatar_path'] ?? null),
            'banner_path' => self::storedPath($state['banner_path'] ?? null),
            'is_published' => true,
        ]);

        $design = (array) ($state['design'] ?? []);
        if (is_array($design['bg_image_path'] ?? null)) {
            $page->design = ['bg_image_path' => self::storedPath($design['bg_image_path'])] + $design;
        }

        return view('link-page', [
            'page' => $page,
            'items' => self::items((array) ($state['items'] ?? []), $page),
            'preview' => true,
            'urls' => array_filter([
                'avatar' => self::temporaryUrl($state['avatar_path'] ?? null),
                'banner' => self::temporaryUrl($state['banner_path'] ?? null),
                'background' => self::temporaryUrl($design['bg_image_path'] ?? null),
            ]),
        ])->render();
    }

    /** @return Collection<int, LinkItem> */
    private static function items(array $rows, LinkPage $page): Collection
    {
        $rows = collect($rows)->filter(fn ($row) => is_array($row) && filled($row['label'] ?? null) && ($row['is_active'] ?? true));
        $products = Product::whereIn('id', $rows->pluck('product_id')->filter())->get()->keyBy('id');

        return $rows->map(function (array $row) use ($page, $products) {
            $item = new LinkItem(Arr::only($row, ['type', 'label', 'link_type', 'page_id', 'url', 'new_tab', 'product_id', 'sales_channel_id',
                'whatsapp_number', 'whatsapp_message', 'is_highlighted', 'is_active', 'button_color', 'text_color']));
            $item->thumbnail_path = self::storedPath($row['thumbnail_path'] ?? null);
            $item->previewThumbnailUrl = self::temporaryUrl($row['thumbnail_path'] ?? null);
            $item->setRelation('linkPage', $page);
            $item->setRelation('product', $products[$row['product_id'] ?? 0] ?? null);

            return $item;
        })->values();
    }

    /** Path tersimpan pertama dari state FileUpload ([uuid => path]) atau string biasa. */
    private static function storedPath(mixed $state): ?string
    {
        $file = is_array($state) ? Arr::first($state) : $state;

        return is_string($file) && $file !== '' ? $file : null;
    }

    private static function temporaryUrl(mixed $state): ?string
    {
        $file = is_array($state) ? Arr::first($state) : $state;

        if (! $file instanceof TemporaryUploadedFile) {
            return null;
        }

        try {
            return $file->temporaryUrl();
        } catch (Throwable) {
            return null;
        }
    }
}
