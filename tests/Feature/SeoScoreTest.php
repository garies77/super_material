<?php

use App\Models\Article;
use App\Models\Product;
use App\Models\User;
use App\Support\SeoScore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('scores product and article SEO completeness from 0 to 100 (PRD 9.10.5)', function () {
    $product = Product::published()->first();
    $result = SeoScore::forProduct($product);

    expect($result['checks'])->toHaveCount(8)
        ->and($result['score'])->toBeBetween(0, 100);

    $product->update(['availability' => null]);
    expect(SeoScore::forProduct($product->fresh())['checks']['Status ketersediaan terisi'])->toBeFalse();

    $product->update(['availability' => 'InStock']);
    expect(SeoScore::forProduct($product->fresh())['checks']['Status ketersediaan terisi'])->toBeTrue();

    expect(SeoScore::forArticle(Article::published()->first())['checks'])->toHaveCount(8);
});

it('flags products with expired prices for review and clears the flag on renewal (PRD 9.10.2)', function () {
    $product = Product::published()->where('price_display', '!=', 'hidden')->first();
    $product->update(['price_valid_until' => now()->subDay()]);

    $this->artisan('seo:flag-expired-prices')->assertSuccessful();
    expect($product->fresh()->price_review_needed)->toBeTrue();

    $product->fresh()->update(['price_valid_until' => now()->addMonth()]);
    expect($product->fresh()->price_review_needed)->toBeFalse();
});

it('schedules the daily expired-price check', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('seo:flag-expired-prices');
});

it('renders the SEO score column in the admin product and article tables', function () {
    $this->actingAs(User::first());

    $this->get('/admin/products')->assertOk()->assertSee('Skor SEO');
    $this->get('/admin/articles')->assertOk()->assertSee('Skor SEO');
});
