<?php

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\SalesChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->shopee = SalesChannel::create(['name' => 'Shopee', 'slug' => 'shopee', 'type' => 'marketplace', 'color' => '#EE4D2D', 'is_active' => true]);
    $this->tokopedia = SalesChannel::create(['name' => 'Tokopedia', 'slug' => 'tokopedia', 'type' => 'marketplace', 'color' => '#03AC0E', 'is_active' => true]);
    $this->alat = Category::create(['name' => 'Alat Proyek', 'slug' => 'alat-proyek', 'is_active' => true]);
    $this->bor = Category::create(['name' => 'Bor', 'slug' => 'bor', 'parent_id' => $this->alat->id, 'is_active' => true]);
});

/** Produk terbit dengan tautan di kanal tertentu. */
function gridProduct(string $name, array $channels, array $attrs = []): Product
{
    $product = Product::create($attrs + ['name' => $name, 'slug' => Str::slug($name), 'is_published' => true]);
    foreach ($channels as $channel) {
        $product->links()->create(['sales_channel_id' => $channel->id, 'url' => 'https://'.$channel->slug.'.co.id/'.$product->slug, 'is_active' => true]);
    }

    return $product;
}

function gridPage(array $data): Page
{
    return Page::create([
        'title' => 'Jual Peralatan Proyek di Shopee', 'slug' => 'peralatan-proyek-shopee', 'is_published' => true,
        'blocks' => [['type' => 'products', 'data' => $data]],
    ]);
}

it('lists only published products sold on the chosen marketplace, with tracked buy buttons', function () {
    gridProduct('Bor Listrik', [$this->shopee]);
    gridProduct('Gerinda Tangan', [$this->shopee, $this->tokopedia]);
    gridProduct('Palu Godam', [$this->tokopedia]);
    gridProduct('Draf Rahasia', [$this->shopee], ['is_published' => false]);
    gridPage(['heading' => 'Peralatan Proyek di Shopee', 'channel_id' => $this->shopee->id, 'source' => 'channel', 'sort' => 'name', 'limit' => 12]);

    $this->get('/peralatan-proyek-shopee')->assertOk()
        ->assertSee('<h2 class="font-display text-2xl font-extrabold text-ink-950 sm:text-3xl">Peralatan Proyek di Shopee</h2>', false)
        ->assertSeeInOrder(['Bor Listrik', 'Gerinda Tangan'])
        ->assertDontSee('Palu Godam')
        ->assertDontSee('Draf Rahasia')
        ->assertSee('Beli di Shopee')
        ->assertSee(e(route('go', ['bor-listrik', 'shopee']).'?utm_source=landing-page&utm_medium=page&utm_campaign=peralatan-proyek-shopee'), false)
        ->assertSee('rel="nofollow sponsored noopener"', false)
        ->assertSee('href="'.route('product.show', 'bor-listrik').'"', false);
});

it('adds an ItemList for Google pointing at each product page', function () {
    gridProduct('Bor Listrik', [$this->shopee]);
    gridProduct('Gerinda Tangan', [$this->shopee]);
    gridPage(['heading' => 'Peralatan Proyek di Shopee', 'channel_id' => $this->shopee->id, 'source' => 'channel', 'sort' => 'name', 'limit' => 12]);

    $html = $this->get('/peralatan-proyek-shopee')->assertOk()->getContent();
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $scripts);
    $list = collect($scripts[1])->map(fn ($json) => json_decode($json, true))->firstWhere('@type', 'ItemList');

    expect($list['name'])->toBe('Peralatan Proyek di Shopee')
        ->and($list['numberOfItems'])->toBe(2)
        ->and(array_column($list['itemListElement'], 'url'))->toBe([route('product.show', 'bor-listrik'), route('product.show', 'gerinda-tangan')]);
});

it('picks products from a category including its subcategories', function () {
    gridProduct('Bor Listrik', [$this->tokopedia], ['category_id' => $this->bor->id]);
    gridProduct('Meteran', [$this->tokopedia], ['category_id' => $this->alat->id]);
    gridProduct('Cat Tembok', [$this->tokopedia]);
    gridPage(['channel_id' => $this->tokopedia->id, 'source' => 'category', 'category_id' => $this->alat->id, 'limit' => 12]);

    $this->get('/peralatan-proyek-shopee')->assertOk()
        ->assertSee('Bor Listrik')->assertSee('Meteran')->assertDontSee('Cat Tembok')
        ->assertSee('Beli di Tokopedia');
});

it('keeps the manual order and respects the limit', function () {
    $a = gridProduct('Alpha', [$this->shopee]);
    $b = gridProduct('Bravo', [$this->shopee]);
    $c = gridProduct('Charlie', [$this->shopee]);
    gridPage(['channel_id' => $this->shopee->id, 'source' => 'manual', 'product_ids' => [$c->id, $a->id, $b->id], 'limit' => 2, 'button_label' => 'Pesan sekarang']);

    $this->get('/peralatan-proyek-shopee')->assertOk()
        ->assertSeeInOrder(['Charlie', 'Alpha'])->assertDontSee('Bravo')
        ->assertSee('Pesan sekarang');
});

it('renders nothing when the marketplace is inactive', function () {
    gridProduct('Bor Listrik', [$this->shopee]);
    $this->shopee->update(['is_active' => false]);
    gridPage(['channel_id' => $this->shopee->id, 'source' => 'channel', 'limit' => 12]);

    $this->get('/peralatan-proyek-shopee')->assertOk()->assertDontSee('Bor Listrik')->assertDontSee('ItemList', false);
});

it('creates draft marketplace landing pages that work once published', function () {
    gridProduct('Bor Listrik', [$this->shopee]);
    Page::create(['title' => 'Sudah ada', 'slug' => 'jual-peralatan-proyek-di-tokopedia', 'is_published' => true]);

    (require database_path('migrations/2026_11_15_152535_create_marketplace_landing_pages.php'))->up();

    $shopeePage = Page::where('slug', 'jual-peralatan-proyek-di-shopee')->sole();
    expect($shopeePage->is_published)->toBeFalse()
        ->and(Page::where('slug', 'jual-peralatan-proyek-di-tokopedia')->sole()->title)->toBe('Sudah ada')
        ->and(Page::where('slug', 'jual-peralatan-proyek-di-lazada')->exists())->toBeFalse();

    $this->get('/jual-peralatan-proyek-di-shopee')->assertNotFound();

    $shopeePage->publish();

    $this->get('/jual-peralatan-proyek-di-shopee')->assertOk()
        ->assertSee('Supermaterial Jual Peralatan &amp; Bahan Proyek di Shopee', false)
        ->assertSee('Bor Listrik')->assertSee('Beli di Shopee')
        ->assertSee('Pertanyaan yang sering diajukan');
});

it('gives each example page the marketplace-specific notes, steps and FAQ around one automatic grid', function () {
    gridProduct('Semen Serba Guna 40 kg', [$this->shopee]);
    gridProduct('Kompresor Angin 24L', [$this->tokopedia]);

    (require database_path('migrations/2026_11_15_152535_create_marketplace_landing_pages.php'))->up();
    Page::where('slug', 'like', 'jual-peralatan-proyek-di-%')->get()->each->publish();

    $this->get('/jual-peralatan-proyek-di-shopee')->assertOk()
        ->assertSee('Peralatan &amp; Bahan Proyek Pilihan di Shopee', false)
        ->assertSee('Semen Serba Guna 40 kg')->assertDontSee('Kompresor Angin 24L')
        ->assertSee('Harga acuan')
        ->assertSee('Tombol beli membuka Shopee, tempat transaksi, pembayaran, dan pengiriman diproses.')
        ->assertSee('Belanja di Shopee: yang perlu Anda tahu')->assertSee('SPayLater')
        ->assertSee('Tips belanja kebutuhan proyek secara online')
        ->assertSee('<details', false)
        ->assertSee('href="/jual-peralatan-proyek-di-tokopedia"', false);

    $this->get('/jual-peralatan-proyek-di-tokopedia')->assertOk()
        ->assertSee('Kompresor Angin 24L')->assertDontSee('Semen Serba Guna 40 kg')
        ->assertSee('Tokopedia PLUS')->assertDontSee('SPayLater');
});

it('renders highlight chips and a collapsible FAQ whose answers stay in the HTML', function () {
    Page::create(['title' => 'Uji blok', 'slug' => 'uji-blok', 'is_published' => true, 'blocks' => [
        ['type' => 'highlights', 'data' => ['items' => [['text' => 'Harga acuan'], ['text' => 'Tanya via WhatsApp']]]],
        ['type' => 'faq', 'data' => ['heading' => 'Pertanyaan umum', 'items' => [
            ['question' => 'Apakah harga sama?', 'answer' => 'Harga final mengikuti marketplace.'],
            ['question' => 'Kosong', 'answer' => ''],
        ]]],
    ]]);

    $this->get('/uji-blok')->assertOk()
        ->assertSeeInOrder(['Harga acuan', 'Tanya via WhatsApp'])
        ->assertSee('<h2 class="font-display text-2xl font-extrabold text-ink-950">Pertanyaan umum</h2>', false)
        ->assertSee('Apakah harga sama?')->assertSee('Harga final mengikuti marketplace.')
        ->assertDontSee('>Kosong<', false);
});
