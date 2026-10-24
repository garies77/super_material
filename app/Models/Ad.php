<?php

namespace App\Models;

use App\Support\ExternalUrl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Kampanye internal (gabungan Kampanye Artikel dan Banner Kampanye pop-up). Tidak ada jaringan iklan pihak ketiga.
 * - placement banner: di dalam halaman (display inline) atau pop-up (display popup);
 * - placement sidebar: kolom samping artikel.
 * Halaman tujuan dipilih lewat kolom pages.
 */
class Ad extends Model
{
    public const PLACEMENTS = ['banner' => 'Banner', 'sidebar' => 'Sidebar'];

    public const DISPLAYS = ['inline' => 'Di dalam halaman', 'popup' => 'Pop-up (modal)'];

    public const PAGES = [
        'home' => 'Homepage',
        'product' => 'Halaman produk',
        'article' => 'Halaman artikel',
        'category' => 'Halaman kategori',
        'page' => 'Halaman statis',
    ];

    /** Halaman yang punya kolom samping. */
    public const SIDEBAR_PAGES = ['article'];

    /** Posisi banner di halaman artikel; halaman lain menampilkan banner di atas konten. */
    public const ARTICLE_SLOTS = [
        'top' => 'Atas artikel',
        'in_content_1' => 'Dalam isi 1',
        'in_content_2' => 'Dalam isi 2',
        'bottom' => 'Bawah artikel',
    ];

    /** Semua posisi (untuk laporan dan pencatatan event). */
    public const SLOTS = self::ARTICLE_SLOTS + [
        'sidebar' => 'Sidebar',
        'page_top' => 'Atas konten halaman',
        'popup' => 'Pop-up',
    ];

    public const FORMATS = [
        'image' => 'Gambar dengan tautan',
        'product' => 'Kartu produk',
        'text' => 'Teks dengan tombol CTA',
        'html' => 'HTML kustom (khusus Admin)',
    ];

    // Label netral untuk kampanye sendiri; 'Sponsor' untuk materi berbayar dari mitra
    public const LABELS = ['Sorotan' => 'Sorotan', 'Info' => 'Info', 'Sponsor' => 'Sponsor (mitra berbayar)'];

    public const TARGETING = [
        'all' => 'Semua isi di halaman terpilih',
        'categories' => 'Kategori tertentu (artikel, produk, halaman kategori)',
        'tags' => 'Tag artikel tertentu',
        'articles' => 'Artikel tertentu',
    ];

    public const DEVICES = ['all' => 'Desktop & mobile', 'desktop' => 'Desktop saja', 'mobile' => 'Mobile saja'];

    public const TRIGGERS = ['delay' => 'Setelah jeda (detik)', 'scroll' => 'Setelah menggulir (persen halaman)'];

    public const FREQUENCIES = ['once' => 'Sekali saja', 'session' => 'Sekali per sesi', 'days' => 'Setiap N hari'];

    /** Paragraf bawaan untuk slot dalam isi (N = 3, M = 8). */
    public const DEFAULT_PARAGRAPH = ['in_content_1' => 3, 'in_content_2' => 8];

    /** Kandidat pop-up yang dikirim ke browser; browser memilih yang pertama belum dibatasi frekuensi. */
    public const MAX_POPUPS = 3;

    private const EXCLUDED_ROUTES = ['go', 'ad.click', 'ad.event', 'ad.preview', 'search', 'login.verify'];

    private const POLICY_SLUG = '/kebijakan|privasi|privacy|syarat|ketentuan|terms|disclaimer/i';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (Ad $ad) {
            // HTML kustom hanya boleh dibuat atau diubah Admin, ditegakkan di backend (PRD 8.8)
            if ($ad->format === 'html' && ($ad->isDirty('html') || $ad->isDirty('format'))
                && auth()->check() && ! auth()->user()->isAdmin()) {
                throw new AuthorizationException('Hanya Admin yang dapat membuat kampanye HTML kustom.');
            }

            if ($ad->html !== null) {
                $ad->html = self::sanitize($ad->html);
            }

            // Slot mengikuti format tampilan: sidebar dan pop-up punya slot tetap
            if ($ad->placement === 'sidebar' || ($ad->slot === 'sidebar' && ! $ad->isDirty('placement'))) {
                $ad->placement = 'sidebar';
                $ad->display = 'inline';
                $ad->slot = 'sidebar';
                $ad->pages = array_values(array_intersect($ad->pages ?? [], self::SIDEBAR_PAGES)) ?: self::SIDEBAR_PAGES;
            } elseif ($ad->display === 'popup') {
                $ad->slot = 'popup';
            } elseif (! isset(self::ARTICLE_SLOTS[$ad->slot])) {
                $ad->slot = 'top';
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_scroll_hint' => 'boolean',
            'allow_never_again' => 'boolean',
            'priority' => 'integer',
            'paragraph_after' => 'integer',
            'delay_seconds' => 'integer',
            'scroll_percent' => 'integer',
            'frequency_days' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'pages' => 'array',
            'target_category_ids' => 'array',
            'target_tag_ids' => 'array',
            'target_article_ids' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AdEvent::class);
    }

    /** Aktif dan berada dalam jadwal tayang. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isLive(): bool
    {
        return $this->is_active
            && (! $this->starts_at || $this->starts_at->lte(now()))
            && (! $this->ends_at || $this->ends_at->gte(now()));
    }

    public function isPopup(): bool
    {
        return $this->display === 'popup';
    }

    /** Status tampilan admin: nonaktif, terjadwal, aktif, atau selesai. */
    public function displayStatus(): string
    {
        return match (true) {
            ! $this->is_active => 'Nonaktif',
            $this->starts_at && $this->starts_at->isFuture() => 'Terjadwal',
            $this->ends_at && $this->ends_at->isPast() => 'Selesai',
            default => 'Aktif',
        };
    }

    /** @return array<int, string> */
    public function pageList(): array
    {
        return $this->pages ?: ['article'];
    }

    /**
     * Apakah kampanye ini tampil pada konteks halaman ini.
     *
     * @param  array{page: string, article?: ?Article, category_id?: ?int}  $context
     */
    public function targets(array $context): bool
    {
        if (! in_array($context['page'], $this->pageList(), true)) {
            return false;
        }

        $ids = fn (?array $v) => array_map('intval', $v ?? []);
        $article = $context['article'] ?? null;

        return match ($this->targeting) {
            'categories' => $article
                ? $article->categories->pluck('id')->map(fn ($id) => (int) $id)->intersect($this->expandedCategoryIds())->isNotEmpty()
                : ($context['category_id'] ?? null) && in_array((int) $context['category_id'], $this->expandedCategoryIds(), true),
            'tags' => $article && $article->tags->pluck('id')->intersect($ids($this->target_tag_ids))->isNotEmpty(),
            'articles' => $article && in_array($article->id, $ids($this->target_article_ids), true),
            default => true,
        };
    }

    /** Kategori terpilih beserta seluruh turunannya. */
    private function expandedCategoryIds(): array
    {
        $ids = array_map('intval', $this->target_category_ids ?? []);

        return Category::whereIn('id', $ids)->get()
            ->flatMap(fn (Category $c) => $c->descendantIds())
            ->map(fn ($id) => (int) $id)->merge($ids)->unique()->values()->all();
    }

    /**
     * Banner dalam halaman dan sidebar untuk artikel: satu kampanye per slot. Prioritas tertinggi menang;
     * kampanye berprioritas sama dirotasi acak per tampilan halaman.
     *
     * @return array<string, Ad>
     */
    public static function forArticle(Article $article): array
    {
        $context = ['page' => 'article', 'article' => $article];

        $chosen = self::live()->where('display', 'inline')->with('product.media', 'product.variants')->get()
            ->filter(fn (Ad $ad) => (isset(self::ARTICLE_SLOTS[$ad->slot]) || $ad->slot === 'sidebar') && $ad->targets($context))
            ->groupBy('slot')
            ->map(fn (Collection $ads) => self::pick($ads))
            ->all();

        // Dalam isi 2 harus berada setelah Dalam isi 1
        if (isset($chosen['in_content_1'], $chosen['in_content_2'])
            && $chosen['in_content_2']->paragraphPosition() <= $chosen['in_content_1']->paragraphPosition()) {
            unset($chosen['in_content_2']);
        }

        return $chosen;
    }

    /** Banner di atas konten untuk halaman selain artikel (homepage, produk, kategori, halaman statis). */
    public static function bannerForRequest(Request $request): ?Ad
    {
        $context = self::pageContext($request);
        if (! $context || $context['page'] === 'article') {
            return null;
        }

        $ads = self::live()->where('display', 'inline')->where('placement', 'banner')->with('product.media', 'product.variants')->get()
            ->filter(fn (Ad $ad) => $ad->targets($context));

        return $ads->isEmpty() ? null : self::pick($ads);
    }

    /** @return Collection<int, Ad> kandidat pop-up, prioritas tertinggi lebih dulu */
    public static function popupsForRequest(Request $request): Collection
    {
        $context = self::pageContext($request);
        if (! $context) {
            return collect();
        }

        return self::live()->where('display', 'popup')->orderByDesc('priority')->orderByDesc('id')->get()
            ->filter(fn (Ad $ad) => $ad->targets($context))
            ->take(self::MAX_POPUPS)->values();
    }

    /**
     * Jenis halaman dan konteksnya, atau null bila kampanye tidak boleh tampil di sini.
     *
     * @return array{page: string, article: ?Article, category_id: ?int}|null
     */
    public static function pageContext(Request $request): ?array
    {
        $route = $request->route();
        $name = $route?->getName();
        if (! $name || in_array($name, self::EXCLUDED_ROUTES, true) || $request->is('go/*', 'ad/*', 'sm-link/*', 'admin', 'admin/*')) {
            return null;
        }

        $slug = (string) $route->parameter('slug');

        return match ($name) {
            'home' => ['page' => 'home', 'article' => null, 'category_id' => null],
            'page.show' => preg_match(self::POLICY_SLUG, $slug) ? null : ['page' => 'page', 'article' => null, 'category_id' => null],
            'category.show' => ['page' => 'category', 'article' => null, 'category_id' => Category::where('slug', $slug)->value('id')],
            'product.show' => ['page' => 'product', 'article' => null, 'category_id' => Product::where('slug', $slug)->value('category_id')],
            'article.show' => ['page' => 'article', 'article' => Article::where('slug', $slug)->with('categories', 'tags')->first(), 'category_id' => null],
            default => null,
        };
    }

    private static function pick(Collection $ads): Ad
    {
        return $ads->where('priority', $ads->max('priority'))->random();
    }

    /** Konfigurasi yang dibaca JS pop-up untuk pemicu dan aturan frekuensi. */
    public function clientConfig(): array
    {
        return [
            'id' => $this->id,
            'device' => $this->device,
            'trigger' => $this->trigger,
            'delay' => max(1, (int) $this->delay_seconds),
            'scroll' => min(100, max(1, (int) $this->scroll_percent)),
            'frequency' => $this->frequency,
            'days' => max(1, (int) $this->frequency_days),
        ];
    }

    /**
     * Tautan CTA pop-up. Tautan keluar diberi UTM (utm_campaign = nama kampanye) agar terbaca di GA4;
     * tautan internal tidak, karena UTM internal memecah sesi GA4.
     */
    public function ctaUrl(): ?string
    {
        $url = $this->destinationUrl();
        if (blank($url) || ! $this->isExternal() || preg_match('#^https?://(wa\.me|api\.whatsapp\.com)/#i', $url)) {
            return $url ?: null;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $existing);
        $utm = array_diff_key([
            'utm_source' => Str::slug(config('app.name')) ?: 'supermaterial',
            'utm_medium' => 'onsite',
            'utm_campaign' => $this->utmCampaign(),
        ], $existing);

        if ($utm === []) {
            return $url;
        }

        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);

        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query($utm).($fragment !== null ? '#'.$fragment : '');
    }

    public function utmCampaign(): string
    {
        return Str::slug($this->name) ?: 'kampanye-'.$this->id;
    }

    public function paragraphPosition(): int
    {
        return max(1, $this->paragraph_after ?: (self::DEFAULT_PARAGRAPH[$this->slot] ?? 3));
    }

    public function destinationUrl(): ?string
    {
        if (filled($this->target_url)) {
            return $this->target_url;
        }

        return $this->product?->url();
    }

    /** Tujuan ke situs mitra atau marketplace (bukan domain Supermaterial). */
    public function isExternal(): bool
    {
        return ExternalUrl::is($this->destinationUrl());
    }

    public function imageUrl(?string $path = null): ?string
    {
        $path ??= $this->image;
        if (! $path) {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    public function mobileImageUrl(): ?string
    {
        return $this->image_mobile ? $this->imageUrl($this->image_mobile) : null;
    }

    /**
     * Data kartu produk dari katalog: gambar, nama, harga, dan harga coret.
     *
     * @return array{name: string, image: ?string, price: ?string, compare: ?string}|null
     */
    public function productCard(): ?array
    {
        $product = $this->product;
        if (! $product || ! $product->is_published) {
            return null;
        }

        $fmt = fn (?int $n) => $n ? 'Rp'.number_format($n, 0, ',', '.') : null;
        $cheapest = $product->variants->filter(fn ($v) => $v->is_active ?? true)
            ->filter(fn ($v) => $v->effectivePrice())
            ->sortBy(fn ($v) => $v->effectivePrice())->first();

        return [
            'name' => $product->name,
            'image' => $product->imageUrl(),
            'price' => $product->isPriceHidden() ? null : $product->priceLabel(),
            'compare' => $product->isPriceHidden() ? null : $fmt($cheapest?->effectiveCompareAtPrice()),
        ];
    }

    /** HTML kustom yang sudah disanitasi; tautan keluar diberi rel sponsored nofollow. */
    public function safeHtml(): string
    {
        $html = self::sanitize((string) $this->html);
        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="ad-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        foreach ($doc->getElementsByTagName('a') as $a) {
            $probe = new self(['target_url' => $a->getAttribute('href')]);
            if ($probe->isExternal()) {
                $a->setAttribute('rel', 'sponsored nofollow noopener');
                $a->setAttribute('target', '_blank');
            }
        }

        $root = $doc->getElementById('ad-root');
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    public static function sanitize(string $html): string
    {
        return trim(Str::sanitizeHtml($html));
    }
}
