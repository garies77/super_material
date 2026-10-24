<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\Article;
use App\Models\Category;
use App\Models\ClickEvent;
use App\Models\CustomForm;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Menyusun data beranda dari konfigurasi seksi. Konfigurasi di-cache (dibersihkan saat disimpan);
 * peringkat produk populer di-cache 10 menit karena agregasi klik paling berat.
 * Query produk/artikel lain ringan dan selalu segar sehingga perubahan produk langsung tampil.
 */
class HomepageService
{
    public const POPULAR_CACHE_KEY = 'homepage_popular_products';

    /** @return Collection<int, HomeSection> */
    public function sections(): Collection
    {
        // Simpan atribut mentah, bukan objek: Laravel menolak unserialize objek dari cache (cache.serializable_classes)
        return HomeSection::hydrate(Cache::rememberForever(HomeSection::CACHE_KEY,
            fn () => HomeSection::active()->ordered()->get()->map->getAttributes()->all()))->toBase();
    }

    public static function flush(): void
    {
        Cache::forget(HomeSection::CACHE_KEY);
        Cache::forget(self::POPULAR_CACHE_KEY);
    }

    public function resolve(HomeSection $section): array
    {
        return match ($section->type) {
            'hero' => ['banner' => self::heroBanner()],
            'categories' => ['categories' => $this->categories($section)],
            'products' => ['products' => $this->products($section)],
            'articles' => ['articles' => $this->articles($section)],
            'banner' => $this->banner($section),
            'cta' => ['whatsapp' => $this->whatsappUrl($section)],
            'form' => ['form' => CustomForm::active()->find($section->setting('form_id'))],
            default => [],
        };
    }

    /** Banner kampanye hero tetap disimpan di Setting (hero_banner_*) agar data lama dan gambar OG tidak berubah. */
    public static function heroBanner(): ?array
    {
        $image = Setting::imageUrl('hero_banner_image');
        if (! Setting::getBool('hero_banner_active', true) || ! $image) {
            return null;
        }

        return [
            'image' => $image,
            'style' => Setting::get('hero_banner_style', 'card'),
            'badge' => Setting::get('hero_banner_badge', 'Promo Bundling Spesial'),
            'title' => Setting::get('hero_banner_title'),
            'desc' => Setting::get('hero_banner_desc'),
            'cta_text' => Setting::get('hero_banner_cta_text', 'Lihat Promo Bundling'),
            'cta_url' => Setting::get('hero_banner_cta_url', route('product.index')),
        ];
    }

    private function categories(HomeSection $section): Collection
    {
        $query = Category::active();
        $ids = array_map('intval', (array) $section->setting('category_ids', []));

        $categories = $section->setting('source') === 'selected' && $ids
            ? $query->whereIn('id', $ids)->get()->sortBy(fn ($c) => array_search($c->id, $ids))->values()
            : $query->whereNull('parent_id')->orderBy('sort_order')->orderBy('name')->get();

        return $this->withPublishedProductCounts($categories);
    }

    /**
     * products_count = produk terbit di kategori ini dan seluruh subkategorinya, aturan yang sama dengan
     * halaman /kategori/{slug} (CatalogController: published() + descendantIds()). Dua query tetap, berapa pun kategorinya.
     */
    private function withPublishedProductCounts(Collection $categories): Collection
    {
        $children = Category::query()->get(['id', 'parent_id'])->groupBy('parent_id')->map(fn ($group) => $group->pluck('id')->all());
        $direct = Product::published()->whereNotNull('category_id')
            ->groupBy('category_id')->selectRaw('category_id, COUNT(*) as aggregate')->pluck('aggregate', 'category_id');

        return $categories->each(function (Category $category) use ($children, $direct) {
            $total = 0;
            $seen = [];
            $queue = [$category->id];
            while ($queue) {
                $id = array_shift($queue);
                if (isset($seen[$id])) {
                    continue; // jaga-jaga bila data induk-anak membentuk lingkaran
                }
                $seen[$id] = true;
                $total += (int) ($direct[$id] ?? 0);
                array_push($queue, ...($children[$id] ?? []));
            }
            $category->products_count = $total;
        });
    }

    private function products(HomeSection $section): Collection
    {
        $limit = (int) $section->setting('limit', 8);
        $query = Product::published()->with(['category', 'brand', 'media', 'variants']);

        switch ($section->setting('selection_mode', 'featured')) {
            case 'manual':
                $ids = array_map('intval', (array) $section->setting('product_ids', []));

                return $query->whereIn('id', $ids)->get()
                    ->sortBy(fn ($p) => array_search($p->id, $ids))->take($limit)->values();

            case 'category':
                $category = Category::find($section->setting('category_id'));

                return $category
                    ? $query->whereIn('category_id', $category->descendantIds())->latest()->take($limit)->get()
                    : collect();

            case 'popular':
                $ids = $this->popularProductIds();
                $popular = (clone $query)->whereIn('id', $ids)->get()->sortBy(fn ($p) => array_search($p->id, $ids))->values();

                // Belum cukup data klik: lengkapi dengan produk terbaru agar seksi tidak kosong
                return $popular->concat(
                    $query->whereNotIn('id', $popular->pluck('id'))->latest()->take(max(0, $limit - $popular->count()))->get()
                )->take($limit)->values();

            case 'latest':
                return $query->latest()->take($limit)->get();

            default: // featured: perilaku beranda lama, unggulan dulu lalu terbaru
                return $query->orderByDesc('is_featured')->latest()->take($limit)->get();
        }
    }

    /** ID produk diurutkan dari klik tombol beli terbanyak (90 hari terakhir, tanpa bot). */
    private function popularProductIds(): array
    {
        return Cache::remember(self::POPULAR_CACHE_KEY, 600, fn () => ClickEvent::query()
            ->whereNotNull('product_id')->where('is_bot', false)->where('created_at', '>=', now()->subDays(90))
            ->groupBy('product_id')->orderByRaw('COUNT(*) DESC')->limit(50)
            ->pluck('product_id')->map(fn ($id) => (int) $id)->all());
    }

    private function articles(HomeSection $section): Collection
    {
        $limit = (int) $section->setting('limit', 3);
        $query = Article::published();

        return match ($section->setting('sort_by', 'latest')) {
            'popular' => $query->orderByDesc('views_count')->latest('published_at')->take($limit)->get(),
            'manual' => (function () use ($query, $section, $limit) {
                $ids = array_map('intval', (array) $section->setting('article_ids', []));

                return $query->whereIn('id', $ids)->get()->sortBy(fn ($a) => array_search($a->id, $ids))->take($limit)->values();
            })(),
            'category' => $query->whereHas('categories', fn ($q) => $q->whereKey($section->setting('category_id')))
                ->latest('published_at')->take($limit)->get(),
            default => $query->latest('published_at')->take($limit)->get(),
        };
    }

    private function banner(HomeSection $section): array
    {
        if ($section->setting('source') === 'campaign') {
            $ad = Ad::live()->where('placement', 'banner')->where('display', 'inline')->find($section->setting('ad_id'));

            return ['ad' => $ad];
        }

        $path = fn (?string $p) => $p ? (preg_match('#^https?://#', $p) ? $p : asset('storage/'.ltrim($p, '/'))) : null;

        return [
            'image' => $path($section->setting('image')),
            'image_mobile' => $path($section->setting('image_mobile')),
        ];
    }

    private function whatsappUrl(HomeSection $section): ?string
    {
        $wa = preg_replace('/\D+/', '', (string) ($section->setting('whatsapp_number') ?: Setting::get('whatsapp_number')));
        if (! $wa) {
            return null;
        }
        $wa = str_starts_with($wa, '0') ? '62'.substr($wa, 1) : $wa;

        return 'https://wa.me/'.$wa.'?text='.rawurlencode((string) $section->setting('message', 'Halo, saya ingin konsultasi kebutuhan material proyek.'));
    }
}
