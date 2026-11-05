<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Mengambil judul & channel video YouTube lewat oEmbed publik (tanpa API key) untuk mengisi form video.
 */
class YouTubeMetadata
{
    /**
     * @return array{title: ?string, thumbnail_url: ?string, author_name: ?string, author_url: ?string}|null
     */
    public static function fetch(string $videoId): ?array
    {
        return Cache::remember('youtube-oembed:v2:'.$videoId, now()->addDay(), function () use ($videoId) {
            try {
                $response = Http::timeout(5)->get('https://www.youtube.com/oembed', [
                    'url' => YouTube::watchUrl($videoId),
                    'format' => 'json',
                ]);
            } catch (Throwable $e) {
                report($e);

                return null;
            }

            return $response->successful()
                ? [
                    'title' => $response->json('title'),
                    'thumbnail_url' => $response->json('thumbnail_url'),
                    'author_name' => $response->json('author_name'),
                    'author_url' => $response->json('author_url'),
                ]
                : null;
        });
    }
}
