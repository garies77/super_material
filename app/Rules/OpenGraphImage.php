<?php

namespace App\Rules;

use App\Models\Media;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Gambar Open Graph yang benar-benar dipakai Facebook, WhatsApp, X, dan LinkedIn untuk kartu besar:
 * JPG/PNG/WebP (bukan SVG/GIF), rasio 1.91:1, minimal 600×315 px. Berlaku untuk unggahan baru
 * maupun gambar yang dipilih dari Pustaka Media (path di disk publik).
 */
class OpenGraphImage implements ValidationRule
{
    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public const RATIO = 1200 / 630;

    public const MIN_WIDTH = 600;

    public const MIN_HEIGHT = 315;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $file = match (true) {
            $value instanceof UploadedFile => $value->getRealPath(),
            is_string($value) && $value !== '' && ! str_contains($value, '..') => Storage::disk(Media::DISK)->path(ltrim($value, '/')),
            default => null,
        };

        if (! $file || ! is_file($file)) {
            $fail('Berkas gambar Open Graph tidak ditemukan. Unggah ulang atau pilih gambar lain.');

            return;
        }

        $size = @getimagesize($file);
        if (! $size || ! in_array($size['mime'] ?? null, self::MIME_TYPES, true)) {
            $fail('Gambar Open Graph harus JPG, PNG, atau WebP. SVG dan GIF tidak didukung Facebook, WhatsApp, dan X.');

            return;
        }

        [$width, $height] = $size;
        if (abs($width / $height - self::RATIO) > self::RATIO * Media::RATIO_TOLERANCE) {
            $fail("Rasio gambar Open Graph harus 1.91:1 (mis. 1200×630 px); gambar ini {$width}×{$height} px. Klik ikon pensil untuk memotongnya.");

            return;
        }

        if ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT) {
            $fail('Gambar Open Graph minimal '.self::MIN_WIDTH.'×'.self::MIN_HEIGHT." px (disarankan 1200×630 px); gambar ini {$width}×{$height} px.");
        }
    }
}
