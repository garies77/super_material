<?php

namespace App\Models;

use App\Support\ImageDerivatives;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Satu berkas gambar di disk publik yang terdaftar di Pustaka Media.
 * `path` sama persis dengan nilai yang disimpan kolom gambar konten (mis. products/semen-1.jpg).
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    public const DISK = 'public';

    public const THUMBNAIL_WIDTH = 320;

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
            'focal_x' => 'integer',
            'focal_y' => 'integer',
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

    /**
     * Versi kecil untuk grid pustaka. Hanya membaca kolom tersimpan (tanpa akses disk);
     * belum ada thumbnail = berkas asli.
     */
    public function thumbnailUrl(): string
    {
        return Storage::disk(self::DISK)->url($this->thumbnail_path ?: $this->path);
    }

    /**
     * Buat thumbnail WebP 320 px sekali lalu simpan path-nya. SVG berisi bitmap tersemat (ekspor Figma, ratusan KB)
     * memakai bitmap terbesarnya; SVG vektor murni dan gambar yang gagal diproses memakai berkas asli.
     */
    public function generateThumbnail(): void
    {
        $isSvg = $this->mime_type === 'image/svg+xml';
        $source = $isSvg ? $this->embeddedBitmapPath() : Storage::disk(self::DISK)->path($this->path);
        $url = $source ? ImageDerivatives::resized($source, self::THUMBNAIL_WIDTH) : null;
        $marker = '/storage/derivatives/';

        if ($isSvg && $source) {
            @unlink($source);
        }

        $this->update(['thumbnail_path' => $url && str_contains($url, $marker) ? 'derivatives/'.Str::after($url, $marker) : $this->path]);
    }

    /** Tulis bitmap base64 terbesar di dalam SVG ke berkas sementara; null bila SVG tidak memuat bitmap. */
    private function embeddedBitmapPath(): ?string
    {
        preg_match_all('#data:image/(png|jpe?g|webp);base64,([A-Za-z0-9+/=\s]+)#', (string) Storage::disk(self::DISK)->get($this->path), $matches, PREG_SET_ORDER);
        $largest = collect($matches)->sortByDesc(fn (array $match) => strlen($match[2]))->first();

        if (! $largest || ($bitmap = base64_decode(preg_replace('/\s+/', '', $largest[2]), true)) === false) {
            return null;
        }

        // Jenis gambar dibaca dari isinya (getimagesize), jadi berkas sementara tidak perlu ekstensi
        $path = tempnam(sys_get_temp_dir(), 'svgthumb');
        file_put_contents($path, $bitmap);

        return $path;
    }

    /** Rasio umum untuk label & filter pustaka; toleransi 2% agar 1200×630 tetap terbaca 1.91:1. */
    public const RATIOS = [
        '1:1' => 1, '4:3' => 4 / 3, '3:4' => 3 / 4, '3:2' => 3 / 2, '16:9' => 16 / 9, '16:10' => 16 / 10,
        '1.91:1' => 1200 / 630, '32:15' => 32 / 15, '4:1' => 4, '728:250' => 728 / 250, '6:5' => 300 / 250,
    ];

    public const RATIO_TOLERANCE = 0.02;

    public function matchesRatio(float $ratio): bool
    {
        return $this->width && $this->height && abs($this->width / $this->height - $ratio) <= $ratio * self::RATIO_TOLERANCE;
    }

    /** Gambar raster dengan dimensi diketahui yang bisa dipotong GD (SVG tidak). */
    public function isCroppable(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) && $this->width && $this->height;
    }

    public function ratioLabel(): ?string
    {
        return collect(self::RATIOS)->search(fn (float $ratio) => $this->matchesRatio($ratio)) ?: null;
    }

    public function scopeWithRatio(Builder $q, float $ratio): Builder
    {
        return $q->whereNotNull('width')->where('height', '>', 0)
            ->whereRaw('ABS(width - height * ?) <= height * ? * ?', [$ratio, $ratio, self::RATIO_TOLERANCE]);
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
