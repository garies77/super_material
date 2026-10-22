<?php

namespace App\Models;

use App\Support\ExternalUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Banner kampanye berbentuk modal (PRD 8.9). Nama kelas/atribut/endpoint di sisi publik
 * memakai awalan netral "sm-campaign" agar tidak tersaring ad blocker.
 */
class Campaign extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'active' => 'Aktif',
        'disabled' => 'Dinonaktifkan',
    ];

    public const LAYOUTS = [
        'image' => 'Hanya gambar',
        'image_text' => 'Gambar dengan teks',
    ];

    public const TARGETING = [
        'all' => 'Seluruh situs',
        'home' => 'Beranda saja',
        'categories' => 'Kategori tertentu (halaman kategori & produknya)',
        'products' => 'Halaman produk',
        'articles' => 'Halaman artikel',
    ];

    public const DEVICES = [
        'all' => 'Desktop & mobile',
        'desktop' => 'Desktop saja',
        'mobile' => 'Mobile saja',
    ];

    public const TRIGGERS = [
        'delay' => 'Setelah jeda (detik)',
        'scroll' => 'Setelah menggulir (persen halaman)',
    ];

    public const FREQUENCIES = [
        'once' => 'Sekali saja',
        'session' => 'Sekali per sesi',
        'days' => 'Setiap N hari',
    ];

    /** Kandidat yang dikirim ke browser; browser memilih yang pertama belum dibatasi frekuensi. */
    public const MAX_CANDIDATES = 3;

    /** Rute yang tidak boleh menampilkan modal (PRD 8.9). */
    private const EXCLUDED_ROUTES = ['go', 'ad.click', 'ad.event', 'ad.preview', 'search', 'campaign.event'];

    private const POLICY_SLUG = '/kebijakan|privasi|privacy|syarat|ketentuan|terms|disclaimer/i';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'delay_seconds' => 'integer',
            'scroll_percent' => 'integer',
            'frequency_days' => 'integer',
            'allow_never_again' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'target_category_ids' => 'array',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(CampaignEvent::class);
    }

    /** Aktif dan berada dalam jadwal tayang. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isLive(): bool
    {
        return $this->status === 'active'
            && (! $this->starts_at || $this->starts_at->lte(now()))
            && (! $this->ends_at || $this->ends_at->gte(now()));
    }

    /** Status tampilan admin: draft, terjadwal, aktif, selesai, atau dinonaktifkan. */
    public function displayStatus(): string
    {
        return match (true) {
            $this->status !== 'active' => self::STATUSES[$this->status] ?? $this->status,
            $this->starts_at && $this->starts_at->isFuture() => 'Terjadwal',
            $this->ends_at && $this->ends_at->isPast() => 'Selesai',
            default => 'Aktif',
        };
    }

    /**
     * Kampanye yang boleh tampil pada request ini, prioritas tertinggi lebih dulu.
     *
     * @return Collection<int, Campaign>
     */
    public static function forRequest(Request $request): Collection
    {
        $context = self::pageContext($request);
        if ($context === null) {
            return collect();
        }

        return self::live()->orderByDesc('priority')->orderByDesc('id')->get()
            ->filter(fn (Campaign $c) => $c->targets($context))
            ->take(self::MAX_CANDIDATES)
            ->values();
    }

    /**
     * Jenis halaman dan kategori terkait, atau null bila modal tidak boleh tampil di sini.
     *
     * @return array{page: string, category_id: ?int}|null
     */
    public static function pageContext(Request $request): ?array
    {
        $route = $request->route();
        $name = $route?->getName();
        if (! $name || in_array($name, self::EXCLUDED_ROUTES, true) || $request->is('go/*', 'ad/*', 'admin', 'admin/*')) {
            return null;
        }

        $slug = (string) $route->parameter('slug');

        return match ($name) {
            'home' => ['page' => 'home', 'category_id' => null],
            'page.show' => preg_match(self::POLICY_SLUG, $slug) ? null : ['page' => 'page', 'category_id' => null],
            'category.show' => ['page' => 'category', 'category_id' => Category::where('slug', $slug)->value('id')],
            'product.show' => ['page' => 'product', 'category_id' => Product::where('slug', $slug)->value('category_id')],
            'article.show' => ['page' => 'article', 'category_id' => null],
            default => ['page' => 'other', 'category_id' => null],
        };
    }

    /** @param  array{page: string, category_id: ?int}  $context */
    public function targets(array $context): bool
    {
        return match ($this->targeting) {
            'home' => $context['page'] === 'home',
            'products' => $context['page'] === 'product',
            'articles' => $context['page'] === 'article',
            'categories' => in_array($context['page'], ['category', 'product'], true)
                && $context['category_id']
                && in_array($context['category_id'], $this->targetCategoryIds(), true),
            default => true,
        };
    }

    /** Kategori yang dipilih beserta seluruh turunannya. */
    private function targetCategoryIds(): array
    {
        $ids = array_map('intval', $this->target_category_ids ?? []);

        return Category::whereIn('id', $ids)->get()
            ->flatMap(fn (Category $c) => $c->descendantIds())
            ->map(fn ($id) => (int) $id)->merge($ids)->unique()->values()->all();
    }

    public function isExternal(): bool
    {
        return ExternalUrl::is($this->target_url);
    }

    /**
     * Tautan CTA. Tautan keluar diberi UTM (utm_campaign = nama kampanye) agar terbaca di GA4;
     * tautan internal tidak, karena UTM internal memecah sesi GA4. Klik internal dikirim sebagai event.
     */
    public function ctaUrl(): ?string
    {
        $url = $this->target_url;
        if (blank($url) || ! $this->isExternal() || preg_match('#^https?://(wa\.me|api\.whatsapp\.com)/#i', $url)) {
            return $url ?: null;
        }

        $query = (string) parse_url($url, PHP_URL_QUERY);
        parse_str($query, $existing);
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

    /** Konfigurasi yang dibaca JS untuk pemicu dan aturan frekuensi. */
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
}
