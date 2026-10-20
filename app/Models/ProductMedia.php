<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMedia extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
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

        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $this->path, $match)) {
            return $match[1];
        }

        return null;
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
            return "https://img.youtube.com/vi/{$vid}/hqdefault.jpg";
        }

        return null;
    }

    public function embedUrl(): ?string
    {
        if ($vid = $this->youtubeVideoId()) {
            return "https://www.youtube-nocookie.com/embed/{$vid}?autoplay=1&rel=0";
        }

        return null;
    }
}
