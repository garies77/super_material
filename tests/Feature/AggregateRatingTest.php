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

    return [trim($value[1] ?? ''), trim($count[1] ?? '')];
}

it('menyisipkan aggregateRating dari database ke JSON-LD produk bervarian', function () {
    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->first();
    $product->update(['rating_value' => 4.7, 'rating_count' => 1240]);

    $html = $this->get($product->url())->assertOk()->getContent();
    $ld = productLd($html);

    expect($ld['@type'])->toBe('ProductGroup')
        ->and($ld['aggregateRating'])->toBe([
            '@type' => 'AggregateRating',
            'ratingValue' => '4.7',
            'ratingCount' => 1240,
        ]);
});

it('menyisipkan aggregateRating ke JSON-LD produk tanpa varian', function () {
    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->first();
    $product->variants()->delete();
    $product->update(['rating_value' => 5, 'rating_count' => 12]);

    $ld = productLd($this->get($product->url())->assertOk()->getContent());

    expect($ld['@type'])->toBe('Product')
        ->and($ld['aggregateRating']['ratingValue'])->toBe('5.0')
        ->and($ld['aggregateRating']['ratingCount'])->toBe(12);
});

it('menyamakan ratingValue dan ratingCount JSON-LD dengan tampilan', function (float $value, int $count) {
    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->first();
    $product->update(['rating_value' => $value, 'rating_count' => $count]);

    $html = $this->get($product->url())->assertOk()->getContent();
    $rating = productLd($html)['aggregateRating'];

    expect(visibleRating($html))->toBe([$rating['ratingValue'], (string) $rating['ratingCount']]);
})->with([
    'ribuan' => [4.85, 1240],
    'ratusan' => [4.0, 430],
    'satuan' => [3.2, 1],
]);

it('tidak me-render aggregateRating bila rating kosong atau nol', function (?float $value, ?int $count) {
    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->first();
    $product->update(['rating_value' => $value, 'rating_count' => $count]);

    $html = $this->get($product->url())->assertOk()->getContent();

    expect(productLd($html))->not->toHaveKey('aggregateRating')
        ->and($html)->not->toContain('AggregateRating')
        ->and($html)->not->toContain('data-rating-value');
})->with([
    'jumlah nol' => [4.5, 0],
    'jumlah kosong' => [4.5, null],
    'nilai kosong' => [null, 25],
    'nilai di luar skala' => [0.0, 25],
]);

it('memakai rating milik produk itu sendiri', function () {
    [$a, $b] = Product::whereHas('variants')->take(2)->get();
    $a->update(['rating_value' => 4.2, 'rating_count' => 77]);
    $b->update(['rating_value' => 3.1, 'rating_count' => 9]);

    $ldA = productLd($this->get($a->url())->getContent());
    $ldB = productLd($this->get($b->url())->getContent());

    expect($ldA['aggregateRating']['ratingValue'])->toBe('4.2')
        ->and($ldA['aggregateRating']['ratingCount'])->toBe(77)
        ->and($ldB['aggregateRating']['ratingValue'])->toBe('3.1')
        ->and($ldB['aggregateRating']['ratingCount'])->toBe(9);
});

it('tidak memasang AggregateRating di beranda', function () {
    $this->get('/')->assertOk()->assertDontSee('AggregateRating', false);
});
