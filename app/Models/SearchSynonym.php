<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Sinonim pencarian: pencarian yang memuat "term" (mis. "hebel") juga mencari tiap kata di "synonyms"
 * (mis. "bata ringan"). Disimpan huruf kecil; daftar di-cache dan dibersihkan saat diubah.
 */
class SearchSynonym extends Model
{
    public const CACHE_KEY = 'search.synonyms';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['synonyms' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (SearchSynonym $synonym) {
            $synonym->term = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $synonym->term)));
            $synonym->synonyms = collect($synonym->synonyms)
                ->map(fn ($word) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $word))))
                ->filter(fn (string $word) => $word !== '' && $word !== $synonym->term)
                ->unique()->values()->all();
        });

        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** @return array<string, list<string>> kata => sinonim */
    public static function map(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, fn () => static::query()->pluck('synonyms', 'term')
            ->map(fn ($synonyms) => is_array($synonyms) ? $synonyms : (json_decode((string) $synonyms, true) ?: []))
            ->all());
    }
}
