<?php

namespace App\Models;

use App\Support\LinkTarget;
use Database\Factories\LinkItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Satu blok di halaman link-in-bio. Tujuan klik selalu dihitung dari data ini (tidak pernah dari URL), jadi bukan open redirect. */
class LinkItem extends Model
{
    /** @use HasFactory<LinkItemFactory> */
    use HasFactory;

    public const TYPES = [
        'link' => 'Tautan',
        'heading' => 'Judul pemisah',
        'product' => 'Produk dari katalog',
        'whatsapp' => 'Tombol WhatsApp',
    ];

    protected $guarded = [];

    /** Hanya untuk pratinjau admin: URL gambar unggahan yang belum disimpan. */
    public ?string $previewThumbnailUrl = null;

    protected function casts(): array
    {
        return [
            'new_tab' => 'boolean',
            'is_highlighted' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function linkPage(): BelongsTo
    {
        return $this->belongsTo(LinkPage::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class, 'sales_channel_id');
    }

    /** Tombol yang aktif (tampil di halaman publik). */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isClickable(): bool
    {
        return $this->type !== 'heading';
    }

    public function thumbnailUrl(): ?string
    {
        if ($this->previewThumbnailUrl) {
            return $this->previewThumbnailUrl;
        }

        if ($this->thumbnail_path) {
            return Storage::disk('public')->url($this->thumbnail_path);
        }

        return $this->type === 'product' ? $this->product?->imageUrl() : null;
    }

    /** Tujuan akhir klik; null bila tidak bisa ditentukan (produk belum terbit, nomor WhatsApp kosong, dsb.). */
    public function destination(): ?string
    {
        return match ($this->type) {
            'link' => LinkTarget::url($this->only(['link_type', 'page_id', 'url'])),
            'product' => $this->productDestination(),
            'whatsapp' => $this->whatsappDestination(),
            default => null,
        };
    }

    public function opensInNewTab(): bool
    {
        return $this->type !== 'link' || $this->new_tab;
    }

    /** Produk menuju jalur /go agar klik ke marketplace ikut tercatat di laporan klik produk. */
    private function productDestination(): ?string
    {
        $product = $this->product;
        if (! $product?->is_published) {
            return null;
        }

        $channel = $this->channel;
        if (! $channel?->is_active) {
            return $product->url();
        }

        return route('go', [$product->slug, $channel->slug]).'?'.http_build_query([
            'utm_source' => 'link-bio',
            'utm_medium' => 'link-bio',
            'utm_campaign' => $this->linkPage?->slug,
        ]);
    }

    private function whatsappDestination(): ?string
    {
        $number = preg_replace('/\D+/', '', (string) ($this->whatsapp_number ?: Setting::get('whatsapp_number')));
        if (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        }

        if ($number === '') {
            return null;
        }

        return 'https://wa.me/'.$number.($this->whatsapp_message ? '?text='.rawurlencode($this->whatsapp_message) : '');
    }
}
