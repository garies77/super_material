<?php

use App\Models\Article;
use App\Models\Brand;
use App\Models\ClickEvent;
use App\Models\Product;
use App\Models\ProductLink;
use App\Models\Redirect;
use App\Models\SalesChannel;
use App\Models\Setting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('renders public pages', function () {
    $product = Product::first();

    $this->get('/')->assertOk()->assertSee('Supermaterial')->assertSee('application/ld+json', false);
    $this->get('/kategori/semen-perekat')->assertOk();
    $this->get('/kategori/mortar')->assertOk();
    $this->get('/produk/'.$product->slug)->assertOk()->assertSee('FAQPage', false)->assertSee('"@type":"Product"', false);
    $this->get('/artikel')->assertOk();
    $this->get('/artikel/cara-memilih-semen-untuk-rumah')->assertOk()->assertSee('Daftar isi');
    $this->get('/tentang')->assertOk();
    $this->get('/cari?q=semen')->assertOk()->assertSee('noindex', false);
    $this->get('/tidak-ada')->assertNotFound();
});

it('renders the catalog with sidebar filters', function () {
    $this->get('/produk')->assertOk()
        ->assertSee('Katalog Produk')->assertSee('Rentang harga')->assertSee('Semua produk')
        ->assertSee('produk ditemukan');
});

it('filters the catalog in the database and noindexes filtered pages', function () {
    $mulia = Brand::where('slug', 'mulia')->first();

    $this->get('/produk?brand[]='.$mulia->id)->assertOk()
        ->assertSee('2</span> produk ditemukan', false)
        ->assertSee('noindex', false)
        ->assertSee('Hapus semua');

    $this->get('/produk?min=100000&max=200000&sort=termurah')->assertOk()
        ->assertSee('Harga terendah');

    $this->get('/kategori/semen-perekat')->assertOk()->assertSee('3</span> produk ditemukan', false);
});

it('keeps search and article links in the header and no category links', function () {
    $html = $this->get('/')->assertOk()->getContent();
    $header = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));

    expect($header)->toContain('data-search-open')->toContain('/artikel')->toContain('/produk')
        ->not->toContain('/kategori/');
});

it('serves sitemap and robots', function () {
    $this->get('/sitemap.xml')->assertOk()->assertSee('sitemap-products.xml');
    $this->get('/sitemap-products.xml')->assertOk()->assertSee('/produk/', false);
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /go/');
});

it('logs a click and redirects through /go', function () {
    $product = Product::first();

    $this->get("/go/{$product->slug}/shopee")->assertRedirect()->assertStatus(302);

    expect(ClickEvent::count())->toBe(1);
    expect(ClickEvent::first()->product_id)->toBe($product->id);
});

it('builds a whatsapp redirect with prefilled message', function () {
    $product = Product::first();
    $response = $this->get("/go/{$product->slug}/whatsapp");

    $response->assertStatus(302);
    expect($response->headers->get('Location'))->toStartWith('https://wa.me/6281234567890?text=');
});

it('refuses to redirect to non-allowed hosts (no open redirect)', function () {
    $product = Product::first();
    $channel = SalesChannel::where('slug', 'shopee')->first();
    ProductLink::where('product_id', $product->id)->where('sales_channel_id', $channel->id)->update(['url' => 'https://evil.example.com/phish']);

    $response = $this->get("/go/{$product->slug}/shopee");

    $response->assertRedirect($product->url());
});

it('applies stored redirects', function () {
    Redirect::create(['from_path' => '/produk/lama', 'to_path' => '/produk/baru', 'code' => 301]);

    $this->get('/produk/lama')->assertStatus(301)->assertRedirect(url('/produk/baru'));
});

it('hides unpublished products', function () {
    $product = Product::first();
    $product->update(['is_published' => false]);

    $this->get('/produk/'.$product->slug)->assertNotFound();
});

it('renders a modern article: author link, WIB date, reading time, toc, no dateline or print initials', function () {
    $article = Article::where('slug', 'cara-memilih-semen-untuk-rumah')->firstOrFail();
    $author = $article->author;

    $response = $this->get('/artikel/'.$article->slug);

    $response->assertOk()
        ->assertSee('Cara Memilih Semen yang Tepat untuk Bangunan Tahan Gempa')
        ->assertSee('href="'.route('author.show', $author->slug).'"', false)
        ->assertSee('menit baca')
        ->assertSee('Daftar isi')
        ->assertSee('WIB')
        ->assertSee('Bagikan:')
        ->assertSee('Terpopuler')
        // Kolom lama (dateline, inisial) masih tersimpan di database tetapi tidak lagi ditampilkan
        ->assertDontSee('Medan, Supermaterial --')
        ->assertDontSee('(gar/ed)')
        ->assertSee('"@type":"Person"', false)
        ->assertSee('Profil Penulis &amp; Artikel Lainnya', false)
        ->assertSee('Topik Terkait')
        ->assertSee('Produk yang Dibahas dalam Artikel')
        ->assertSee('BlogPosting', false);

    // Verify views increment
    expect($article->fresh()->views_count)->toBeGreaterThan(0);
});

it('renders article category and tag listing feeds', function () {
    $this->get('/artikel/kategori/semen-perekat')->assertOk()
        ->assertSee('Artikel Semen &amp; Perekat', false)
        ->assertSee('Terpopuler');

    $this->get('/artikel/tag/sni')->assertOk()
        ->assertSee('Topik: #Standar SNI')
        ->assertSee('Terpopuler');
});

it('renders hero campaign bundling banner on home page when active', function () {
    Setting::put('hero_banner_active', '1');
    Setting::put('hero_banner_style', 'card');
    Setting::put('hero_banner_image', 'banners/bundling-renovasi-oktober.jpg');
    Setting::put('hero_banner_badge', 'Promo Bundling Spesial');
    Setting::put('hero_banner_title', 'Paket Bundling Renovasi Hemat s.d. 30%');

    $this->get('/')
        ->assertOk()
        ->assertSee('Promo Bundling Spesial')
        ->assertSee('Paket Bundling Renovasi Hemat s.d. 30%')
        ->assertSee('storage/banners/bundling-renovasi-oktober.jpg');
});

it('hides hero campaign banner when inactive', function () {
    Setting::put('hero_banner_active', '0');

    $this->get('/')
        ->assertOk()
        ->assertDontSee('storage/banners/bundling-renovasi-oktober.jpg');
});
