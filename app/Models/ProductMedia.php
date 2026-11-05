<?php

namespace App\Models;

use App\Support\Seo\VideoData;
use App\Support\YouTube;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMedia extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'upload_date' => 'datetime',
            'duration_seconds' => 'integer',
            'clips' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    public function youtubeVideoId(): ?string
    {
        if ($this->provider !== 'youtube' && ! str_contains((string) $this->path, 'youtube.com') && ! str_contains((string) $this->path, 'youtu.be')) {
            return null;
        }

        return YouTube::videoId($this->path);
    }

    public function url(): string
    {
        if (str_starts_with((string) $this->path, 'http://') || str_starts_with((string) $this->path, 'https://')) {
            return $this->path;
        }

        return asset('storage/'.ltrim((string) $this->path, '/'));
    }

    public function posterUrl(): ?string
    {
        if ($this->poster_path) {
            if (str_starts_with($this->poster_path, 'http://') || str_starts_with($this->poster_path, 'https://')) {
                return $this->poster_path;
            }

            return asset('storage/'.ltrim((string) $this->poster_path, '/'));
        }

        if ($this->isVideo() && ($vid = $this->youtubeVideoId())) {
            return YouTube::thumbnailUrl($vid);
        }

        return null;
    }

    /**
     * Data VideoObject. Judul memakai alt_text; tanggal unggah cadangan = waktu video ditambahkan ke produk.
     */
    public function toVideoData(string $pageUrl, string $fallbackTitle): VideoData
    {
        $youtubeId = $this->youtubeVideoId();

        return new VideoData(
            title: trim((string) $this->alt_text) ?: $fallbackTitle,
            description: $this->description,
            thumbnailUrl: $this->posterUrl(),
            uploadDate: $this->upload_date ?? $this->created_at,
            durationSeconds: $this->duration_seconds,
            youtubeId: $youtubeId,
            fileUrl: $youtubeId ? null : $this->url(),
            pageUrl: $pageUrl,
            clips: Video::normalizeClips($this->clips),
            creatorName: $this->creator_name,
            creatorUrl: $this->creator_url,
        );
    }

    public function embedUrl(): ?string
    {
        if ($vid = $this->youtubeVideoId()) {
            return "https://www.youtube-nocookie.com/embed/{$vid}?autoplay=1&rel=0";
        }

        return null;
    }
}
