<?php

use App\Filament\Pages\AdReport;
use App\Filament\Resources\Ads\Pages\CreateAd;
use App\Filament\Resources\Ads\Pages\ListAds;
use App\Models\Ad;
use App\Models\AdEvent;
use App\Models\Article;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

/** Kampanye format Banner yang tampil sebagai pop-up (dulu: Banner Kampanye). */
function makePopup(array $attrs = []): Ad
{
    return Ad::create(array_merge([
        'name' => 'Promo Oktober',
        'placement' => 'banner',
        'display' => 'popup',
        'pages' => array_keys(Ad::PAGES),
        'format' => 'text',
        'label' => 'Sorotan',
        'is_active' => true,
        'image' => 'https://cdn.example.com/promo.jpg',
        'image_alt' => 'Diskon semen',
        'title' => 'KAMPANYE-UJI',
        'body' => 'Diskon hingga 20%.',
        'cta_label' => 'Lihat promo',
        'target_url' => 'https://mitra.example.com/promo',
    ], $attrs));
}

/** Kampanye format Banner di dalam halaman. */
function makeBanner(array $attrs = []): Ad
{
    return Ad::create(array_merge([
        'name' => 'Banner uji',
        'placement' => 'banner',
        'display' => 'inline',
        'pages' => ['home'],
        'format' => 'text',
        'label' => 'Sorotan',
        'is_active' => true,
        'title' => 'BANNER-UJI',
        'cta_label' => 'Lihat',
        'target_url' => '/produk',
    ], $attrs));
}

it('renders a live pop-up inert in a template with data-nosnippet and neutral markup', function () {
    $c = makePopup();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('data-nosnippet data-sm-campaign-root')
        ->toContain('<template data-sm-campaign=')
        ->toContain('KAMPANYE-UJI')
        ->toContain('role="dialog" aria-modal="true" aria-labelledby="sm-campaign-title-'.$c->id.'"')
        ->toContain('rel="nofollow noopener"')
        ->toContain('utm_campaign=promo-oktober')
        ->toContain(route('ad.event', $c))
        ->not->toMatch('/class="[^"]*\b(ad|ads|banner|popup)\b/');

    // Gambar hanya ada di dalam <template>, jadi tidak diunduh saat halaman dimuat
    $outsideTemplates = preg_replace('#<template.*?</template>#s', '', $html);
    expect($outsideTemplates)->not->toContain('cdn.example.com/promo.jpg');

    // Label Sponsor tetap memakai rel="sponsored"
    makePopup(['name' => 'Mitra', 'label' => 'Sponsor', 'priority' => 5]);
    expect($this->get('/')->getContent())->toContain('rel="sponsored nofollow noopener"');
});

it('keeps internal CTA links clean and adds UTM only to external links', function () {
    expect(makePopup(['target_url' => '/produk'])->ctaUrl())->toBe('/produk')
        ->and(makePopup(['target_url' => 'https://wa.me/628123'])->ctaUrl())->toBe('https://wa.me/628123')
        ->and(makePopup(['target_url' => 'https://toko.example.com/x?utm_source=mitra#a'])->ctaUrl())
        ->toBe('https://toko.example.com/x?utm_source=mitra&utm_medium=onsite&utm_campaign=promo-oktober#a');
});

it('shows only live pop-ups, highest priority first, capped to a few candidates', function () {
    makePopup(['name' => 'Rendah', 'title' => 'PRIO-RENDAH', 'priority' => 1]);
    makePopup(['name' => 'Tinggi', 'title' => 'PRIO-TINGGI', 'priority' => 9]);
    makePopup(['name' => 'Mati', 'title' => 'STATUS-MATI', 'is_active' => false, 'priority' => 99]);
    makePopup(['name' => 'Nanti', 'title' => 'JADWAL-NANTI', 'starts_at' => now()->addDay(), 'priority' => 99]);
    makePopup(['name' => 'Lewat', 'title' => 'JADWAL-LEWAT', 'ends_at' => now()->subMinute(), 'priority' => 99]);

    $this->get('/')->assertOk()
        ->assertSeeInOrder(['PRIO-TINGGI', 'PRIO-RENDAH'])
        ->assertDontSee('STATUS-MATI')->assertDontSee('JADWAL-NANTI')->assertDontSee('JADWAL-LEWAT');

    foreach (range(1, 5) as $i) {
        makePopup(['name' => "Extra {$i}", 'priority' => 50]);
    }
    expect(substr_count($this->get('/')->getContent(), '<template data-sm-campaign='))->toBe(Ad::MAX_POPUPS);
});

it('does not render on technical routes, search, or policy pages', function () {
    makePopup();

    $this->get(route('search', ['q' => 'semen']))->assertOk()->assertDontSee('data-sm-campaign-root', false);
    $this->get('/kebijakan-privasi')->assertOk()->assertDontSee('data-sm-campaign-root', false);
    $this->get('/')->assertSee('data-sm-campaign-root', false);
});

it('shows pop-ups only on the selected pages and categories', function () {
    $product = Product::published()->whereNotNull('category_id')->first();
    $article = Article::published()->first();
    $otherCategory = Category::whereKeyNot($product->category_id)->whereNull('parent_id')->first();

    makePopup(['title' => 'T-HOME', 'pages' => ['home']]);
    makePopup(['title' => 'T-PRODUK', 'pages' => ['product']]);
    makePopup(['title' => 'T-ARTIKEL', 'pages' => ['article']]);
    makePopup(['title' => 'T-KATEGORI', 'pages' => ['category', 'product'], 'targeting' => 'categories', 'target_category_ids' => [$product->category_id]]);
    makePopup(['title' => 'T-LAIN', 'pages' => ['category', 'product'], 'targeting' => 'categories', 'target_category_ids' => [$otherCategory->id]]);

    $this->get('/')->assertSee('T-HOME')->assertDontSee('T-PRODUK')->assertDontSee('T-ARTIKEL')->assertDontSee('T-KATEGORI');
    $this->get($product->url())->assertSee('T-PRODUK')->assertSee('T-KATEGORI')->assertDontSee('T-HOME')->assertDontSee('T-LAIN');
    $this->get($article->url())->assertSee('T-ARTIKEL')->assertDontSee('T-PRODUK');
    $this->get(Category::find($product->category_id)->url())->assertSee('T-KATEGORI')->assertDontSee('T-PRODUK');
});

it('shows in-page banners on the homepage, product, and static pages that were selected', function () {
    $product = Product::published()->first();
    makeBanner(['title' => 'BANNER-HOME', 'pages' => ['home']]);
    makeBanner(['title' => 'BANNER-PRODUK', 'pages' => ['product', 'page']]);

    $this->get('/')->assertSee('BANNER-HOME')->assertDontSee('BANNER-PRODUK')->assertSee('data-sm-slot="page_top"', false);
    $this->get($product->url())->assertSee('BANNER-PRODUK')->assertDontSee('BANNER-HOME');
    $static = Page::published()->where('slug', 'not like', '%kebijakan%')->where('slug', 'not like', '%privasi%')->first();
    $this->get($static->url())->assertSee('BANNER-PRODUK');
    $this->get(route('article.index'))->assertDontSee('BANNER-HOME')->assertDontSee('BANNER-PRODUK');
});

it('limits the sidebar format to article pages', function () {
    $ad = Ad::create(['name' => 'Samping', 'placement' => 'sidebar', 'pages' => ['home', 'article'], 'format' => 'text',
        'label' => 'Sorotan', 'title' => 'SIDEBAR-UJI', 'target_url' => '/produk', 'is_active' => true]);

    expect($ad->fresh()->pages)->toBe(['article'])->and($ad->slot)->toBe('sidebar');
    $this->get('/')->assertDontSee('SIDEBAR-UJI');
    $this->get(Article::published()->first()->url())->assertSee('SIDEBAR-UJI');
});

it('passes trigger and frequency settings to the browser', function () {
    makePopup(['trigger' => 'scroll', 'scroll_percent' => 30, 'frequency' => 'days', 'frequency_days' => 7]);

    $this->get('/')->assertSee('"trigger":"scroll"', false)
        ->assertSee('"scroll":30', false)
        ->assertSee('"frequency":"days"', false)
        ->assertSee('"days":7', false);
});

it('records pop-up impressions, clicks, and closes without raw IPs and flags bots', function () {
    $c = makePopup();

    foreach (['impression', 'click', 'close'] as $type) {
        $this->post(route('ad.event', $c), ['type' => $type, 'd' => 'mobile', 'p' => '/produk'], ['User-Agent' => 'Mozilla/5.0 (iPhone)'])->assertNoContent();
    }
    $this->post(route('ad.event', $c), ['type' => 'impression'], ['User-Agent' => 'Googlebot/2.1'])->assertNoContent();
    $this->post(route('ad.event', $c), ['type' => 'hack'])->assertStatus(422);

    expect(AdEvent::where('ad_id', $c->id)->where('is_bot', false)->orderBy('id')->pluck('type')->all())->toBe(['impression', 'click', 'close'])
        ->and(AdEvent::where('ad_id', $c->id)->where('is_bot', true)->count())->toBe(1)
        ->and(AdEvent::where('ad_id', $c->id)->first()->slot)->toBe('popup')
        ->and(AdEvent::where('ad_id', $c->id)->first()->page_url)->toBe('/produk')
        ->and(Schema::hasColumn('ad_events', 'ip'))->toBeFalse();

    // Kampanye yang tidak tayang tidak dicatat
    $off = makePopup(['is_active' => false]);
    $this->post(route('ad.event', $off), ['type' => 'impression'])->assertNoContent();
    expect(AdEvent::where('ad_id', $off->id)->count())->toBe(0);
});

it('lets admins manage, duplicate, preview, and report on campaigns from one menu', function () {
    $c = makePopup(['is_active' => false, 'title' => 'PRATINJAU-KAMPANYE']);
    foreach (['impression', 'impression', 'click', 'close'] as $type) {
        AdEvent::create(['ad_id' => $c->id, 'type' => $type, 'slot' => 'popup', 'created_at' => now()]);
    }
    AdEvent::create(['ad_id' => $c->id, 'type' => 'click', 'slot' => 'popup', 'is_bot' => true, 'created_at' => now()]);

    $this->get(route('ad.preview', $c))->assertForbidden();

    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
    $this->get('/admin/kampanye')->assertOk()->assertSee('Promo Oktober')->assertSee('Banner pop-up');

    $this->get(route('ad.preview', $c))->assertOk()->assertSee('Pratinjau mobile')->assertSee('Belum tayang');
    $this->get(route('ad.preview-frame', $c))->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('PRATINJAU-KAMPANYE')
        ->assertSee('data-sm-campaign-preview', false);

    $banner = makeBanner(['is_active' => false, 'title' => 'PRATINJAU-BANNER', 'pages' => ['home']]);
    $this->get(route('ad.preview-frame', $banner))->assertOk()->assertSee('PRATINJAU-BANNER');

    Livewire::test(ListAds::class)->callTableAction('replicate', $c);
    expect(Ad::where('name', 'Promo Oktober (salinan)')->value('is_active'))->toBeFalse();

    Livewire::test(AdReport::class)
        ->assertSee('Promo Oktober')
        ->assertSee('Pop-up')
        ->assertSee('50,00%')
        ->call('export')
        ->assertFileDownloaded();
});

it('creates a pop-up from the Kampanye form, reading the schedule as WIB', function () {
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());

    Livewire::test(CreateAd::class)
        ->fillForm([
            'name' => 'Jadwal WIB', 'label' => 'Sorotan', 'placement' => 'banner', 'display' => 'popup',
            'pages' => ['home', 'product'], 'format' => 'image', 'is_active' => true, 'priority' => 0,
            'image' => ['https://cdn.example.com/a.jpg'], 'image_alt' => 'Promo', 'target_url' => '/produk',
            'starts_at' => '2026-10-22 13:07', 'ends_at' => '2026-10-23 13:07',
            'targeting' => 'all', 'device' => 'all', 'trigger' => 'delay', 'delay_seconds' => 5, 'frequency' => 'session',
        ])
        ->call('create')
        ->assertHasNoFormErrors(['starts_at', 'ends_at', 'name', 'target_url', 'pages']);

    $c = Ad::where('name', 'Jadwal WIB')->first();
    expect($c)->not->toBeNull()
        ->and($c->slot)->toBe('popup')
        ->and($c->pages)->toBe(['home', 'product'])
        ->and($c->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-22 06:07');
});
