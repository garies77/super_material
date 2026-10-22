<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Iklan internal pada halaman artikel (PRD 8.8). Tidak ada jaringan iklan pihak ketiga.
 */
class Ad extends Model
{
    public const SLOTS = [
        'top' => 'Atas artikel',
        'in_content_1' => 'Dalam isi 1',
        'in_content_2' => 'Dalam isi 2',
        'sidebar' => 'Sidebar',
        'bottom' => 'Bawah artikel',
    ];

    public const FORMATS = [
        'image' => 'Gambar dengan tautan',
        'product' => 'Kartu produk',
        'text' => 'Teks dengan tombol CTA',
        'html' => 'HTML kustom (khusus Admin)',
    ];

    public const LABELS = ['Iklan' => 'Iklan', 'Sponsor' => 'Sponsor'];

    public const TARGETING = [
        'all' => 'Semua artikel',
        'categories' => 'Kategori artikel tertentu',
        'tags' => 'Tag tertentu',
        'articles' => 'Artikel tertentu',
    ];

    /** Paragraf bawaan untuk slot dalam isi (N = 3, M = 8). */
    public const DEFAULT_PARAGRAPH = ['in_content_1' => 3, 'in_content_2' => 8];

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (Ad $ad) {
            // HTML kustom hanya boleh dibuat atau diubah Admin, ditegakkan di backend (PRD 8.8)
            if ($ad->format === 'html' && ($ad->isDirty('html') || $ad->isDirty('format'))
                && auth()->check() && ! auth()->user()->isAdmin()) {
                throw new AuthorizationException('Hanya Admin yang dapat membuat iklan HTML kustom.');
            }

            if ($ad->html !== null) {
                $ad->html = self::sanitize($ad->html);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_scroll_hint' => 'boolean',
            'priority' => 'integer',
            'paragraph_after' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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

    public function targets(Article $article): bool
    {
        $ids = fn (?array $v) => array_map('intval', $v ?? []);

        return match ($this->targeting) {
            'categories' => $article->categories->pluck('id')->intersect($ids($this->target_category_ids))->isNotEmpty(),
            'tags' => $article->tags->pluck('id')->intersect($ids($this->target_tag_ids))->isNotEmpty(),
            'articles' => in_array($article->id, $ids($this->target_article_ids), true),
            default => true,
        };
    }

    /**
     * Satu iklan per slot untuk artikel ini. Prioritas tertinggi menang; iklan berprioritas
     * sama dirotasi acak per tampilan halaman.
     *
     * @return array<string, Ad>
     */
    public static function forArticle(Article $article): array
    {
        $chosen = self::live()->with('product.media', 'product.variants')->get()
            ->filter(fn (Ad $ad) => isset(self::SLOTS[$ad->slot]) && $ad->targets($article))
            ->groupBy('slot')
            ->map(fn (Collection $ads) => $ads->where('priority', $ads->max('priority'))->random())
            ->all();

        // Dalam isi 2 harus berada setelah Dalam isi 1
        if (isset($chosen['in_content_1'], $chosen['in_content_2'])
            && $chosen['in_content_2']->paragraphPosition() <= $chosen['in_content_1']->paragraphPosition()) {
            unset($chosen['in_content_2']);
        }

        return $chosen;
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
        $dest = (string) $this->destinationUrl();
        if ($dest === '' || Str::startsWith($dest, '/') && ! Str::startsWith($dest, '//')) {
            return false;
        }

        $host = strtolower((string) parse_url($dest, PHP_URL_HOST));
        $own = strtolower((string) parse_url(url('/'), PHP_URL_HOST));

        return $host !== '' && $host !== $own && ! Str::endsWith($host, '.'.$own);
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
