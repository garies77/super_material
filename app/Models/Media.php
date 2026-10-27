<?php

namespace App\Models;

use App\Support\ImageDerivatives;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * Satu berkas gambar di disk publik yang terdaftar di Pustaka Media.
 * `path` sama persis dengan nilai yang disimpan kolom gambar konten (mis. products/semen-1.jpg).
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    public const DISK = 'public';

    /** Label folder untuk direktori unggahan yang sudah ada. */
    public const FOLDERS = [
        'products' => 'Produk',
        'articles' => 'Artikel',
        'categories' => 'Kategori',
        'brands' => 'Merek',
        'authors' => 'Penulis',
        'kampanye' => 'Kampanye',
        'banners' => 'Banner',
        'promo' => 'Promo',
        'pages' => 'Halaman',
        'channels' => 'Kanal penjualan',
        'logos' => 'Logo',
        'seo' => 'SEO',
        'og' => 'Open Graph',
        'media' => 'Unggahan pustaka',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk(self::DISK)->url($this->path);
    }

    /** Versi kecil (WebP 320 px) untuk grid pustaka; dibuat sekali lalu disimpan di disk. */
    public function thumbnailUrl(): string
    {
        return ImageDerivatives::resized($this->url(), 320) ?? $this->url();
    }

    public function folderLabel(): string
    {
        return self::FOLDERS[$this->folder] ?? $this->folder;
    }

    public function dimensionsLabel(): ?string
    {
        return $this->width && $this->height ? "{$this->width}×{$this->height} px" : null;
    }

    public function sizeLabel(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }
}
