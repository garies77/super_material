<?php

namespace App\Models\Concerns;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Custom HTML/CSS/JS dijalankan langsung di halaman publik, jadi hanya pengguna berizin yang boleh mengubahnya.
 * Ditegakkan di model agar berlaku untuk semua jalur (form Filament, API, impor), bukan hanya form yang menyembunyikan field.
 * Proses tanpa pengguna login (seeder, artisan, antrean) tidak dibatasi.
 */
trait GuardsCustomCode
{
    public const CUSTOM_CODE_FIELDS = ['custom_html', 'custom_css', 'custom_js'];

    protected static function bootGuardsCustomCode(): void
    {
        static::saving(function ($model) {
            $user = auth()->user();
            if ($user && ! $user->canManageCustomHtml() && $model->isDirty(self::CUSTOM_CODE_FIELDS)) {
                throw new AuthorizationException('Anda tidak memiliki izin mengelola Custom HTML.');
            }
        });
    }
}
