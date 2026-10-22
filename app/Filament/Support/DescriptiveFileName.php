<?php

namespace App\Filament\Support;

use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Nama file gambar deskriptif saat unggah (PRD 9.10.1 poin 4):
 * {slug-entitas}[-{akhiran}]-{nomor}.{ext}, huruf kecil dengan tanda hubung, bukan IMG00023.JPG.
 */
class DescriptiveFileName
{
    /** Untuk FileUpload::getUploadedFileNameForStorageUsing(). */
    public static function from(string $sourceField, string $directory, ?string $suffix = null): Closure
    {
        return function (TemporaryUploadedFile $file, Get $get) use ($sourceField, $directory, $suffix): string {
            $base = Str::slug((string) $get($sourceField))
                ?: Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                ?: 'gambar';

            return self::unique(Str::limit($base, 80, ''), $suffix, self::extension($file), $directory);
        };
    }

    private static function extension(TemporaryUploadedFile $file): string
    {
        // Ekstensi mengikuti jenis file sebenarnya, bukan nama asli (PRD 9.10.1 poin 3)
        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');

        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    private static function unique(string $base, ?string $suffix, string $ext, string $directory): string
    {
        $stem = $suffix ? "{$base}-{$suffix}" : $base;
        $disk = Storage::disk('public');

        for ($n = 1; ; $n++) {
            $name = "{$stem}-{$n}.{$ext}";
            if (! $disk->exists(trim($directory, '/').'/'.$name)) {
                return $name;
            }
        }
    }
}
