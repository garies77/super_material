<?php

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Media;
use App\Models\Product;
use App\Models\User;
use App\Support\Catalog\MarketplaceExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(Media::DISK);
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
});

/** Gambar PNG transparan berukuran bebas di disk publik palsu. */
function transparentPng(string $path, int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledrectangle($image, (int) ($width / 4), (int) ($height / 4), (int) ($width * 3 / 4), (int) ($height * 3 / 4), imagecolorallocate($image, 200, 30, 30));
    ob_start();
    imagepng($image);
    Storage::disk(Media::DISK)->put($path, ob_get_clean());

    return $path;
}

function productWithPhotos(array $paths): Product
{
    $product = Product::create(['name' => 'Bundling Raskam & Cetok', 'slug' => 'bundling-raskam-cetok', 'is_published' => true,
        'description' => '<p>Paket <strong>hemat</strong> berisi:</p><ul><li>Raskam</li><li>Cetok</li></ul>',
        'specifications' => ['Isi' => '3 product'], 'unit' => '3 product']);
    foreach ($paths as $order => $path) {
        $product->media()->create(['type' => 'image', 'provider' => 'upload', 'path' => $path, 'sort_order' => $order]);
    }

    return $product;
}

it('turns any photo into a 1024 square jpg on white at 72 dpi under 2 MB', function () {
    $jpeg = MarketplaceExport::marketplacePhoto(transparentPng('products/wide.png', 2000, 1000));
    $info = getimagesizefromstring($jpeg);
    $image = imagecreatefromstring($jpeg);

    // JFIF APP0: byte 13 = satuan (1 = DPI), byte 14-17 = densitas X dan Y
    expect([$info[0], $info[1], $info['mime']])->toBe([1024, 1024, 'image/jpeg'])
        ->and(strlen($jpeg))->toBeLessThanOrEqual(MarketplaceExport::MAX_PHOTO_BYTES)
        ->and(unpack('Cunit/nx/ny', substr($jpeg, 13, 5)))->toBe(['unit' => 1, 'x' => 72, 'y' => 72])
        ->and(imagecolorat($image, 5, 5) & 0xFFFFFF)->toBeGreaterThan(0xF0F0F0) // pojok transparan jadi putih
        ->and(MarketplaceExport::marketplacePhoto('products/tidak-ada.png'))->toBeNull();
});

it('zips every photo of a product in gallery order as jpg files', function () {
    $product = productWithPhotos([transparentPng('products/a.png', 800, 600), transparentPng('products/b.webp', 600, 900)]);

    $zipPath = MarketplaceExport::photosZip(collect([$product->load('media')]));
    $zip = new ZipArchive;
    $zip->open($zipPath);

    expect($zip->numFiles)->toBe(2)
        ->and([$zip->getNameIndex(0), $zip->getNameIndex(1)])->toBe(['bundling-raskam-cetok-1.jpg', 'bundling-raskam-cetok-2.jpg'])
        ->and(getimagesizefromstring($zip->getFromIndex(1))[0])->toBe(1024);

    $zip->close();
    @unlink($zipPath);
});

it('writes one spreadsheet row per variant with plain text description and photo urls', function () {
    $product = productWithPhotos([transparentPng('products/a.png', 800, 600)]);
    $product->variants()->create(['name' => 'Kecil', 'slug' => 'kecil', 'sku' => 'RK-S', 'price' => 50000, 'is_active' => true, 'sort_order' => 0]);
    $product->variants()->create(['name' => 'Besar', 'slug' => 'besar', 'sku' => 'RK-L', 'price' => 75000, 'compare_at_price' => 90000, 'is_active' => true, 'sort_order' => 1]);

    $path = MarketplaceExport::spreadsheet(collect([$product->load(['variants', 'media', 'links.channel'])]));
    $reader = new Reader;
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    @unlink($path);

    $header = array_flip($rows[0]);
    expect($rows)->toHaveCount(3)
        ->and([$rows[1][$header['Nama varian']], $rows[1][$header['Harga']]])->toBe(['Kecil', 50000])
        ->and([$rows[2][$header['SKU varian']], $rows[2][$header['Harga']], $rows[2][$header['Harga coret']]])->toBe(['RK-L', 75000, 90000])
        ->and($rows[1][$header['Deskripsi']])->toBe("Paket hemat berisi:\n• Raskam\n• Cetok")
        ->and($rows[1][$header['Foto 1']])->toEndWith('/products/a.png')
        ->and($rows[1][$header['Nama berkas foto (ZIP)']])->toBe('bundling-raskam-cetok-1.jpg');
});

it('downloads photos from a table row and the excel for selected products', function () {
    $product = productWithPhotos([transparentPng('products/a.png', 800, 600)]);
    $other = Product::create(['name' => 'Semen', 'slug' => 'semen', 'is_published' => true]);

    Livewire::test(ListProducts::class)
        ->callTableAction('downloadPhotos', $product)
        ->assertFileDownloaded('bundling-raskam-cetok-foto-marketplace.zip');

    Livewire::test(ListProducts::class)
        ->callTableBulkAction('downloadSpreadsheet', [$product, $other])
        ->assertFileDownloaded();
});

it('warns instead of downloading when a product has no photos', function () {
    $product = Product::create(['name' => 'Semen', 'slug' => 'semen', 'is_published' => true]);

    Livewire::test(ListProducts::class)
        ->callTableAction('downloadPhotos', $product)
        ->assertNotified('Tidak ada foto untuk diunduh');
});
