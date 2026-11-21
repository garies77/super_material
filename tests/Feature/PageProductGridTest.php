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
        ->assertSee('text-xl sm:text-2xl">Peralatan Proyek di Shopee</h2>', false)
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

it('creates Mbiz and e-Katalog LKPP channels with draft procurement landing pages', function () {
    (require database_path('migrations/2026_11_15_152535_create_marketplace_landing_pages.php'))->up();
    (require database_path('migrations/2026_11_20_105416_create_procurement_landing_pages.php'))->up();

    $mbiz = SalesChannel::where('slug', 'mbiz')->sole();
    $lkpp = SalesChannel::where('slug', 'lkpp')->sole();
    expect($mbiz->allowed_hosts)->toContain('mbizmarket.co.id')
        ->and($lkpp->name)->toBe('e-Katalog LKPP')
        ->and($lkpp->allowed_hosts)->toContain('katalog.inaproc.id');

    gridProduct('Meteran Laser', [$mbiz]);
    gridProduct('Waterpass Aluminium', [$lkpp]);
    gridProduct('Bor Listrik', [$this->shopee]);

    $this->get('/jual-peralatan-proyek-di-mbiz')->assertNotFound();
    Page::whereIn('slug', ['jual-peralatan-proyek-di-mbiz', 'jual-peralatan-proyek-di-e-katalog-lkpp'])->get()->each->publish();

    $this->get('/jual-peralatan-proyek-di-mbiz')->assertOk()
        ->assertSee('Meteran Laser')->assertSee('Beli di Mbiz')
        ->assertDontSee('Waterpass Aluminium')->assertDontSee('Bor Listrik')
        ->assertSee('Pengadaan di Mbiz: yang perlu Anda tahu')
        ->assertSee('href="/jual-peralatan-proyek-di-e-katalog-lkpp"', false)
        ->assertSee('href="/jual-peralatan-proyek-di-shopee"', false);

    $this->get('/jual-peralatan-proyek-di-e-katalog-lkpp')->assertOk()
        ->assertSee('Waterpass Aluminium')->assertSee('Beli di e-Katalog LKPP')
        ->assertDontSee('Meteran Laser')
        ->assertSee('e-purchasing')->assertSee('TKDN');
});

it('keeps existing procurement channels and pages when the migration runs', function () {
    $mbiz = SalesChannel::create(['name' => 'Mbizmarket', 'slug' => 'mbiz', 'type' => 'marketplace', 'is_active' => true]);
    Page::create(['title' => 'Sudah ada', 'slug' => 'jual-peralatan-proyek-di-mbiz', 'is_published' => true]);

    (require database_path('migrations/2026_11_20_105416_create_procurement_landing_pages.php'))->up();

    expect(SalesChannel::where('slug', 'mbiz')->sole()->name)->toBe('Mbizmarket')
        ->and(Page::where('slug', 'jual-peralatan-proyek-di-mbiz')->sole()->title)->toBe('Sudah ada')
        ->and(Page::where('slug', 'jual-peralatan-proyek-di-e-katalog-lkpp')->sole()->is_published)->toBeFalse();
});

it('renders a numbered price table with one row per variant and a tracked buy link per variant', function () {
    $semen = Category::create(['name' => 'Semen', 'slug' => 'semen', 'is_active' => true]);
    $bor = gridProduct('Bor Listrik', [$this->shopee], ['category_id' => $this->alat->id, 'price_min' => 450000]);
    $mortar = gridProduct('Mortar Instan', [$this->shopee], ['category_id' => $semen->id, 'unit' => 'sak']);
    $mortar->variants()->create(['name' => 'Sak 40 kg', 'slug' => 'sak-40-kg', 'price' => 62000, 'compare_at_price' => 70000, 'is_active' => true, 'sort_order' => 1]);
    $mortar->variants()->create(['name' => 'Sak 25 kg', 'slug' => 'sak-25-kg', 'price' => 45000, 'is_active' => true, 'sort_order' => 0]);
    $mortar->variants()->create(['name' => 'Ukuran lama', 'slug' => 'lama', 'price' => 1000, 'is_active' => false]);
    gridProduct('Gerinda Tangan', [$this->shopee], ['category_id' => $this->alat->id, 'price_min' => 380000]);
    gridProduct('Kunci Inggris', [$this->shopee], ['category_id' => $this->alat->id, 'price_min' => 75000]);
    gridProduct('Semen Putih', [$this->shopee], ['category_id' => $semen->id, 'price_min' => 30000]);
    gridProduct('Palu Godam', [$this->tokopedia], ['category_id' => $this->alat->id]);

    Page::create(['title' => 'Daftar harga', 'slug' => 'daftar-harga-shopee', 'is_published' => true, 'blocks' => [
        ['type' => 'price_table', 'data' => ['channel_id' => $this->shopee->id, 'group_by' => 'category']],
    ]]);

    $this->get('/daftar-harga-shopee')->assertOk()
        ->assertSee('Daftar harga di Shopee')
        ->assertSeeInOrder(['1. Alat Proyek', '2. Semen', '1. Harga Alat Proyek', 'Bor Listrik', 'Rp450.000', '2. Harga Semen', 'Sak 25 kg', 'Rp45.000', '/ sak', 'Sak 40 kg', 'Rp70.000', 'Rp62.000', '/ sak'])
        ->assertSee('/go/mortar-instan/shopee?utm_source=landing-page&amp;utm_medium=page&amp;utm_campaign=daftar-harga-shopee&amp;variant=sak-40-kg', false)
        ->assertSee('href="#harga-semen"', false)
        ->assertDontSee('Ukuran lama')->assertDontSee('Palu Godam');
});

it('merges thin category groups into one table with category labels, as in a small real catalog', function () {
    $cetok = Category::create(['name' => 'Cetok', 'slug' => 'cetok', 'is_active' => true]);
    $dynabolt = Category::create(['name' => 'Dynabolt', 'slug' => 'dynabolt', 'is_active' => true]);
    gridProduct('Dynabolt Baut Tanam Beton M8 M10 M12', [$this->shopee], ['category_id' => $dynabolt->id, 'price_min' => 206200, 'unit' => '1 kotak']);
    gridProduct('Cetok Stainless Gagang Kayu Jati', [$this->shopee], ['category_id' => $cetok->id, 'price_min' => 13100, 'unit' => '12 pcs']);
    gridProduct('Pahat Beton 8 Inch', [$this->shopee], ['category_id' => $this->alat->id, 'price_min' => 21000]);

    Page::create(['title' => 'Daftar harga', 'slug' => 'daftar-harga-shopee', 'is_published' => true, 'blocks' => [
        ['type' => 'price_table', 'data' => ['channel_id' => $this->shopee->id, 'group_by' => 'category']],
    ]]);

    $this->get('/daftar-harga-shopee')->assertOk()
        ->assertSeeInOrder(['Alat Proyek', 'Pahat Beton 8 Inch', 'Rp21.000', 'Cetok', 'Cetok Stainless Gagang Kayu Jati', 'Rp13.100', '/ 12 pcs', 'Dynabolt', 'Dynabolt Baut Tanam Beton M8 M10 M12', 'Rp206.200', '/ 1 kotak'])
        ->assertDontSee('1. Harga')->assertDontSee('aria-label="Isi daftar harga"', false);
});

it('adds a price table to landing page drafts without changing the published version', function () {
    gridProduct('Bor Listrik', [$this->shopee], ['price_min' => 450000]);
    (require database_path('migrations/2026_11_15_152535_create_marketplace_landing_pages.php'))->up();
    $page = Page::where('slug', 'jual-peralatan-proyek-di-shopee')->sole();
    $page->publish();

    $migration = require database_path('migrations/2026_11_21_021637_add_price_table_to_landing_pages.php');
    $migration->up();
    $migration->up();

    $page->refresh();
    $types = array_column($page->draft_blocks, 'type');
    expect(array_count_values($types)['price_table'])->toBe(1)
        ->and($types[array_search('products', $types, true) + 1])->toBe('price_table')
        ->and(array_column($page->blocks, 'type'))->not->toContain('price_table');

    $this->get('/jual-peralatan-proyek-di-shopee')->assertOk()
        ->assertSee('Diperbarui')->assertDontSee('Daftar Harga Peralatan &amp; Bahan Proyek di Shopee', false);

    $page->publish();

    $this->get('/jual-peralatan-proyek-di-shopee')->assertOk()
        ->assertSee('Daftar Harga Peralatan &amp; Bahan Proyek di Shopee', false)
        ->assertSee('Rp450.000');
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

it('skips products whose link on the marketplace is switched off', function () {
    gridProduct('Bor Aktif', [$this->shopee]);
    gridProduct('Bor Nonaktif', [$this->shopee])->links()->update(['is_active' => false]);
    gridPage(['channel_id' => $this->shopee->id, 'source' => 'channel', 'limit' => 12]);

    $this->get('/peralatan-proyek-shopee')->assertOk()->assertSee('Bor Aktif')->assertDontSee('Bor Nonaktif');
});
