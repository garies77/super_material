<?php

namespace App\Models;

use App\Support\LinkBioDesign;
use App\Support\SocialPlatforms;
use Database\Factories\LinkPageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Halaman link-in-bio (seperti Linktree) di /l/{slug}: profil, deretan tombol tautan, dan ikon media sosial.
 * Tayangan dan klik dicatat sebagai agregat harian di link_stats (App\Support\Reports\LinkRecorder).
 */
class LinkPage extends Model
{
    /** @use HasFactory<LinkPageFactory> */
    use HasFactory;

    /** Platform ikon sosial: yang ada di SocialPlatforms plus TikTok & YouTube (ikonnya sudah ada di partials/social-icon). */
    public const SOCIAL_HOSTS = [
        'tiktok' => ['label' => 'TikTok', 'hosts' => ['tiktok.com']],
        'youtube' => ['label' => 'YouTube', 'hosts' => ['youtube.com', 'youtu.be']],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['socials' => 'array', 'design' => 'array', 'is_published' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(LinkItem::class)->orderBy('sort')->orderBy('id');
    }

    public function stats(): HasMany
    {
        return $this->hasMany(LinkStat::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function url(): string
    {
        return route('link.show', $this->slug);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path ? Storage::disk('public')->url($this->banner_path) : null;
    }

    /** @return array<string, string|null> pengaturan tampilan yang sudah disaring (App\Support\LinkBioDesign) */
    public function resolvedDesign(): array
    {
        return LinkBioDesign::forPage($this);
    }

    /** @return array<string, string> */
    public static function socialOptions(): array
    {
        return SocialPlatforms::options() + array_map(fn ($p) => $p['label'], self::SOCIAL_HOSTS);
    }

    /** URL profil wajib https dan berada di domain resmi platform. */
    public static function isValidSocialUrl(?string $platform, ?string $url): bool
    {
        if (isset(self::SOCIAL_HOSTS[$platform])) {
            $url = trim((string) $url);
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));

            return str_starts_with(strtolower($url), 'https://')
                && collect(self::SOCIAL_HOSTS[$platform]['hosts'])->contains(fn ($h) => $host === $h || str_ends_with($host, '.'.$h));
        }

        return SocialPlatforms::isValidProfileUrl((string) $platform, $url);
    }

    /** @return array<int, array{platform: string, url: string}> */
    public function validSocials(): array
    {
        return collect($this->socials ?? [])
            ->filter(fn ($s) => self::isValidSocialUrl($s['platform'] ?? null, $s['url'] ?? null))
            ->map(fn ($s) => ['platform' => $s['platform'], 'url' => trim($s['url'])])
            ->values()->all();
    }
}
