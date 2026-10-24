<?php

namespace App\Support;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Membersihkan berkas SVG unggahan dari <script>, atribut on*, dan referensi eksternal
 * sebelum disimpan ke disk (PRD Keamanan 4.1). Jenis berkas dibaca dari isinya, bukan ekstensi.
 */
class SvgSanitizer
{
    public static function isSvg(UploadedFile $file): bool
    {
        return $file->getMimeType() === 'image/svg+xml' || strtolower($file->getClientOriginalExtension()) === 'svg';
    }

    public static function cleanInPlace(UploadedFile $file, string $field = 'file'): void
    {
        if (! self::isSvg($file)) {
            return;
        }

        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $clean = $sanitizer->sanitize((string) file_get_contents($file->getRealPath()));

        if ($clean === false || trim($clean) === '') {
            throw ValidationException::withMessages([$field => 'Berkas SVG tidak valid atau tidak dapat dibersihkan.']);
        }

        file_put_contents($file->getRealPath(), $clean);
    }
}
