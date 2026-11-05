<?php

namespace App\Support\Seo;

use Illuminate\Support\Str;

/**
 * Menyusun JSON-LD VideoObject sesuai panduan Google (name, thumbnailUrl, uploadDate wajib).
 * Video yang belum lengkap menghasilkan null agar markup tidak valid tidak pernah dikeluarkan.
 *
 * @see https://developers.google.com/search/docs/appearance/structured-data/video
 */
class VideoSchema
{
    public const DESCRIPTION_LIMIT = 2000;

    /** Google: momen penting (Clip) hanya untuk video berdurasi minimal 30 detik. */
    public const MIN_CLIP_VIDEO_SECONDS = 30;

    /**
     * @return array<string, mixed>|null
     */
    public static function make(VideoData $video, ?string $fallbackDescription = null): ?array
    {
        if (! $video->isComplete()) {
            return null;
        }

        return array_filter([
            '@type' => 'VideoObject',
            'name' => Str::limit($video->title, 250, ''),
            'description' => Str::limit(self::description($video, $fallbackDescription), self::DESCRIPTION_LIMIT),
            'thumbnailUrl' => [$video->thumbnailUrl],
            'uploadDate' => $video->uploadDate->toIso8601String(),
            'duration' => $video->durationSeconds ? VideoDuration::iso($video->durationSeconds) : null,
            'contentUrl' => $video->fileUrl,
            'embedUrl' => $video->embedUrl(),
            // Hanya bila pembuat diketahui (nama channel YouTube); tidak ditebak dari nama situs
            'creator' => $video->creatorName ? array_filter([
                '@type' => 'Organization',
                'name' => $video->creatorName,
                'url' => $video->creatorUrl,
            ]) : null,
            'hasPart' => self::clips($video) ?: null,
        ], fn ($value) => $value !== null);
    }

    /** VideoObject mandiri (dengan @context) untuk satu tag script tersendiri. */
    public static function standalone(VideoData $video, ?string $fallbackDescription = null): ?array
    {
        $schema = self::make($video, $fallbackDescription);

        return $schema ? ['@context' => 'https://schema.org', ...$schema] : null;
    }

    /**
     * Beberapa video: satu objek bila hanya satu, array bila lebih; null bila tidak ada yang lengkap.
     *
     * @param  iterable<VideoData>  $videos
     * @return array<string, mixed>|array<int, array<string, mixed>>|null
     */
    public static function many(iterable $videos, ?string $fallbackDescription = null): ?array
    {
        $items = [];
        foreach ($videos as $video) {
            if ($schema = self::make($video, $fallbackDescription)) {
                $items[] = $schema;
            }
        }

        return match (count($items)) {
            0 => null,
            1 => $items[0],
            default => $items,
        };
    }

    /**
     * Momen penting: diurutkan, yang melewati durasi dibuang, endOffset diisi dari momen berikutnya/durasi.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function clips(VideoData $video): array
    {
        $duration = $video->durationSeconds;
        if ($duration !== null && $duration < self::MIN_CLIP_VIDEO_SECONDS) {
            return [];
        }

        $clips = collect($video->clips)
            ->filter(fn ($clip) => trim((string) ($clip['name'] ?? '')) !== '' && is_int($clip['start'] ?? null) && $clip['start'] >= 0)
            ->filter(fn ($clip) => $duration === null || $clip['start'] < $duration)
            ->sortBy('start')
            ->unique('start')
            ->values();

        return $clips->map(function (array $clip, int $i) use ($clips, $duration, $video) {
            $next = $clips[$i + 1]['start'] ?? null;
            $end = $clip['end'] ?? null;
            if (! is_int($end) || $end <= $clip['start'] || ($duration !== null && $end > $duration)) {
                $end = $next ?? $duration;
            }

            return array_filter([
                '@type' => 'Clip',
                'name' => Str::limit(trim($clip['name']), 120, ''),
                'startOffset' => $clip['start'],
                'endOffset' => $end,
                'url' => self::timestampUrl($video->pageUrl, $clip['start']),
            ], fn ($value) => $value !== null);
        })->all();
    }

    public static function timestampUrl(string $pageUrl, int $seconds): string
    {
        return $pageUrl.(str_contains($pageUrl, '?') ? '&' : '?').'t='.$seconds;
    }

    /**
     * Deskripsi harus unik per video: tanpa deskripsi sendiri, judul video digabung dengan ringkasan halaman
     * agar beberapa video di halaman yang sama tidak berbagi teks yang persis sama.
     */
    private static function description(VideoData $video, ?string $fallbackDescription): string
    {
        if ($own = self::plainText($video->description)) {
            return $own;
        }

        $title = self::plainText($video->title);
        $fallback = self::plainText($fallbackDescription);

        return $fallback ? rtrim($title, '.!?').'. '.$fallback : $title;
    }

    private static function plainText(?string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5)));
    }
}
