<?php

namespace App\Models;

use App\Http\Controllers\SeoController;
use App\Support\Seo\VideoData;
use App\Support\YouTube;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Video yang ditempel di konten (saat ini artikel). Kolom metadata mengikuti properti VideoObject Google.
 */
class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'upload_date' => 'datetime',
            'duration_seconds' => 'integer',
            'clips' => 'array',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Video $video) {
            $video->provider = YouTube::isYouTubeUrl($video->url) ? 'youtube' : 'upload';
        });

        // Satu video utama per konten: video utama baru menggeser yang lama
        static::saved(function (Video $video) {
            if ($video->is_primary) {
                static::query()
                    ->where('videoable_type', $video->videoable_type)
                    ->where('videoable_id', $video->videoable_id)
                    ->whereKeyNot($video->getKey())
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            SeoController::flushSitemaps();
        });

        static::deleted(fn () => SeoController::flushSitemaps());
    }

    public function videoable(): MorphTo
    {
        return $this->morphTo();
    }

    public function youtubeId(): ?string
    {
        return YouTube::videoId($this->url);
    }

    public function fileUrl(): ?string
    {
        if ($this->youtubeId() || ! $this->url) {
            return null;
        }

        return str_starts_with($this->url, 'http://') || str_starts_with($this->url, 'https://')
            ? $this->url
            : asset('storage/'.ltrim($this->url, '/'));
    }

    public function thumbnailUrl(): ?string
    {
        if ($this->thumbnail_path) {
            return str_starts_with($this->thumbnail_path, 'http://') || str_starts_with($this->thumbnail_path, 'https://')
                ? $this->thumbnail_path
                : asset('storage/'.ltrim($this->thumbnail_path, '/'));
        }

        return ($id = $this->youtubeId()) ? YouTube::thumbnailUrl($id) : null;
    }

    public function toVideoData(string $pageUrl): VideoData
    {
        return new VideoData(
            title: (string) $this->title,
            description: $this->description,
            thumbnailUrl: $this->thumbnailUrl(),
            uploadDate: $this->upload_date ?? $this->created_at,
            durationSeconds: $this->duration_seconds,
            youtubeId: $this->youtubeId(),
            fileUrl: $this->fileUrl(),
            pageUrl: $pageUrl,
            clips: self::normalizeClips($this->clips),
            creatorName: $this->creator_name,
            creatorUrl: $this->creator_url,
        );
    }

    /**
     * @return array<int, array{name: string, start: int, end: ?int}>
     */
    public static function normalizeClips(?array $clips): array
    {
        return collect($clips ?? [])
            ->filter(fn ($clip) => is_array($clip) && isset($clip['name'], $clip['start']) && is_numeric($clip['start']))
            ->map(fn (array $clip) => [
                'name' => (string) $clip['name'],
                'start' => (int) $clip['start'],
                'end' => isset($clip['end']) && is_numeric($clip['end']) ? (int) $clip['end'] : null,
            ])
            ->values()
            ->all();
    }
}
