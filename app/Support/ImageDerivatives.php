<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ImageDerivatives
{
    /**
     * Tiga rasio aspek standar Google Images & Discover (PRD 9.13.3 & SEO-01):
     * - 16:9 (1200 x 675)
     * - 4:3  (1200 x 900)
     * - 1:1  (1200 x 1200)
     */
    public const SIZES = [
        '16x9' => ['width' => 1200, 'height' => 675],
        '4x3' => ['width' => 1200, 'height' => 900],
        '1x1' => ['width' => 1200, 'height' => 1200],
    ];

    /**
     * Dapatkan larik URL tiga rasio gambar (16:9, 4:3, 1:1) untuk properti JSON-LD 'image'.
     *
     * @return array<int, string>
     */
    public static function multiRatioUrls(?string $sourceUrlOrPath): array
    {
        if (empty($sourceUrlOrPath)) {
            return [];
        }

        $localPath = self::resolveLocalPath($sourceUrlOrPath);
        if (! $localPath || ! file_exists($localPath) || ! extension_loaded('gd')) {
            return [$sourceUrlOrPath];
        }

        $urls = [];
        $derivativesDir = storage_path('app/public/derivatives');
        if (! File::isDirectory($derivativesDir)) {
            File::makeDirectory($derivativesDir, 0755, true, true);
        }

        $hash = substr(md5($localPath.'_'.filemtime($localPath)), 0, 16);

        foreach (self::SIZES as $ratio => $dimensions) {
            $filename = "{$hash}_{$ratio}.webp";
            $targetPath = "{$derivativesDir}/{$filename}";
            $targetUrl = asset("storage/derivatives/{$filename}");

            if (file_exists($targetPath)) {
                $urls[] = $targetUrl;

                continue;
            }

            try {
                if (self::createDerivative($localPath, $targetPath, $dimensions['width'], $dimensions['height'])) {
                    $urls[] = $targetUrl;
                } else {
                    $urls[] = $sourceUrlOrPath;
                }
            } catch (\Throwable) {
                $urls[] = $sourceUrlOrPath;
            }
        }

        return ! empty($urls) ? array_values(array_unique($urls)) : [$sourceUrlOrPath];
    }

    /**
     * URL versi WebP selebar $width (rasio asli, tidak diperbesar), dibuat sekali lalu disimpan di disk.
     * SVG, gambar eksternal, atau bila GD gagal: kembalikan URL asli.
     */
    public static function resized(?string $sourceUrlOrPath, int $width): ?string
    {
        if (empty($sourceUrlOrPath) || str_ends_with(strtolower(parse_url($sourceUrlOrPath, PHP_URL_PATH) ?? ''), '.svg')) {
            return $sourceUrlOrPath;
        }

        $localPath = self::resolveLocalPath($sourceUrlOrPath);
        $size = $localPath ? @getimagesize($localPath) : false;
        if (! $size || ! extension_loaded('gd') || ! function_exists('imagewebp')) {
            return $sourceUrlOrPath;
        }

        $width = min($width, $size[0]);
        $filename = substr(md5($localPath.'_'.filemtime($localPath)), 0, 16)."_w{$width}.webp";
        $targetPath = storage_path("app/public/derivatives/{$filename}");

        if (! file_exists($targetPath)) {
            File::ensureDirectoryExists(dirname($targetPath));
            try {
                if (! self::createDerivative($localPath, $targetPath, $width, (int) round($size[1] * $width / $size[0]))) {
                    return $sourceUrlOrPath;
                }
            } catch (\Throwable) {
                return $sourceUrlOrPath;
            }
        }

        return asset("storage/derivatives/{$filename}");
    }

    /** Atribut srcset dari beberapa lebar; kosong bila gambar tidak bisa diubah ukurannya (mis. SVG). */
    public static function srcset(?string $sourceUrlOrPath, array $widths): string
    {
        $urls = collect($widths)->mapWithKeys(fn ($w) => [$w => self::resized($sourceUrlOrPath, $w)])
            ->reject(fn ($url) => $url === $sourceUrlOrPath)->unique();

        return $urls->map(fn ($url, $w) => "{$url} {$w}w")->implode(', ');
    }

    /**
     * Lebar & tinggi asli gambar lokal (raster atau atribut SVG) untuk atribut width/height (mencegah CLS).
     *
     * @return array{0: int, 1: int}|null
     */
    public static function dimensions(?string $sourceUrlOrPath): ?array
    {
        $localPath = $sourceUrlOrPath ? self::resolveLocalPath($sourceUrlOrPath) : null;
        if (! $localPath) {
            return null;
        }

        if (str_ends_with(strtolower($localPath), '.svg')) {
            $head = (string) file_get_contents($localPath, false, null, 0, 1024);
            if (preg_match('/<svg[^>]*\bwidth="([\d.]+)"[^>]*\bheight="([\d.]+)"/i', $head, $m)
                || preg_match('/viewBox="[\d.\-]+ [\d.\-]+ ([\d.]+) ([\d.]+)"/i', $head, $m)) {
                return [(int) round((float) $m[1]), (int) round((float) $m[2])];
            }

            return null;
        }

        $size = @getimagesize($localPath);

        return $size ? [$size[0], $size[1]] : null;
    }

    /**
     * Hitung total piksel (lebar x tinggi) untuk validasi minimal 50.000 px (PRD 9.13.3).
     */
    public static function pixelCount(string $filePath): int
    {
        if (! file_exists($filePath)) {
            return 0;
        }

        $size = @getimagesize($filePath);
        if (! $size || empty($size[0]) || empty($size[1])) {
            return 0;
        }

        return (int) ($size[0] * $size[1]);
    }

    private static function resolveLocalPath(string $urlOrPath): ?string
    {
        if (file_exists($urlOrPath)) {
            return $urlOrPath;
        }

        $parsed = parse_url($urlOrPath, PHP_URL_PATH) ?? '';
        $clean = ltrim($parsed, '/');

        // Jika path berupa "storage/..."
        if (str_starts_with($clean, 'storage/')) {
            $relative = substr($clean, strlen('storage/'));
            $path = storage_path('app/public/'.$relative);
            if (file_exists($path)) {
                return $path;
            }
        }

        // Cek langsung di storage/app/public
        $publicStorage = storage_path('app/public/'.$clean);
        if (file_exists($publicStorage)) {
            return $publicStorage;
        }

        // Cek di public_path
        $publicDirect = public_path($clean);
        if (file_exists($publicDirect)) {
            return $publicDirect;
        }

        return null;
    }

    /**
     * Potong (cover) ke ukuran target lalu simpan sebagai WebP 85 (JPEG bila WebP tidak tersedia).
     * Area potong berpusat di titik fokus (persen); default tengah.
     */
    public static function createDerivative(string $sourcePath, string $targetPath, int $targetW, int $targetH, float $focalX = 50, float $focalY = 50): bool
    {
        $info = @getimagesize($sourcePath);
        if (! $info) {
            return false;
        }

        $srcW = $info[0];
        $srcH = $info[1];
        $mime = $info['mime'] ?? '';

        $srcImg = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null,
            'image/gif' => @imagecreatefromgif($sourcePath),
            default => null,
        };

        if (! $srcImg) {
            return false;
        }

        // Hitung area crop (cover) yang berpusat di titik fokus, dijepit agar tetap di dalam gambar
        $srcRatio = $srcW / $srcH;
        $targetRatio = $targetW / $targetH;

        if ($srcRatio > $targetRatio) {
            // Gambar sumber lebih lebar: crop sisi kiri/kanan
            $cropW = (int) round($srcH * $targetRatio);
            $cropH = $srcH;
        } else {
            // Gambar sumber lebih tinggi: crop atas/bawah
            $cropW = $srcW;
            $cropH = (int) round($srcW / $targetRatio);
        }

        $cropX = (int) max(0, min($srcW - $cropW, round($srcW * $focalX / 100 - $cropW / 2)));
        $cropY = (int) max(0, min($srcH - $cropH, round($srcH * $focalY / 100 - $cropH / 2)));

        $dstImg = imagecreatetruecolor($targetW, $targetH);

        // Alpha channel transparency support
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
        imagefilledrectangle($dstImg, 0, 0, $targetW, $targetH, $transparent);

        imagecopyresampled($dstImg, $srcImg, 0, 0, $cropX, $cropY, $targetW, $targetH, $cropW, $cropH);

        $saved = false;
        if (function_exists('imagewebp')) {
            $saved = @imagewebp($dstImg, $targetPath, 85);
        } else {
            $targetPathJpg = preg_replace('/\.webp$/', '.jpg', $targetPath);
            $saved = @imagejpeg($dstImg, $targetPathJpg, 85);
        }

        imagedestroy($dstImg);
        imagedestroy($srcImg);

        return $saved;
    }
}
