<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Favicon situs & panel admin mengikuti logo di Pengaturan situs, sehingga tidak perlu mengganti
 * public/favicon.svg lewat File Manager.
 *
 * - Logo PNG/JPG/WebP, atau SVG yang berisi gambar bitmap (ekspor Figma, biasanya ratusan KB):
 *   dibuat sekali menjadi PNG persegi 192 px di storage/app/public/favicons (logo utuh, tidak gepeng).
 * - SVG vektor murni: dipakai langsung (ukurannya kecil dan tajam di semua ukuran).
 * - Tanpa logo, atau logo tidak bisa diproses: public/favicon.svg bawaan.
 */
class Favicon
{
    public const SIZE = 192;

    public const DIRECTORY = 'favicons';

    /** @return array{url: string, type: string} */
    public static function resolve(): array
    {
        return once(fn () => self::fromLogo() ?? ['url' => asset('favicon.svg'), 'type' => 'image/svg+xml']);
    }

    public static function url(): string
    {
        return self::resolve()['url'];
    }

    public static function type(): string
    {
        return self::resolve()['type'];
    }

    /** @return array{url: string, type: string}|null */
    private static function fromLogo(): ?array
    {
        $logo = (string) Setting::get('site_logo');

        if ($logo === '') {
            // Logo eksternal (URL): dipakai apa adanya, tipe ditebak dari ekstensi
            $external = Setting::logoUrl();

            return $external ? ['url' => $external, 'type' => self::mimeFromExtension($external)] : null;
        }

        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            return ['url' => $logo, 'type' => self::mimeFromExtension($logo)];
        }

        $disk = Storage::disk('public');
        $logo = ltrim($logo, '/');
        if (! $disk->exists($logo)) {
            return null;
        }

        $target = self::DIRECTORY.'/'.sha1($logo.'|'.$disk->lastModified($logo)).'.png';
        if ($disk->exists($target) || self::generate($disk->path($logo), $disk->path($target))) {
            return ['url' => $disk->url($target), 'type' => 'image/png'];
        }

        // Tidak bisa dibuat PNG (mis. SVG vektor murni atau GD tidak tersedia): pakai logo langsung
        return ['url' => $disk->url($logo), 'type' => self::mimeFromExtension($logo)];
    }

    /** Buat PNG persegi transparan dengan logo di tengah (proporsi dijaga). */
    public static function generate(string $source, string $target): bool
    {
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }

        try {
            $bytes = self::rasterBytes($source);
            $image = $bytes !== null ? @imagecreatefromstring($bytes) : false;
            if (! $image) {
                return false;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $scale = min(self::SIZE / $width, self::SIZE / $height);
            $fitWidth = max(1, (int) round($width * $scale));
            $fitHeight = max(1, (int) round($height * $scale));

            $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            imagealphablending($canvas, true);
            imagecopyresampled($canvas, $image, intdiv(self::SIZE - $fitWidth, 2), intdiv(self::SIZE - $fitHeight, 2), 0, 0, $fitWidth, $fitHeight, $width, $height);

            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }

            return imagepng($canvas, $target, 9);
        } catch (Throwable) {
            return false;
        }
    }

    /** Isi bitmap logo: berkas raster apa adanya, atau gambar terbesar yang tertanam di SVG. */
    private static function rasterBytes(string $source): ?string
    {
        $contents = (string) file_get_contents($source);

        if (! str_ends_with(strtolower($source), '.svg')) {
            return $contents;
        }

        preg_match_all('#data:image/(?:png|jpe?g|webp);base64,([A-Za-z0-9+/=\s]+)#', $contents, $matches);
        $largest = collect($matches[1])->sortByDesc(fn (string $data) => strlen($data))->first();

        return $largest ? (base64_decode(preg_replace('/\s+/', '', $largest), true) ?: null) : null;
    }

    private static function mimeFromExtension(string $path): string
    {
        return match (strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            default => 'image/png',
        };
    }
}
