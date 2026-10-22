<?php

use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

/** Ambil semua blok JSON-LD dari HTML sebagai array. */
function jsonLdBlocks(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    return array_map(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $m[1]);
}

function productLd(string $html): array
{
    return collect(jsonLdBlocks($html))
        ->first(fn ($b) => in_array($b['@type'] ?? null, ['Product', 'ProductGroup'], true));
}

function visibleRating(string $html): array
{
    preg_match('#<span[^>]*data-rating-value>([^<]*)</span>#', $html, $value);
    preg_match('#<span[^>]*data-rating-count>([^<]*)</span>#', $html, $count);
    preg_match('#<span[^>]*data-rating-source>([^<]*)</span>#', $html, $source);

    return [trim($value[1] ?? ''), trim($count[1] ?? ''), trim($source[1] ?? '')];
}

it('menampilkan rating marketplace dengan label sumbernya', function () {
    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->first();
    $product->update(['rating_value' => 4.7, 'rating_count' => 1240, 'rating_source' => 'Shopee']);

    $html = $this->get($product->url())->assertOk()->getContent();

    expect(visibleRating($html))->toBe(['4.7', '1240', 'di Shopee']);
});

it('memakai label marketplace bila sumber rating kosong', function () {
    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->first();
    $product->update(['rating_value' => 4.7, 'rating_count' => 12, 'rating_source' => null]);

    $html = $this->get($product->url())->assertOk()->getContent();

    expect(visibleRating($html)[2])->toBe('di marketplace');
});

it('tidak memasang rating marketplace sebagai AggregateRating di JSON-LD', function (bool $withVariants) {
    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->first();
    if (! $withVariants) {
        $product->variants()->delete();
    }
    $product->update(['rating_value' => 4.9, 'rating_count' => 1240, 'rating_source' => 'Shopee']);

    $html = $this->get($product->url())->assertOk()->getContent();

    expect(productLd($html)['@type'])->toBe($withVariants ? 'ProductGroup' : 'Product')
        ->and(productLd($html))->not->toHaveKey('aggregateRating')
        ->and($html)->not->toContain('AggregateRating')
        ->and($html)->toContain('data-rating-value');
})->with([
    'produk bervarian' => [true],
    'produk tanpa varian' => [false],
]);

it('menyembunyikan rating bila datanya kosong atau tidak sah', function (?float $value, ?int $count) {
    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->first();
    $product->update(['rating_value' => $value, 'rating_count' => $count]);

    $html = $this->get($product->url())->assertOk()->getContent();

    expect($html)->not->toContain('data-rating-value')
        ->and($html)->not->toContain('AggregateRating');
})->with([
    'jumlah nol' => [4.5, 0],
    'jumlah kosong' => [4.5, null],
    'nilai kosong' => [null, 25],
    'nilai di luar skala' => [0.0, 25],
]);

it('tidak memasang AggregateRating di beranda', function () {
    $this->get('/')->assertOk()->assertDontSee('AggregateRating', false);
});
