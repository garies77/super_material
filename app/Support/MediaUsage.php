<?php

namespace App\Support;

use App\Filament\Pages\ManageHomepage;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Ads\AdResource;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Brands\BrandResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\SalesChannels\SalesChannelResource;
use App\Models\Ad;
use App\Models\Article;
use App\Models\Author;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Media;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use App\Models\Setting;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Mencari konten yang memakai sebuah berkas pustaka media. Kandidat disaring di database
 * berdasarkan nama berkas, lalu dipastikan di PHP terhadap path lengkap (termasuk bentuk JSON "\/").
 */
class MediaUsage
{
    /**
     * @return array<int, array{type: string, title: string, url: ?string}>
     */
    public static function for(Media $media): array
    {
        $needles = [$media->path, str_replace('/', '\/', $media->path)];
        $usages = [];

        foreach (self::sources() as [$type, $model, $columns, $title, $url]) {
            /** @var Builder $query */
            $query = $model::query()->where(function (Builder $q) use ($columns, $media) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', '%'.basename($media->path).'%');
                }
            });

            foreach ($query->get() as $record) {
                $raw = collect($columns)->map(fn ($column) => (string) $record->getRawOriginal($column))->implode("\n");

                if (collect($needles)->contains(fn ($needle) => str_contains($raw, $needle))) {
                    $usages[] = ['type' => $type, 'title' => (string) $title($record), 'url' => self::safeUrl($url, $record)];
                }
            }
        }

        return $usages;
    }

    /**
     * @return array<int, array{0: string, 1: class-string<Model>, 2: array<int, string>, 3: Closure, 4: Closure}>
     */
    private static function sources(): array
    {
        return [
            ['Produk', Product::class, ['image', 'gallery', 'og_image', 'description', 'custom_html'], fn (Product $p) => $p->name, fn (Product $p) => ProductResource::getUrl('edit', ['record' => $p])],
            ['Media produk', ProductMedia::class, ['path', 'poster_path'], fn (ProductMedia $m) => $m->product?->name, fn (ProductMedia $m) => ProductResource::getUrl('edit', ['record' => $m->product_id])],
            ['Varian produk', ProductVariant::class, ['image_path'], fn (ProductVariant $v) => $v->product?->name.' — '.$v->name, fn (ProductVariant $v) => ProductResource::getUrl('edit', ['record' => $v->product_id])],
            ['Kategori', Category::class, ['image', 'og_image', 'bottom_content'], fn (Category $c) => $c->name, fn (Category $c) => CategoryResource::getUrl('edit', ['record' => $c])],
            ['Merek', Brand::class, ['logo'], fn (Brand $b) => $b->name, fn () => BrandResource::getUrl('index')],
            ['Artikel', Article::class, ['image', 'og_image', 'content', 'custom_html'], fn (Article $a) => $a->title, fn (Article $a) => ArticleResource::getUrl('edit', ['record' => $a])],
            ['Penulis', Author::class, ['avatar'], fn (Author $a) => $a->name, fn (Author $a) => AuthorResource::getUrl('edit', ['record' => $a])],
            ['Kampanye', Ad::class, ['image', 'image_mobile', 'html'], fn (Ad $a) => $a->name, fn (Ad $a) => AdResource::getUrl('edit', ['record' => $a])],
            ['Halaman', Page::class, ['og_image', 'content', 'blocks', 'draft_blocks', 'custom_html'], fn (Page $p) => $p->title, fn (Page $p) => PageResource::getUrl('edit', ['record' => $p])],
            ['Kanal penjualan', SalesChannel::class, ['logo'], fn (SalesChannel $c) => $c->name, fn () => SalesChannelResource::getUrl('index')],
            ['Beranda', HomeSection::class, ['settings'], fn (HomeSection $s) => HomeSection::TYPES[$s->type] ?? $s->key, fn () => ManageHomepage::getUrl()],
            ['Pengaturan', Setting::class, ['value'], fn (Setting $s) => $s->key, fn (Setting $s) => str_starts_with($s->key, 'hero_banner') ? ManageHomepage::getUrl() : SiteSettings::getUrl()],
        ];
    }

    /** Tautan sunting hanya pelengkap; kegagalan membuat URL tidak boleh menggagalkan daftar. */
    private static function safeUrl(Closure $url, Model $record): ?string
    {
        try {
            return $url($record);
        } catch (Throwable) {
            return null;
        }
    }
}
