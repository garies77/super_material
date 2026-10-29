<?php

use App\Filament\Pages\ReportCenter;
use App\Filament\Reports\Widgets\TopProductsTable;
use App\Models\Article;
use App\Models\ClickEvent;
use App\Models\CustomForm;
use App\Models\FormSubmission;
use App\Models\PageViewStat;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\SearchStat;
use App\Models\User;
use App\Support\Reports\ReportMetrics;
use App\Support\Reports\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00', 'Asia/Jakarta'));
    $this->shopee = SalesChannel::create(['name' => 'Shopee', 'slug' => 'shopee', 'type' => 'marketplace', 'is_active' => true]);
    $this->whatsapp = SalesChannel::create(['name' => 'WhatsApp', 'slug' => 'whatsapp', 'type' => 'whatsapp', 'is_active' => true]);
});

afterEach(fn () => Carbon::setTestNow());

/** Catat klik pada tanggal WIB tertentu. */
function click(Product $product, SalesChannel $channel, string $date, array $attributes = []): ClickEvent
{
    return ClickEvent::create([
        'product_id' => $product->id,
        'sales_channel_id' => $channel->id,
        'is_bot' => false,
        'created_at' => Carbon::parse($date, 'Asia/Jakarta')->utc(),
        ...$attributes,
    ]);
}

it('resolves presets into WIB ranges with an equally long previous period', function () {
    $period = ReportPeriod::fromFilters(['period' => '7d']);

    expect($period->from->toDateTimeString())->toBe('2026-10-14 00:00:00')
        ->and($period->to->toDateString())->toBe('2026-10-20')
        ->and($period->previous()->from->toDateString())->toBe('2026-10-07')
        ->and($period->previous()->to->toDateString())->toBe('2026-10-13')
        ->and(ReportPeriod::fromFilters(['period' => 'last_month'])->label())->toBe('01 Sep 2026 – 30 Sep 2026')
        ->and(ReportPeriod::fromFilters(['period' => 'custom', 'from' => '2026-10-10', 'until' => '2026-10-01'])->days())->toBe(10)
        ->and(ReportPeriod::change(120, 100))->toBe(20.0)
        ->and(ReportPeriod::change(5, 0))->toBeNull();
});

it('counts human clicks per day, channel and product with a change against the previous period', function () {
    $semen = Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik', 'is_published' => true]);
    $bata = Product::create(['name' => 'Bata Ringan', 'slug' => 'bata-ringan', 'is_published' => true]);

    click($semen, $this->shopee, '2026-10-18 09:00');
    click($semen, $this->shopee, '2026-10-19 09:00');
    click($semen, $this->whatsapp, '2026-10-19 10:00');
    click($semen, $this->shopee, '2026-10-19 11:00', ['is_bot' => true]);
    click($semen, $this->shopee, '2026-10-10 09:00'); // periode sebelumnya
    click($bata, $this->whatsapp, '2026-10-20 08:00');

    $period = ReportPeriod::fromFilters(['period' => '7d']);
    $top = ReportMetrics::topProducts($period)->values();

    expect(ReportMetrics::clicks($period))->toBe(4)
        ->and(ReportMetrics::clickedProducts($period))->toBe(2)
        ->and(array_slice(ReportMetrics::dailyClicks($period), -3, 3, true))->toBe(['2026-10-18' => 1, '2026-10-19' => 2, '2026-10-20' => 1])
        ->and(ReportMetrics::channelShares($period)->pluck('clicks', 'name')->all())->toEqualCanonicalizing(['WhatsApp' => 2, 'Shopee' => 2])
        ->and($top[0])->toMatchArray(['name' => 'Semen Gresik', 'clicks' => 3, 'change' => 200.0, 'channels' => 'Shopee 2 · WhatsApp 1'])
        ->and($top[1])->toMatchArray(['name' => 'Bata Ringan', 'clicks' => 1, 'change' => null]);
});

it('lists published products without clicks in the period', function () {
    $clicked = Product::create(['name' => 'Laku', 'slug' => 'laku', 'is_published' => true]);
    $idle = Product::create(['name' => 'Sepi', 'slug' => 'sepi', 'is_published' => true]);
    Product::create(['name' => 'Draf', 'slug' => 'draf', 'is_published' => false]);
    click($clicked, $this->shopee, '2026-10-19 09:00');
    click($idle, $this->shopee, '2026-10-19 09:00', ['is_bot' => true]);

    expect(ReportMetrics::zeroClickProducts(ReportPeriod::fromFilters(['period' => '7d']))->pluck('name')->all())->toBe(['Sepi']);
});

it('groups clicks by page type, UTM campaign and the article they came from', function () {
    $product = Product::create(['name' => 'Semen', 'slug' => 'semen', 'is_published' => true]);
    Article::create(['title' => 'Cara memilih semen', 'slug' => 'cara-memilih-semen', 'content' => '<p>x</p>', 'published_at' => now()]);

    click($product, $this->shopee, '2026-10-19 09:00', ['page_url' => url('/produk/semen')]);
    click($product, $this->shopee, '2026-10-19 09:05', ['page_url' => url('/artikel/cara-memilih-semen')]);
    click($product, $this->shopee, '2026-10-19 09:10', ['page_url' => url('/artikel/cara-memilih-semen'), 'utm_source' => 'instagram', 'utm_campaign' => 'promo-oktober']);
    click($product, $this->shopee, '2026-10-19 09:15', ['page_url' => url('/')]);

    $period = ReportPeriod::fromFilters(['period' => '7d']);

    expect(ReportMetrics::clickOrigins($period)->pluck('clicks', 'origin')->all())->toEqualCanonicalizing(['Artikel' => 2, 'Halaman produk' => 1, 'Beranda' => 1])
        ->and(ReportMetrics::utmCampaigns($period)->values()->all())->toBe([['source' => 'instagram', 'medium' => '—', 'campaign' => 'promo-oktober', 'clicks' => 1]])
        ->and(ReportMetrics::articlesDrivingClicks($period)->values()->all())->toBe([['title' => 'Cara memilih semen', 'slug' => 'cara-memilih-semen', 'clicks' => 2]]);
});

it('counts leads per form by follow-up status, leaving spam out', function () {
    $form = CustomForm::factory()->create(['name' => 'Minta penawaran']);
    foreach (['new', 'new', 'contacted', 'done', 'spam'] as $status) {
        FormSubmission::factory()->for($form, 'form')->create(['status' => $status, 'created_at' => now()->subDay()]);
    }

    $period = ReportPeriod::fromFilters(['period' => '7d']);

    expect(ReportMetrics::leads($period))->toBe(4)
        ->and(ReportMetrics::leadsByForm($period)->values()->first())->toMatchArray(['form' => 'Minta penawaran', 'total' => 4, 'new' => 2, 'contacted' => 1, 'done' => 1]);
});

it('records product and article views once per session and never for bots', function () {
    $product = Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik', 'is_published' => true]);
    $article = Article::create(['title' => 'Cara memilih semen', 'slug' => 'cara-memilih-semen', 'content' => '<p>x</p>', 'published_at' => now()->subDay()]);

    $this->get('/produk/semen-gresik')->assertOk();
    $this->get('/produk/semen-gresik')->assertOk();
    $this->get('/artikel/cara-memilih-semen')->assertOk();
    $this->flushSession()->withHeader('User-Agent', 'Googlebot/2.1')->get('/produk/semen-gresik')->assertOk();

    expect(PageViewStat::where('viewable_type', PageViewStat::PRODUCT)->where('viewable_id', $product->id)->sole())
        ->date->toDateString()->toBe('2026-10-20')
        ->views->toBe(1)
        ->and(PageViewStat::where('viewable_type', PageViewStat::ARTICLE)->value('views'))->toBe(1)
        ->and($article->fresh()->views_count)->toBe(1);
});

it('records searches from the search page with their result count', function () {
    Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik', 'is_published' => true]);

    $this->get('/cari?q=Semen')->assertOk();
    $this->get('/cari?q=semen')->assertOk();
    $this->flushSession()->get('/cari?q=semen')->assertOk();
    $this->get('/cari?q=keramik')->assertOk();
    $this->get('/cari?q=k')->assertOk();
    $this->getJson('/cari/live?q=granit')->assertOk();

    expect(SearchStat::orderBy('term')->get(['term', 'searches', 'results'])->toArray())->toBe([
        ['term' => 'keramik', 'searches' => 1, 'results' => 0],
        ['term' => 'semen', 'searches' => 2, 'results' => 1],
    ]);
});

it('reports views, conversion, article reading and searches for the period', function () {
    $semen = Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik', 'is_published' => true]);
    $bata = Product::create(['name' => 'Bata Ringan', 'slug' => 'bata-ringan', 'is_published' => true]);
    $article = Article::create(['title' => 'Cara memilih semen', 'slug' => 'cara-memilih-semen', 'content' => '<p>x</p>', 'published_at' => now()]);

    PageViewStat::insert([
        ['date' => '2026-10-19', 'viewable_type' => 'product', 'viewable_id' => $semen->id, 'views' => 30],
        ['date' => '2026-10-20', 'viewable_type' => 'product', 'viewable_id' => $semen->id, 'views' => 10],
        ['date' => '2026-10-20', 'viewable_type' => 'product', 'viewable_id' => $bata->id, 'views' => 5],
        ['date' => '2026-10-20', 'viewable_type' => 'article', 'viewable_id' => $article->id, 'views' => 8],
        ['date' => '2026-10-01', 'viewable_type' => 'product', 'viewable_id' => $semen->id, 'views' => 99],
    ]);
    click($semen, $this->shopee, '2026-10-19 09:00', ['page_url' => url('/artikel/cara-memilih-semen')]);
    click($semen, $this->shopee, '2026-10-20 09:00');

    SearchStat::insert([
        ['date' => '2026-10-18', 'term' => 'semen', 'searches' => 6, 'results' => 3],
        ['date' => '2026-10-10', 'term' => 'semen', 'searches' => 2, 'results' => 3],
        ['date' => '2026-10-18', 'term' => 'keramik', 'searches' => 2, 'results' => 4],
        ['date' => '2026-10-19', 'term' => 'keramik', 'searches' => 1, 'results' => 0],
        ['date' => '2026-10-19', 'term' => 'bata', 'searches' => 1, 'results' => 0],
        ['date' => '2026-10-20', 'term' => 'bata', 'searches' => 1, 'results' => 2],
    ]);

    $period = ReportPeriod::fromFilters(['period' => '7d']);

    expect(ReportMetrics::pageViews($period))->toBe(53)
        ->and(ReportMetrics::pageViews($period, PageViewStat::PRODUCT))->toBe(45)
        ->and(array_slice(ReportMetrics::dailyViews($period), -2, 2, true))->toBe(['2026-10-19' => 30, '2026-10-20' => 23])
        ->and(ReportMetrics::productConversion($period)->values()->all())->toBe([
            ['product_id' => $semen->id, 'name' => 'Semen Gresik', 'category' => '—', 'views' => 40, 'clicks' => 2, 'rate' => 5.0],
            ['product_id' => $bata->id, 'name' => 'Bata Ringan', 'category' => '—', 'views' => 5, 'clicks' => 0, 'rate' => 0.0],
        ])
        ->and(ReportMetrics::articlePerformance($period)->values()->all())->toBe([
            ['title' => 'Cara memilih semen', 'slug' => 'cara-memilih-semen', 'views' => 8, 'clicks' => 1, 'rate' => 12.5],
        ])
        ->and(ReportMetrics::topSearches($period)->values()->all())->toBe([
            ['term' => 'semen', 'searches' => 6, 'results' => 3, 'change' => 200.0],
            ['term' => 'keramik', 'searches' => 3, 'results' => 0, 'change' => null],
            ['term' => 'bata', 'searches' => 2, 'results' => 2, 'change' => null],
        ])
        ->and(ReportMetrics::zeroResultSearches($period)->pluck('term')->all())->toBe(['keramik']);
});

it('opens the report center for editors only and renders every report', function () {
    $product = Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik', 'is_published' => true]);
    click($product, $this->shopee, '2026-10-19 09:00');

    $this->actingAs(User::factory()->create(['role' => 'contributor', 'is_active' => true]))->get(ReportCenter::getUrl())->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]))->get(ReportCenter::getUrl())->assertOk()
        ->assertSee('Pusat Laporan')->assertSee('Klik ke toko')->assertSee('Produk paling banyak diklik')
        ->assertSee('Produk tanpa klik')->assertSee('Halaman asal klik')->assertSee('Performa artikel')
        ->assertSee('Tayangan halaman')->assertSee('Konversi produk')->assertSee('Pencarian terpopuler')->assertSee('Dicari tapi tidak ketemu')
        ->assertSee('Kampanye & tautan promosi (UTM)')->assertSee('Lead formulir')->assertSee('Semen Gresik');
});

it('exports a report table to CSV for the selected period', function () {
    $product = Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik', 'is_published' => true]);
    click($product, $this->shopee, '2026-10-19 09:00');
    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]));

    Livewire::test(TopProductsTable::class, ['pageFilters' => ['period' => '7d']])
        ->callTableAction('export')
        ->assertFileDownloaded('produk-terlaris-20261014-20261020.csv');
});
