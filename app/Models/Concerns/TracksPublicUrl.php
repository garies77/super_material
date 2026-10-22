<?php

namespace App\Models\Concerns;

use App\Http\Controllers\SeoController;
use App\Support\SlugRedirects;

/**
 * Observer siklus hidup rekaman publik (PRD 9.16):
 * - slug berubah → redirect 301 dari URL lama;
 * - disimpan/dihapus → pecahan sitemap diregenerasi.
 *
 * Model wajib mendefinisikan konstanta PUBLIC_ROUTE (nama rute dengan parameter slug).
 */
trait TracksPublicUrl
{
    protected static function bootTracksPublicUrl(): void
    {
        static::updated(function ($model) {
            if ($model->wasChanged('slug') && filled($model->getOriginal('slug'))) {
                SlugRedirects::record(
                    $model->publicPath($model->getOriginal('slug')),
                    $model->publicPath($model->slug),
                );
            }
        });

        static::saved(fn () => SeoController::flushSitemaps());
        static::deleted(fn () => SeoController::flushSitemaps());
    }

    public function publicPath(string $slug): string
    {
        return route(static::PUBLIC_ROUTE, $slug, false);
    }
}
