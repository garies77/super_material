<?php

use App\Models\Article;
use App\Models\Product;
use App\Models\Redirect;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('emits robots directives and og:type mapping (PRD 9.10.3, 9.11.4)', function () {
    $product = Product::published()->first();

    $this->get($product->url())->assertOk()
        ->assertSee('content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1"', false)
        ->assertSee('<meta property="og:type" content="website">', false)
        ->assertDontSee('content="product"', false);

    $product->update(['noindex' => true, 'nofollow' => true]);
    $this->get($product->url())->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('renders Organization and WebSite without SearchAction on every page (PRD 9.15.2, SEO-06)', function () {
    foreach (['/', '/produk', '/artikel'] as $path) {
        $this->get($path)->assertOk()
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertDontSee('SearchAction', false)
            ->assertDontSee('HowTo', false);
    }
});

it('uses the admin-selected article schema type (PRD 9.13.1)', function () {
    $article = Article::published()->first();
    $article->update(['schema_type' => 'NewsArticle']);

    $this->get($article->url())->assertSee('"@type":"NewsArticle"', false);
});

it('keeps dateModified stable when only the view counter changes (PRD 9.11.6)', function () {
    $article = Article::published()->first();
    $before = $article->modifiedAt()->toIso8601String();

    $this->travel(3)->days();
    $article->increment('views_count');
    $article->update(['is_featured' => ! $article->is_featured]);
    expect($article->fresh()->modifiedAt()->toIso8601String())->toBe($before);

    $article->update(['content' => $article->content.'<p>Pembaruan data harga 2026.</p>']);
    expect($article->fresh()->modifiedAt()->gt($before))->toBeTrue();
});

it('records 301 redirects on slug change without chains or loops (PRD 9.1, SEO-07)', function () {
    $article = Article::published()->first();
    $a = $article->slug;

    $article->update(['slug' => 'slug-b']);
    $article->update(['slug' => 'slug-c']);

    expect(Redirect::where('from_path', "/artikel/{$a}")->value('to_path'))->toBe('/artikel/slug-c')
        ->and(Redirect::where('from_path', '/artikel/slug-b')->value('to_path'))->toBe('/artikel/slug-c');
    $this->get("/artikel/{$a}")->assertStatus(301)->assertRedirect(url('/artikel/slug-c'));

    // Kembali ke slug awal: tidak boleh ada redirect yang berasal dari URL hidup
    $article->update(['slug' => $a]);
    expect(Redirect::where('from_path', "/artikel/{$a}")->exists())->toBeFalse()
        ->and(Redirect::whereColumn('from_path', 'to_path')->exists())->toBeFalse();
    $this->get("/artikel/{$a}")->assertOk();
    $this->get('/artikel/slug-c')->assertStatus(301)->assertRedirect(url("/artikel/{$a}"));
});

it('splits sitemaps by type including images and excludes noindex pages (PRD 9.11.6)', function () {
    $index = $this->get('/sitemap.xml')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
    foreach (['products', 'categories', 'articles', 'pages', 'authors', 'images'] as $type) {
        $index->assertSee("sitemap-{$type}.xml", false);
    }

    $product = Product::published()->first();
    $this->get('/sitemap-products.xml')->assertOk()->assertSee($product->url(), false);
    $this->get('/sitemap-images.xml')->assertOk()->assertSee('<image:loc>', false);

    $product->update(['noindex' => true]);
    $this->get('/sitemap-products.xml')->assertDontSee('<loc>'.$product->url().'</loc>', false);

    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.url('/sitemap.xml'))->assertDontSee("Disallow: /\n");
});

it('marks internal search as noindex via header and meta (PRD 9.11.3)', function () {
    $this->get('/cari?q=semen')->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertSee('noindex', false);
});

it('gives each paginated list page a self canonical and unique title (PRD 9.11.5, SEO-10)', function () {
    foreach (range(1, 12) as $i) {
        $clone = Article::published()->first()->replicate();
        $clone->slug = "artikel-paginasi-{$i}";
        $clone->is_featured = false;
        $clone->save();
    }

    $this->get('/artikel?page=2')->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/artikel').'?page=2">', false)
        ->assertSee('— Halaman 2', false);
});

it('returns 503 with Retry-After in maintenance while robots.txt stays 200 (SEO-08)', function () {
    $this->artisan('down');

    try {
        $this->get(Product::published()->first()->url())->assertStatus(503)->assertHeader('Retry-After');
        $this->get('/robots.txt')->assertOk()->assertDontSee("Disallow: /\n");
    } finally {
        $this->artisan('up');
    }
});
