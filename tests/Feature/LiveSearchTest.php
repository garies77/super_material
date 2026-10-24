<?php

use App\Models\Article;
use App\Models\Brand;
use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

function liveSearch(string $q, ?string $type = null)
{
    return test()->getJson(route('search.live', array_filter(['q' => $q, 'type' => $type])))->assertOk();
}

it('finds products by name, ranking names that start with the query first', function () {
    Product::published()->first()->update(['name' => 'Zebra Semen Ajaib']);
    Product::published()->skip(1)->first()->update(['name' => 'Semen Ajaib Super']);

    $titles = collect(liveSearch('semen ajaib', 'produk')->json('products'))->pluck('title');

    expect($titles->first())->toBe('Semen Ajaib Super')->and($titles)->toContain('Zebra Semen Ajaib');
});

it('finds products by brand and SKU', function () {
    $product = Product::published()->whereNotNull('brand_id')->first();
    Brand::whereKey($product->brand_id)->update(['name' => 'MerekUnikXyz']);
    $other = Product::published()->whereKeyNot($product->id)->first();
    $other->update(['sku' => 'SKU-ZZ-991']);

    expect(collect(liveSearch('merekunik', 'produk')->json('products'))->pluck('url'))->toContain($product->url())
        ->and(collect(liveSearch('zz-991', 'produk')->json('products'))->pluck('url'))->toContain($other->url());
});

it('keeps product and article contexts separate', function () {
    Product::published()->first()->update(['name' => 'Kata Kontekstual Produk']);
    Article::published()->first()->update(['title' => 'Kata Kontekstual Artikel']);

    $produk = liveSearch('kontekstual', 'produk')->json();
    $artikel = liveSearch('kontekstual', 'artikel')->json();

    expect($produk['articles'])->toBe([])->and($produk['products'])->toHaveCount(1)
        ->and($artikel['products'])->toBe([])->and($artikel['articles'])->toHaveCount(1)
        ->and($artikel['articles'][0])->toHaveKeys(['title', 'url', 'image', 'meta', 'excerpt']);
});

it('never returns unpublished products or draft and scheduled articles', function () {
    Product::published()->first()->update(['name' => 'Qzxkata Draf Produk', 'is_published' => false]);
    Article::published()->first()->update(['title' => 'Qzxkata Draf Artikel', 'published_at' => null]);
    Article::published()->first()->update(['title' => 'Qzxkata Terjadwal Artikel', 'published_at' => now()->addDay()]);

    $json = liveSearch('qzxkata')->json();

    expect($json['products'])->toBe([])->and($json['articles'])->toBe([]);
});

it('skips the database for empty or one-letter queries and caps results', function () {
    DB::enableQueryLog();
    $json = liveSearch(' s ')->json();
    $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'products') || str_contains($q, 'articles'));

    expect($json['products'])->toBe([])->and($queries)->toBeEmpty()
        ->and(count(liveSearch('a', 'produk')->json('products')))->toBe(0)
        ->and(count(liveSearch('an', 'produk')->json('products')))->toBeLessThanOrEqual(6);
});

it('treats % and _ as literal characters', function () {
    Product::published()->first()->update(['name' => 'Diskon 100% Spesial']);

    expect(collect(liveSearch('100%', 'produk')->json('products'))->pluck('title'))->toContain('Diskon 100% Spesial')
        ->and(liveSearch('%%', 'produk')->json('products'))->toBe([])
        ->and(liveSearch('__', 'produk')->json('products'))->toBe([]);
});

it('ignores unknown types and non-string queries safely', function () {
    $this->getJson('/cari/live?q[]=semen&type=admin')->assertOk()->assertJson(['products' => [], 'articles' => []]);
    $this->getJson('/cari/live?q=semen&type=users')->assertOk()->assertJsonStructure(['products', 'articles']);
});

it('passes the page context to the header search overlay and the full results page', function () {
    $this->get('/produk')->assertSee('data-search-type="produk"', false)->assertSee('name="type" value="produk"', false);
    $this->get('/artikel')->assertSee('data-search-type="artikel"', false);
    $this->get('/')->assertSee('data-search-type=""', false);

    Product::published()->first()->update(['name' => 'Kata Halaman Penuh']);
    Article::published()->first()->update(['title' => 'Kata Halaman Penuh Artikel']);

    $this->get('/cari?q=halaman+penuh&type=produk')->assertOk()->assertSee('Kata Halaman Penuh')->assertDontSee('Kata Halaman Penuh Artikel');
    $this->get('/cari?q=halaman+penuh')->assertOk()->assertSee('Kata Halaman Penuh Artikel');
});
