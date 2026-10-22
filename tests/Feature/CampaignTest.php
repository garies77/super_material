<?php

use App\Filament\Pages\CampaignReport;
use App\Filament\Resources\Campaigns\Pages\CreateCampaign;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

function makeCampaign(array $attrs = []): Campaign
{
    return Campaign::create(array_merge([
        'name' => 'Promo Oktober',
        'status' => 'active',
        'layout' => 'image_text',
        'image' => 'https://cdn.example.com/promo.jpg',
        'image_alt' => 'Diskon semen',
        'title' => 'KAMPANYE-UJI',
        'body' => 'Diskon hingga 20%.',
        'cta_label' => 'Lihat promo',
        'target_url' => 'https://mitra.example.com/promo',
    ], $attrs));
}

it('renders the live campaign inert in a template with data-nosnippet and sponsored links (PRD 8.9)', function () {
    $c = makeCampaign();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('data-sm-campaign-root')
        ->toContain('data-nosnippet data-sm-campaign-root')
        ->toContain('<template data-sm-campaign=')
        ->toContain('KAMPANYE-UJI')
        ->toContain('role="dialog" aria-modal="true" aria-labelledby="sm-campaign-title-'.$c->id.'"')
        ->toContain('rel="sponsored nofollow noopener"')
        ->toContain('utm_campaign=promo-oktober')
        ->toContain(route('campaign.event', $c))
        // Nama yang mudah tersaring ad blocker tidak dipakai di markup modal
        ->not->toMatch('/class="[^"]*\b(ad|ads|banner|popup)\b/');

    // Gambar hanya ada di dalam <template>, jadi tidak diunduh saat halaman dimuat
    $outsideTemplates = preg_replace('#<template.*?</template>#s', '', $html);
    expect($outsideTemplates)->not->toContain('cdn.example.com/promo.jpg');
});

it('keeps internal CTA links clean and adds UTM only to external links', function () {
    expect(makeCampaign(['target_url' => '/produk'])->ctaUrl())->toBe('/produk')
        ->and(makeCampaign(['target_url' => 'https://wa.me/628123'])->ctaUrl())->toBe('https://wa.me/628123')
        ->and(makeCampaign(['target_url' => 'https://toko.example.com/x?utm_source=mitra#a'])->ctaUrl())
        ->toBe('https://toko.example.com/x?utm_source=mitra&utm_medium=onsite&utm_campaign=promo-oktober#a');

    $html = $this->get('/')->getContent();
    expect($html)->toContain('href="/produk" ');
});

it('shows only live campaigns, highest priority first, capped to a few candidates', function () {
    makeCampaign(['name' => 'Rendah', 'title' => 'PRIO-RENDAH', 'priority' => 1]);
    makeCampaign(['name' => 'Tinggi', 'title' => 'PRIO-TINGGI', 'priority' => 9]);
    makeCampaign(['name' => 'Draft', 'title' => 'STATUS-DRAFT', 'status' => 'draft', 'priority' => 99]);
    makeCampaign(['name' => 'Mati', 'title' => 'STATUS-MATI', 'status' => 'disabled', 'priority' => 99]);
    makeCampaign(['name' => 'Nanti', 'title' => 'JADWAL-NANTI', 'starts_at' => now()->addDay(), 'priority' => 99]);
    makeCampaign(['name' => 'Lewat', 'title' => 'JADWAL-LEWAT', 'ends_at' => now()->subMinute(), 'priority' => 99]);

    $this->get('/')->assertOk()
        ->assertSeeInOrder(['PRIO-TINGGI', 'PRIO-RENDAH'])
        ->assertDontSee('STATUS-DRAFT')->assertDontSee('STATUS-MATI')
        ->assertDontSee('JADWAL-NANTI')->assertDontSee('JADWAL-LEWAT');

    foreach (range(1, 5) as $i) {
        makeCampaign(['name' => "Extra {$i}", 'priority' => 50]);
    }
    expect(substr_count($this->get('/')->getContent(), '<template data-sm-campaign='))->toBe(Campaign::MAX_CANDIDATES);
});

it('does not render on technical routes, search, or policy pages', function () {
    makeCampaign();

    $this->get(route('search', ['q' => 'semen']))->assertOk()->assertDontSee('data-sm-campaign-root', false);
    $this->get('/kebijakan-privasi')->assertOk()->assertDontSee('data-sm-campaign-root', false)
        ->assertSee('localStorage');
    $this->get('/')->assertSee('data-sm-campaign-root', false);
});

it('targets home, product, article, and category pages', function () {
    $product = Product::published()->whereNotNull('category_id')->first();
    $article = Article::published()->first();
    $otherCategory = Category::whereKeyNot($product->category_id)->whereNull('parent_id')->first();

    makeCampaign(['title' => 'T-HOME', 'targeting' => 'home']);
    makeCampaign(['title' => 'T-PRODUK', 'targeting' => 'products']);
    makeCampaign(['title' => 'T-ARTIKEL', 'targeting' => 'articles']);
    makeCampaign(['title' => 'T-KATEGORI', 'targeting' => 'categories', 'target_category_ids' => [$product->category_id]]);
    makeCampaign(['title' => 'T-LAIN', 'targeting' => 'categories', 'target_category_ids' => [$otherCategory->id]]);

    $this->get('/')->assertSee('T-HOME')->assertDontSee('T-PRODUK')->assertDontSee('T-ARTIKEL')->assertDontSee('T-KATEGORI');
    $this->get($product->url())->assertSee('T-PRODUK')->assertSee('T-KATEGORI')->assertDontSee('T-HOME')->assertDontSee('T-LAIN');
    $this->get($article->url())->assertSee('T-ARTIKEL')->assertDontSee('T-PRODUK');
    $this->get(Category::find($product->category_id)->url())->assertSee('T-KATEGORI')->assertDontSee('T-PRODUK');
});

it('passes trigger and frequency settings to the browser', function () {
    makeCampaign(['trigger' => 'scroll', 'scroll_percent' => 30, 'frequency' => 'days', 'frequency_days' => 7]);

    $this->get('/')->assertSee('"trigger":"scroll"', false)
        ->assertSee('"scroll":30', false)
        ->assertSee('"frequency":"days"', false)
        ->assertSee('"days":7', false);
});

it('records impressions, clicks, and closes without raw IPs and flags bots', function () {
    $c = makeCampaign();

    foreach (['impression', 'click', 'close'] as $type) {
        $this->post(route('campaign.event', $c), ['type' => $type, 'd' => 'mobile', 'p' => 'https://x.test/produk?q=1'],
            ['User-Agent' => 'Mozilla/5.0 (iPhone)'])->assertNoContent();
    }
    $this->post(route('campaign.event', $c), ['type' => 'impression'], ['User-Agent' => 'Googlebot/2.1'])->assertNoContent();
    $this->post(route('campaign.event', $c), ['type' => 'hack'])->assertStatus(422);

    expect(CampaignEvent::where('is_bot', false)->pluck('type')->all())->toBe(['impression', 'click', 'close'])
        ->and(CampaignEvent::where('is_bot', true)->count())->toBe(1)
        ->and(CampaignEvent::first()->page_path)->toBe('/produk')
        ->and(CampaignEvent::first()->device)->toBe('mobile')
        ->and(Schema::hasColumn('campaign_events', 'ip'))->toBeFalse();

    // Kampanye yang tidak tayang tidak dicatat
    $draft = makeCampaign(['status' => 'draft']);
    $this->post(route('campaign.event', $draft), ['type' => 'impression'])->assertNoContent();
    expect(CampaignEvent::where('campaign_id', $draft->id)->count())->toBe(0);
});

it('lets admins manage, duplicate, preview, and report on campaigns', function () {
    $c = makeCampaign(['status' => 'draft', 'title' => 'PRATINJAU-KAMPANYE']);
    CampaignEvent::create(['campaign_id' => $c->id, 'type' => 'impression', 'created_at' => now()]);
    CampaignEvent::create(['campaign_id' => $c->id, 'type' => 'impression', 'created_at' => now()]);
    CampaignEvent::create(['campaign_id' => $c->id, 'type' => 'click', 'created_at' => now()]);
    CampaignEvent::create(['campaign_id' => $c->id, 'type' => 'close', 'created_at' => now()]);
    CampaignEvent::create(['campaign_id' => $c->id, 'type' => 'click', 'is_bot' => true, 'created_at' => now()]);

    $this->get(route('campaign.preview', $c))->assertForbidden();

    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
    foreach (['/admin/campaigns', '/admin/campaigns/create', "/admin/campaigns/{$c->id}/edit", '/admin/campaign-report'] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get(route('campaign.preview', $c))->assertOk()->assertSee('Pratinjau mobile')->assertSee('Belum tayang');
    $this->get(route('campaign.preview-frame', $c))->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('PRATINJAU-KAMPANYE')
        ->assertSee('data-sm-campaign-preview', false);

    Livewire::test(\App\Filament\Resources\Campaigns\Pages\ListCampaigns::class)
        ->callTableAction('replicate', $c);
    expect(Campaign::where('name', 'Promo Oktober (salinan)')->value('status'))->toBe('draft');

    Livewire::test(CampaignReport::class)
        ->assertSee('Promo Oktober')
        ->assertSee('50,00%')
        ->call('export')
        ->assertFileDownloaded();
});

it('reads the admin schedule as WIB', function () {
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());

    Livewire::test(CreateCampaign::class)
        ->fillForm([
            'name' => 'Jadwal WIB', 'status' => 'active', 'priority' => 0, 'layout' => 'image',
            'image' => ['https://cdn.example.com/a.jpg'], 'image_alt' => 'Promo', 'target_url' => '/produk',
            'starts_at' => '2026-10-22 13:07', 'ends_at' => '2026-10-23 13:07',
            'targeting' => 'all', 'device' => 'all', 'trigger' => 'delay', 'delay_seconds' => 5,
            'frequency' => 'session',
        ])
        ->call('create')
        ->assertHasNoFormErrors(['starts_at', 'ends_at', 'name', 'target_url']);

    $c = Campaign::where('name', 'Jadwal WIB')->first();
    expect($c)->not->toBeNull()
        ->and($c->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-22 06:07');
});
