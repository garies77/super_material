<?php

namespace App\Models;

use App\Models\Concerns\GuardsCustomCode;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\TracksPublicUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

class Product extends Model
{
    use GuardsCustomCode, HasSeo, TracksPublicUrl;

    public const PUBLIC_ROUTE = 'product.show';

    protected $guarded = [];

    protected static function booted(): void
    {
        // Admin memperbarui masa berlaku harga ke masa depan: penanda tinjau harga dibersihkan
        static::saving(function (Product $product) {
            if ($product->isDirty('price_valid_until') && $product->price_valid_until?->isFuture()) {
                $product->price_review_needed = false;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'gallery' => 'array',
            'noindex' => 'boolean',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'rating_value' => 'float',
            'rating_count' => 'integer',
            'rating_updated_at' => 'date',
            'show_quantity' => 'boolean',
            'price_valid_until' => 'datetime',
            'price_review_needed' => 'boolean',
        ];
    }

    public function schemaAvailability(): ?string
    {
        return match ($this->availability) {
            'InStock' => 'https://schema.org/InStock',
            'LimitedAvailability' => 'https://schema.org/LimitedAvailability',
            'PreOrder' => 'https://schema.org/PreOrder',
            'OutOfStock' => 'https://schema.org/OutOfStock',
            default => null,
        };
    }

    public function schemaPriceValidUntil(): string
    {
        if ($this->price_valid_until && $this->price_valid_until->isFuture()) {
            return $this->price_valid_until->setTimezone('Asia/Jakarta')->toIso8601String();
        }

        return now()->addDays(30)->endOfDay()->setTimezone('Asia/Jakarta')->toIso8601String();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->where('is_active', true)->orderBy('sort_order');
    }

    public function links(): HasMany
    {
        return $this->hasMany(ProductLink::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(ClickEvent::class);
    }

    public function faqs(): MorphMany
    {
        return $this->morphMany(Faq::class, 'faqable')->orderBy('sort_order');
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_product');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true);
    }

    public function url(): string
    {
        return route('product.show', $this->slug);
    }

    /**
     * Ambil seluruh media (foto/video), dengan fallback ke image/gallery lama bila media kosong.
     *
     * @return Collection<int, ProductMedia>
     */
    public function allMedia(): Collection
    {
        $items = $this->relationLoaded('media') ? $this->media : $this->media()->get();

        if ($items->isNotEmpty()) {
            return $items;
        }

        // Fallback untuk produk legacy
        $legacy = collect();
        if ($this->image) {
            $legacy->push(new ProductMedia([
                'product_id' => $this->id,
                'type' => 'image',
                'path' => $this->image,
                'provider' => 'upload',
                'alt_text' => $this->name,
                'sort_order' => 0,
            ]));
        }

        if (is_array($this->gallery)) {
            foreach ($this->gallery as $i => $path) {
                if ($path && $path !== $this->image) {
                    $legacy->push(new ProductMedia([
                        'product_id' => $this->id,
                        'type' => 'image',
                        'path' => $path,
                        'provider' => 'upload',
                        'alt_text' => $this->name,
                        'sort_order' => $i + 1,
                    ]));
                }
            }
        }

        return $legacy;
    }

    /** Nomor WA efektif: produk > kategori > global. */
    public function whatsappNumber(): ?string
    {
        $number = $this->whatsapp_number ?: $this->category?->whatsapp_number ?: Setting::get('whatsapp_number');
        $number = preg_replace('/\D+/', '', (string) $number);
        if ($number !== '' && str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        }

        return $number ?: null;
    }

    /** Kanal yang tampil sebagai tombol beli untuk produk ini. */
    public function buyChannels(): Collection
    {
        $links = $this->links()->where('is_active', true)->get()->keyBy('sales_channel_id');

        return SalesChannel::where('is_active', true)->orderBy('sort_order')->get()
            ->filter(fn (SalesChannel $c) => $c->isWhatsapp() ? (bool) $this->whatsappNumber() : $links->has($c->id))
            ->values();
    }

    public function buildWhatsappMessage(?ProductVariant $variant = null, int $quantity = 1): string
    {
        $link = $this->links()->whereHas('channel', fn ($q) => $q->where('slug', 'whatsapp'))->first();
        $base = $link?->message ?: "Halo, saya tertarik dengan {$this->name}.";

        $extra = [];
        if ($variant) {
            $extra[] = "Varian: {$variant->name}";
        }
        if ($quantity > 1 || $this->unit) {
            $extra[] = "Jumlah: {$quantity} ".($this->unit ?: 'item');
        }

        if (! empty($extra)) {
            $base .= ' ('.implode(', ', $extra).')';
        }

        return $base."\n".$this->url();
    }

    /** Tujuan redirect untuk kanal tertentu; null bila tidak valid. */
    public function destinationFor(SalesChannel $channel, ?ProductVariant $variant = null, int $quantity = 1): ?string
    {
        if (! $channel->is_active) {
            return null;
        }

        if ($channel->isWhatsapp()) {
            $number = $this->links()->where('sales_channel_id', $channel->id)->where('is_active', true)->value('url');
            $number = $number ? preg_replace('/\D+/', '', $number) : $this->whatsappNumber();
            $message = $this->buildWhatsappMessage($variant, $quantity);

            return $number ? 'https://wa.me/'.$number.'?text='.rawurlencode($message) : null;
        }

        return $this->links()->with('channel')->where('sales_channel_id', $channel->id)->first()?->safeUrl();
    }

    public function imageUrl(): ?string
    {
        $firstMedia = $this->allMedia()->first();
        if ($firstMedia) {
            return $firstMedia->isImage() ? $firstMedia->url() : ($firstMedia->posterUrl() ?: $firstMedia->url());
        }

        return $this->image ? asset('storage/'.ltrim((string) $this->image, '/')) : null;
    }

    public function effectivePriceMin(): ?int
    {
        $vars = $this->relationLoaded('variants') ? $this->variants : $this->variants;
        $prices = $vars->filter(fn ($v) => $v->is_active ?? true)->map->effectivePrice()->filter()->values();

        if ($prices->isNotEmpty()) {
            return $prices->min();
        }

        return $this->price_min ? (int) $this->price_min : null;
    }

    public function effectivePriceMax(): ?int
    {
        $vars = $this->relationLoaded('variants') ? $this->variants : $this->variants;
        $prices = $vars->filter(fn ($v) => $v->is_active ?? true)->map->effectivePrice()->filter()->values();

        if ($prices->isNotEmpty()) {
            return $prices->max();
        }

        return $this->price_max ? (int) $this->price_max : null;
    }

    public function isPriceHidden(): bool
    {
        return $this->price_display === 'hidden';
    }

    public function priceLabel(): ?string
    {
        if ($this->isPriceHidden()) {
            return 'Hubungi kami untuk harga';
        }

        $min = $this->effectivePriceMin();
        $max = $this->effectivePriceMax();

        if (! $min) {
            return null;
        }

        $fmt = fn ($n) => 'Rp'.number_format($n, 0, ',', '.');
        $label = $fmt($min);
        if ($max && $max > $min) {
            $label .= ' – '.$fmt($max);
        }

        return $label.($this->unit ? ' / '.$this->unit : '');
    }

    public function maxDiscountPercentage(): ?int
    {
        $vars = $this->relationLoaded('variants') ? $this->variants : $this->variants;
        $discounts = $vars->filter(fn ($v) => $v->is_active ?? true)->map->discountPercentage()->filter()->values();

        return $discounts->isNotEmpty() ? $discounts->max() : null;
    }

    public function hasRating(): bool
    {
        return $this->rating_value !== null
            && $this->rating_value >= 1 && $this->rating_value <= 5
            && $this->rating_count !== null && $this->rating_count > 0;
    }

    /** Nilai rating satu desimal dengan titik, dipakai di tampilan dan JSON-LD. */
    public function formattedRatingValue(): string
    {
        return number_format((float) $this->rating_value, 1, '.', '');
    }

    /** Jumlah penilaian apa adanya agar sama persis dengan ratingCount di JSON-LD. */
    public function formattedRatingCount(): string
    {
        return (string) ($this->rating_count ?? 0);
    }

    /**
     * Blok AggregateRating untuk JSON-LD Product (skala 1–5, jadi bestRating/worstRating tidak perlu).
     * Null bila produk belum punya rating sah, sehingga blok tidak di-render.
     */
    public function aggregateRatingSchema(): ?array
    {
        if (! $this->hasRating()) {
            return null;
        }

        return [
            '@type' => 'AggregateRating',
            'ratingValue' => $this->formattedRatingValue(),
            'ratingCount' => $this->rating_count,
        ];
    }

    public function seoName(): string
    {
        return $this->name;
    }

    public function seoFallbackDescription(): ?string
    {
        return $this->summary ?: strip_tags((string) $this->description) ?: "Beli {$this->name} dengan harga acuan dan spesifikasi lengkap.";
    }

    /**
     * URL gambar utama produk untuk Open Graph, Twitter Card, dan Google SERP thumbnail.
     */
    public function primaryImageUrl(): ?string
    {
        if ($this->og_image) {
            return $this->seoImage();
        }

        $firstImage = $this->allMedia()->firstWhere(fn ($m) => $m->isImage());
        if ($firstImage) {
            return $firstImage->url();
        }

        $firstVideo = $this->allMedia()->firstWhere(fn ($m) => $m->isVideo());
        if ($firstVideo && $firstVideo->posterUrl()) {
            return $firstVideo->posterUrl();
        }

        if ($this->image) {
            return str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')
                ? $this->image
                : asset('storage/'.ltrim($this->image, '/'));
        }

        return null;
    }

    /**
     * Kumpulan URL gambar bersih (tanpa video URL YouTube dsb) untuk JSON-LD dan Google Image Sitemap.
     *
     * @return array<int, string>
     */
    public function allImageUrls(): array
    {
        $urls = [];
        foreach ($this->allMedia() as $m) {
            if ($m->isImage()) {
                $urls[] = $m->url();
            } elseif ($m->isVideo() && $m->posterUrl()) {
                $urls[] = $m->posterUrl();
            }
        }

        if (empty($urls) && $this->image) {
            $urls[] = str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')
                ? $this->image
                : asset('storage/'.ltrim($this->image, '/'));
        }

        if (is_array($this->gallery)) {
            foreach ($this->gallery as $p) {
                if ($p) {
                    $urls[] = str_starts_with($p, 'http://') || str_starts_with($p, 'https://')
                        ? $p
                        : asset('storage/'.ltrim($p, '/'));
                }
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }
}
