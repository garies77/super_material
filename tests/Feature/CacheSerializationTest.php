<?php

use App\Models\Product;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

// Seperti production: cache benar-benar diserialisasi (bukan array di memori), dengan
// cache.serializable_classes = false yang menolak objek saat dibaca ulang.
beforeEach(function () {
    $this->cacheDir = sys_get_temp_dir().'/sm-cache-'.uniqid();
    config(['cache.default' => 'file', 'cache.stores.file.path' => $this->cacheDir]);
    Cache::forgetDriver('file');
});

afterEach(fn () => File::deleteDirectory($this->cacheDir));

it('keeps serving the sitemap once it is read back from the cache', function () {
    Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik', 'is_published' => true]);

    foreach ([1, 2] as $attempt) {
        $this->get('/sitemap.xml')->assertOk();
        $this->get('/sitemap-products.xml')->assertOk()->assertSee('/produk/semen-gresik', false);
    }
});

it('keeps redirecting once the redirect map is read back from the cache', function () {
    Redirect::create(['from_path' => '/produk/semen-lama', 'to_path' => '/produk/semen-gresik', 'code' => 301]);

    foreach ([1, 2] as $attempt) {
        $this->get('/produk/semen-lama')->assertStatus(301)->assertRedirect('/produk/semen-gresik');
    }
});
