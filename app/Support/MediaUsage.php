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
use Illuminate\Support\Collection;
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
     * Ganti semua pemakaian berkas lama dengan berkas baru di seluruh konten. Disimpan lewat model agar event
     * (bersihkan cache API, beranda, pengaturan) tetap berjalan; kolom JSON/HTML diganti pada teks mentahnya.
     *
     * @return int jumlah konten yang diperbarui
     */
    public static function replace(Media $old, Media $new): int
    {
        $search = [$old->path, str_replace('/', '\/', $old->path)];
        $replace = [$new->path, str_replace('/', '\/', $new->path)];
        $updated = 0;

        foreach (self::sources() as [, $model, $columns]) {
            $candidates = $model::query()->where(function (Builder $q) use ($columns, $old) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', '%'.basename($old->path).'%');
                }
            })->get();

            foreach ($candidates as $record) {
                $changes = collect($columns)
                    ->mapWithKeys(fn (string $column) => [$column => $record->getRawOriginal($column)])
                    ->filter(fn ($raw) => is_string($raw))
                    ->map(fn (string $raw) => str_replace($search, $replace, $raw))
                    ->filter(fn (string $raw, string $column) => $raw !== $record->getRawOriginal($column));

                if ($changes->isEmpty()) {
                    continue;
                }

                if ($record instanceof Setting) {
                    Setting::put($record->key, $changes->first());
                } else {
                    // Nilai mentah (bukan lewat cast) agar JSON/HTML tersimpan persis seperti hasil penggantian
                    $record->setRawAttributes([...$record->getAttributes(), ...$changes->all()]);
                    $record->save();
                }

                $updated++;
            }
        }

        return $updated;
    }

    /**
     * ID media yang dipakai konten mana pun. Semua kolom sumber dibaca sekali lalu dicocokkan per path,
     * agar filter "Belum dipakai" dan hapus massal tidak menjalankan belasan query per gambar.
     *
     * @return Collection<int, int>
     */
    public static function usedIds(): Collection
    {
        return once(function (): Collection {
            $haystack = collect(self::sources())->flatMap(fn (array $source) => $source[1]::query()
                ->select($source[2])->toBase()->get()
                ->flatMap(fn (object $row) => array_values((array) $row)))
                ->filter()->implode("\n");

            return Media::query()->get(['id', 'path'])
                ->filter(fn (Media $media) => str_contains($haystack, $media->path) || str_contains($haystack, str_replace('/', '\/', $media->path)))
                ->pluck('id')->values();
        });
    }

    /**
     * @return array<int, array{0: string, 1: class-string<Model>, 2: array<int, string>, 3: Closure, 4: Closure}>
     */
    private static function sources(): array
    {
        return [
            ['Produk', Product::class, ['image', 'gallery', 'og_image', 'description', 'custom_html', 'custom_css'], fn (Product $p) => $p->name, fn (Product $p) => ProductResource::getUrl('edit', ['record' => $p])],
            ['Media produk', ProductMedia::class, ['path', 'poster_path'], fn (ProductMedia $m) => $m->product?->name, fn (ProductMedia $m) => ProductResource::getUrl('edit', ['record' => $m->product_id])],
            ['Varian produk', ProductVariant::class, ['image_path'], fn (ProductVariant $v) => $v->product?->name.' — '.$v->name, fn (ProductVariant $v) => ProductResource::getUrl('edit', ['record' => $v->product_id])],
            ['Kategori', Category::class, ['image', 'og_image', 'bottom_content'], fn (Category $c) => $c->name, fn (Category $c) => CategoryResource::getUrl('edit', ['record' => $c])],
            ['Merek', Brand::class, ['logo'], fn (Brand $b) => $b->name, fn () => BrandResource::getUrl('index')],
            ['Artikel', Article::class, ['image', 'og_image', 'content', 'custom_html', 'custom_css'], fn (Article $a) => $a->title, fn (Article $a) => ArticleResource::getUrl('edit', ['record' => $a])],
            ['Penulis', Author::class, ['avatar'], fn (Author $a) => $a->name, fn (Author $a) => AuthorResource::getUrl('edit', ['record' => $a])],
            ['Kampanye', Ad::class, ['image', 'image_mobile', 'html'], fn (Ad $a) => $a->name, fn (Ad $a) => AdResource::getUrl('edit', ['record' => $a])],
            ['Halaman', Page::class, ['og_image', 'content', 'blocks', 'draft_blocks', 'custom_html', 'custom_css'], fn (Page $p) => $p->title, fn (Page $p) => PageResource::getUrl('edit', ['record' => $p])],
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
