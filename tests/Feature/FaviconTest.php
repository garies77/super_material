<?php

use App\Models\Setting;
use App\Support\Favicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('public'));

/** PNG uji: persegi panjang merah (lebar > tinggi) agar proporsi bisa diperiksa. */
function pngLogo(int $width = 400, int $height = 100): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 220, 30, 30));
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

function useLogo(string $path, string $contents): void
{
    Storage::disk('public')->put($path, $contents);
    Setting::put('site_logo', $path);
    Once::flush();
}

it('falls back to the bundled favicon when no logo is uploaded', function () {
    expect(Favicon::resolve())->toBe(['url' => asset('favicon.svg'), 'type' => 'image/svg+xml']);
});

it('turns a raster logo into a square 192 px PNG favicon without stretching it', function () {
    useLogo('logos/brand.png', pngLogo());

    $resolved = Favicon::resolve();
    $path = Storage::disk('public')->path(str_replace(Storage::disk('public')->url(''), '', $resolved['url']));
    $favicon = imagecreatefrompng($path);

    expect($resolved['type'])->toBe('image/png')
        ->and($resolved['url'])->toContain('/favicons/')
        ->and([imagesx($favicon), imagesy($favicon)])->toBe([192, 192])
        // Logo 4:1 di tengah: tepi atas transparan, baris tengah berwarna merah
        ->and(imagecolorsforindex($favicon, imagecolorat($favicon, 96, 5))['alpha'])->toBe(127)
        ->and(imagecolorsforindex($favicon, imagecolorat($favicon, 96, 96)))->toMatchArray(['red' => 220, 'alpha' => 0]);
});

it('extracts the bitmap inside a Figma-style SVG logo instead of serving the heavy SVG', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><image href="data:image/png;base64,'
        .base64_encode(pngLogo(128, 128)).'" width="128" height="128"/></svg>';
    useLogo('logos/figma.svg', $svg);

    expect(Favicon::type())->toBe('image/png')->and(Favicon::url())->toContain('/favicons/');
});

it('uses a pure vector SVG logo directly', function () {
    useLogo('logos/vector.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');

    expect(Favicon::resolve())->toBe(['url' => Storage::disk('public')->url('logos/vector.svg'), 'type' => 'image/svg+xml']);
});

it('follows a newly uploaded logo on the website and the admin panel', function () {
    useLogo('logos/lama.png', pngLogo());
    $old = Favicon::url();

    useLogo('logos/baru.png', pngLogo(100, 100));
    $new = Favicon::url();

    expect($new)->not->toBe($old);

    $this->get('/')->assertOk()
        ->assertSee('<link rel="icon" href="'.$new.'" type="image/png">', false)
        ->assertDontSee('favicon.svg');
    $this->get('/admin/login')->assertOk()->assertSee($new, false);
});
