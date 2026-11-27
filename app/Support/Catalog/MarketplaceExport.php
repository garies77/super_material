<?php

namespace App\Support\Catalog;

use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use GdImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use ZipArchive;

/**
 * Bahan untuk mengunggah produk ke marketplace (Shopee, Tokopedia, dll.): foto siap unggah dalam ZIP
 * dan spreadsheet berisi data produk. Berkas asli di situs tidak diubah.
 */
class MarketplaceExport
{
    /** Ukuran foto yang disarankan marketplace (Shopee: 1:1, ±1024 px). */
    public const PHOTO_SIZE = 1024;

    /** Batas ukuran berkas foto marketplace. */
    public const MAX_PHOTO_BYTES = 2 * 1024 * 1024;

    /** Batas produk per unduhan agar pemrosesan foto tidak melewati batas waktu server. */
    public const MAX_PRODUCTS = 30;

    /** Kolom foto di spreadsheet (Shopee menerima hingga 9 foto per produk). */
    private const PHOTO_COLUMNS = 9;

    /**
     * ZIP berisi semua foto produk yang sudah diubah ke format marketplace: JPG 1024×1024 (1:1, foto utuh di
     * latar putih), 72 DPI, maksimal 2 MB. Satu produk = berkas langsung di akar ZIP; beberapa produk =
     * satu folder per produk. Mengembalikan path berkas sementara, atau null bila tidak ada foto.
     *
     * @param  Collection<int, Product>  $products
     */
    public static function photosZip(Collection $products): ?string
    {
        $zipPath = self::temporaryPath('zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $added = 0;

        foreach ($products as $product) {
            $folder = $products->count() > 1 ? $product->slug.'/' : '';

            foreach (ProductPhotos::paths($product) as $index => $path) {
                $jpeg = self::marketplacePhoto($path);
                if ($jpeg === null) {
                    continue;
                }

                $zip->addFromString($folder.$product->slug.'-'.($index + 1).'.jpg', $jpeg);
                $added++;
            }
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);

            return null;
        }

        return $zipPath;
    }

    /**
     * Foto dari disk publik sebagai JPG persegi 1024×1024: seluruh foto masuk (tidak dipotong) dan sisa ruang
     * diberi latar putih, termasuk bagian transparan PNG/WebP. Kualitas diturunkan bertahap bila lebih dari 2 MB.
     * Null untuk berkas yang tidak ada atau bukan gambar raster (mis. SVG).
     */
    public static function marketplacePhoto(string $path): ?string
    {
        $disk = Storage::disk(Media::DISK);
        if (! $disk->exists($path)) {
            return null;
        }

        // GD memuat semua piksel ke memori (±5 byte/piksel): foto besar butuh batas memori lebih tinggi dari bawaan
        $dimensions = @getimagesize($disk->path($path));
        if (! $dimensions || ! self::ensureMemoryFor($dimensions[0] * $dimensions[1])) {
            return null;
        }

        $source = @imagecreatefromstring((string) $disk->get($path));
        if (! $source instanceof GdImage) {
            return null;
        }

        $size = self::PHOTO_SIZE;
        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min($size / $width, $size / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        imagecopyresampled($canvas, $source, intdiv($size - $targetWidth, 2), intdiv($size - $targetHeight, 2), 0, 0, $targetWidth, $targetHeight, $width, $height);
        unset($source); // lepas memori foto asli sebelum foto berikutnya di ZIP diproses
        imageresolution($canvas, 72, 72);

        $jpeg = '';
        foreach ([90, 82, 74, 66, 58] as $quality) {
            ob_start();
            imagejpeg($canvas, null, $quality);
            $jpeg = (string) ob_get_clean();

            if (strlen($jpeg) <= self::MAX_PHOTO_BYTES) {
                break;
            }
        }

        return $jpeg;
    }

    /**
     * Spreadsheet (XLSX) satu baris per varian (produk tanpa varian = satu baris), berisi data yang biasa
     * diminta saat mengunggah produk ke marketplace. Mengembalikan path berkas sementara.
     *
     * @param  Collection<int, Product>  $products
     */
    public static function spreadsheet(Collection $products): string
    {
        $path = self::temporaryPath('xlsx');
        $writer = new Writer;
        $writer->openToFile($path);

        $photoHeaders = array_map(fn (int $n) => "Foto {$n}", range(1, self::PHOTO_COLUMNS));
        $writer->addRow(Row::fromValues([
            'Nama produk', 'SKU induk', 'Kategori', 'Merek', 'Nama varian', 'SKU varian', 'Harga', 'Harga coret',
            'Satuan / isi', 'Ringkasan', 'Deskripsi', 'Spesifikasi', 'Ketersediaan', ...$photoHeaders,
            'Nama berkas foto (ZIP)', 'URL produk di situs', 'Shopee', 'Tokopedia', 'Lazada', 'TikTok Shop', 'Terbit',
        ], (new Style)->setFontBold()));

        $wrap = (new Style)->setShouldWrapText();

        foreach ($products as $product) {
            $photos = ProductPhotos::paths($product);
            $photoUrls = array_map(fn (string $p) => Storage::disk(Media::DISK)->url($p), array_slice($photos, 0, self::PHOTO_COLUMNS));
            $photoFiles = implode(', ', array_map(fn (int $i) => $product->slug.'-'.($i + 1).'.jpg', array_keys($photos)));
            $links = $product->links->keyBy(fn ($link) => $link->channel?->slug);
            $variants = $product->variants->where('is_active', true)->sortBy('sort_order')->values();

            foreach ($variants->isEmpty() ? [null] : $variants->all() as $variant) {
                /** @var ProductVariant|null $variant */
                $writer->addRow(Row::fromValues([
                    $product->name,
                    $product->sku,
                    $product->category?->name,
                    $product->brand?->name,
                    $variant?->name,
                    $variant?->sku,
                    $variant ? $variant->effectivePrice() : $product->price_min,
                    $variant ? $variant->effectiveCompareAtPrice() : null,
                    $product->unit,
                    $product->summary,
                    self::plainText($product->description),
                    collect((array) $product->specifications)->map(fn ($value, $key) => "{$key}: {$value}")->implode("\n"),
                    $product->availability,
                    ...array_pad($photoUrls, self::PHOTO_COLUMNS, null),
                    $photoFiles,
                    $product->url(),
                    $links['shopee']->url ?? null,
                    $links['tokopedia']->url ?? null,
                    $links['lazada']->url ?? null,
                    $links['tiktok-shop']->url ?? null,
                    $product->is_published ? 'Ya' : 'Tidak',
                ], $wrap));
            }
        }

        $writer->close();

        return $path;
    }

    /** Deskripsi HTML dari editor menjadi teks polos dengan baris baru, siap ditempel ke kolom deskripsi marketplace. */
    public static function plainText(?string $html): string
    {
        $text = preg_replace(['#<br\s*/?>#i', '#</(p|div|h[1-6]|li|tr)>#i', '#<li[^>]*>#i'], ["\n", "\n", '• '], (string) $html);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text)));
    }

    /**
     * Naikkan batas memori sementara bila foto butuh lebih dari batas saat ini. Batas 1 GB: foto yang lebih
     * besar dari itu (±150 megapiksel) dilewati agar satu foto tidak menggagalkan seluruh unduhan.
     */
    private static function ensureMemoryFor(int $pixels): bool
    {
        $needed = memory_get_usage() + ($pixels * 5) + (self::PHOTO_SIZE * self::PHOTO_SIZE * 5) + 32 * 1024 * 1024;
        $limit = self::bytes((string) ini_get('memory_limit'));

        if ($limit < 0 || $needed <= $limit) {
            return true;
        }

        return $needed <= 1024 * 1024 * 1024 && ini_set('memory_limit', (string) (int) ceil($needed / 1048576).'M') !== false;
    }

    private static function bytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private static function temporaryPath(string $extension): string
    {
        $directory = storage_path('app/tmp');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory.'/marketplace-'.Str::random(16).'.'.$extension;
    }
}
