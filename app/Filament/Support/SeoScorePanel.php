<?php

namespace App\Filament\Support;

use Closure;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Panel skor SEO di samping form: dihitung dari isi form yang sedang diketik (belum disimpan),
 * memakai SeoScore yang sama dengan kolom "Skor SEO" di tabel daftar.
 *
 * Field pemicu cukup diberi ->afterStateUpdated(SeoScorePanel::refresh()) agar hanya panel
 * yang di-render ulang, bukan seluruh form.
 */
class SeoScorePanel
{
    /**
     * @param  Closure  $score  dievaluasi seperti closure Filament (Get, record, …); mengembalikan hasil SeoScore
     */
    public const KEY = 'seoScorePanel';

    public static function make(Closure $score): View
    {
        return View::make('filament.seo-score-panel')->key(self::KEY)
            ->viewData(fn (View $component) => ['result' => $component->evaluate($score)]);
    }

    /**
     * Callback afterStateUpdated untuk field pemicu: hanya panel yang di-render ulang. Panel dicari lewat key
     * di skema akar karena Filament dapat membangun ulang komponen form (referensi langsung bisa basi).
     */
    public static function refresh(): Closure
    {
        return function (Component $component): void {
            $component->getRootContainer()->getComponent(self::KEY, withHidden: true)?->partiallyRender();
        };
    }

    /**
     * Path tersimpan dari state FileUpload ([uuid => path]); unggahan yang belum disimpan dilewati
     * karena nama & ukuran berkas finalnya baru diketahui setelah simpan.
     *
     * @return array<int, string>
     */
    public static function storedPaths(mixed $state): array
    {
        return array_values(array_filter((array) $state, fn ($file) => is_string($file) && $file !== ''));
    }

    public static function hasPendingUpload(mixed $state): bool
    {
        return collect((array) $state)->contains(fn ($file) => $file instanceof TemporaryUploadedFile);
    }
}
