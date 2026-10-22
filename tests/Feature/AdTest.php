<?php

use App\Filament\Pages\AdReport;
use App\Filament\Resources\Ads\Pages\CreateAd;
use App\Http\Controllers\SeoController;
use App\Models\Ad;
use App\Models\AdEvent;
use App\Models\Article;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->article = Article::published()->first();
    $paragraphs = collect(range(1, 10))->map(fn ($i) => "<p>Paragraf nomor {$i}.</p>")->implode('');
    $this->article->update(['content' => $paragraphs, 'author_initials' => 'gar', 'editor_initials' => 'ed']);
});

function makeAd(array $attrs = []): Ad
{
    return Ad::create(array_merge([
        'name' => 'Iklan uji',
        'slot' => 'top',
        'format' => 'text',
        'title' => 'Promo Uji',
        'target_url' => 'https://mitra.example.com/promo',
        'is_active' => true,
    ], $attrs));
}

it('renders active ads in the right slots with label, fixed height, and data-nosnippet (PRD 8.8)', function () {
    foreach (['top', 'in_content_1', 'in_content_2', 'sidebar', 'bottom'] as $slot) {
        makeAd(['slot' => $slot, 'title' => 'IKLAN-'.$slot, 'label' => $slot === 'bottom' ? 'Sponsor' : 'Iklan']);
    }

    $res = $this->get($this->article->url())->assertOk();

    $res->assertSeeInOrder(['Bagikan:', 'IKLAN-top', 'Paragraf nomor 1.'], false)
        ->assertSeeInOrder(['Paragraf nomor 3.', 'IKLAN-in_content_1', 'Paragraf nomor 4.'], false)
        ->assertSeeInOrder(['Paragraf nomor 8.', 'IKLAN-in_content_2', 'Paragraf nomor 9.'], false)
        ->assertSeeInOrder(['(gar/ed)', 'IKLAN-bottom', 'Bagikan artikel ini:'], false)
        ->assertSee('data-nosnippet', false)
        ->assertSee('h-[250px]', false)
        ->assertSee('>Sponsor</span>', false)
        ->assertSee('>Iklan</span>', false);

    // Sidebar desktop di atas Terpopuler, plus salinan mobile di bawah isi
    expect(substr_count($res->getContent(), 'IKLAN-sidebar'))->toBe(2);
    $res->assertSee('my-8 lg:hidden', false);
});

it('keeps ads out of the article JSON-LD', function () {
    makeAd(['slot' => 'in_content_1', 'title' => 'RAHASIA-IKLAN']);
    $html = $this->get($this->article->url())->assertOk()->assertSee('RAHASIA-IKLAN')->getContent();

    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    expect($m[1])->not->toBeEmpty();
    foreach ($m[1] as $json) {
        expect($json)->not->toContain('RAHASIA-IKLAN');
    }
});

it('hides in-content slots when the article is too short', function () {
    $this->article->update(['content' => '<p>Satu.</p><p>Dua.</p><p>Tiga.</p><p>Empat.</p>']);
    makeAd(['slot' => 'in_content_1', 'title' => 'IKLAN-1']);
    makeAd(['slot' => 'in_content_2', 'title' => 'IKLAN-2']);

    $this->get($this->article->url())->assertSee('IKLAN-1')->assertDontSee('IKLAN-2');
});

it('honours a custom paragraph position and the scroll hint', function () {
    makeAd(['slot' => 'in_content_1', 'title' => 'IKLAN-N5', 'paragraph_after' => 5, 'show_scroll_hint' => true]);

    $this->get($this->article->url())
        ->assertSeeInOrder(['Paragraf nomor 5.', 'IKLAN-N5', 'Scroll untuk melanjutkan', 'Paragraf nomor 6.'], false);
});

it('marks partner links sponsored nofollow in a new tab via /ad/, internal links stay plain', function () {
    $external = makeAd(['slot' => 'top', 'title' => 'EXT']);
    $product = Product::published()->first();
    makeAd(['slot' => 'bottom', 'title' => 'INT', 'target_url' => '/produk/'.$product->slug]);

    $html = $this->get($this->article->url())->getContent();

    expect($html)->toContain('href="'.e(route('ad.click', ['ad' => $external->id, 's' => 'top', 'a' => $this->article->id])).'" target="_blank" rel="sponsored nofollow"');
    preg_match('#<a href="([^"]*)"([^>]*)>Selengkapnya</a>\s*</div>\s*</div>\s*</aside>#', substr($html, strpos($html, '>INT<')), $m);
    expect($m[1])->toBe('/produk/'.$product->slug)
        ->and($m[2])->not->toContain('sponsored')
        ->and($m[2])->not->toContain('_blank')
        ->and($m[2])->toContain('data-promo-internal');
});

it('renders a product card from the catalog', function () {
    $product = Product::published()->first();
    makeAd(['slot' => 'top', 'format' => 'product', 'product_id' => $product->id, 'target_url' => null]);

    $this->get($this->article->url())
        ->assertSee($product->name)
        ->assertSee('href="'.$product->url().'"', false);
});

it('respects schedule, active flag, targeting, and priority', function () {
    makeAd(['title' => 'NONAKTIF', 'is_active' => false, 'priority' => 99]);
    makeAd(['title' => 'BELUM-MULAI', 'starts_at' => now()->addDay(), 'priority' => 99]);
    makeAd(['title' => 'SUDAH-SELESAI', 'ends_at' => now()->subDay(), 'priority' => 99]);
    makeAd(['title' => 'ARTIKEL-LAIN', 'targeting' => 'articles', 'target_article_ids' => [999999], 'priority' => 99]);
    makeAd(['title' => 'PRIORITAS-RENDAH', 'priority' => 1]);
    makeAd(['title' => 'PRIORITAS-TINGGI', 'priority' => 5, 'targeting' => 'articles', 'target_article_ids' => [$this->article->id]]);

    $this->get($this->article->url())
        ->assertSee('PRIORITAS-TINGGI')
        ->assertDontSee('PRIORITAS-RENDAH')
        ->assertDontSee('NONAKTIF')
        ->assertDontSee('BELUM-MULAI')
        ->assertDontSee('SUDAH-SELESAI')
        ->assertDontSee('ARTIKEL-LAIN');
});

it('targets ads by article category and tag', function () {
    $category = $this->article->categories()->first();
    makeAd(['slot' => 'top', 'title' => 'KATEGORI-OK', 'targeting' => 'categories', 'target_category_ids' => [$category?->id]]);
    makeAd(['slot' => 'bottom', 'title' => 'TAG-SALAH', 'targeting' => 'tags', 'target_tag_ids' => [999999]]);

    $res = $this->get($this->article->url());
    $category ? $res->assertSee('KATEGORI-OK') : $res->assertDontSee('KATEGORI-OK');
    $res->assertDontSee('TAG-SALAH');
});

it('redirects /ad/{iklan} with 302 and logs the click without raw IP', function () {
    $ad = makeAd();

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0)', 'Referer' => $this->article->url()])
        ->get('/ad/'.$ad->id.'?s=top&a='.$this->article->id)
        ->assertStatus(302)
        ->assertRedirect('https://mitra.example.com/promo')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $event = AdEvent::sole();
    expect($event->type)->toBe('click')
        ->and($event->slot)->toBe('top')
        ->and($event->article_id)->toBe($this->article->id)
        ->and($event->page_url)->toBe($this->article->url())
        ->and($event->is_bot)->toBeFalse()
        ->and(array_keys($event->getAttributes()))->not->toContain('ip')
        ->and(array_keys($event->getAttributes()))->not->toContain('ip_address');

    $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->get('/ad/'.$ad->id);
    expect(AdEvent::latest('id')->first()->is_bot)->toBeTrue();

    $this->get('/ad/999999')->assertNotFound();
});

it('throttles the /ad/ route', function () {
    $ad = makeAd();
    for ($i = 0; $i < 60; $i++) {
        $this->get('/ad/'.$ad->id)->assertStatus(302);
    }
    $this->get('/ad/'.$ad->id)->assertStatus(429);
});

it('records impressions from the browser beacon', function () {
    $ad = makeAd(['slot' => 'sidebar']);

    $this->post('/promo-event/'.$ad->id, ['type' => 'impression', 's' => 'sidebar', 'a' => $this->article->id])->assertNoContent();
    $this->post('/promo-event/'.$ad->id, ['type' => 'bogus'])->assertStatus(422);

    expect(AdEvent::where('type', 'impression')->where('slot', 'sidebar')->count())->toBe(1);
});

it('disallows /ad/ in robots.txt and keeps it out of the sitemaps (PRD 9.11.3)', function () {
    makeAd();

    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /ad/');

    $this->get('/sitemap.xml')->assertDontSee('/ad/');
    foreach (SeoController::TYPES as $type) {
        $this->get("/sitemap-{$type}.xml")->assertOk()->assertDontSee('/ad/');
    }
});

it('sanitizes custom HTML and restricts it to admins', function () {
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
    $ad = makeAd(['format' => 'html', 'html' => '<p onclick="x()">Halo <a href="https://mitra.example.com">mitra</a></p><script>alert(1)</script>']);

    expect($ad->html)->not->toContain('<script')->not->toContain('onclick')
        ->and($ad->safeHtml())->toContain('rel="sponsored nofollow noopener"')->toContain('target="_blank"');

    $this->actingAs(User::factory()->create(['role' => 'editor']));
    expect(fn () => makeAd(['format' => 'html', 'html' => '<p>Editor</p>']))->toThrow(AuthorizationException::class);
});

it('lets admins manage ads, preview a slot, and export the report', function () {
    $ad = makeAd(['is_active' => false, 'title' => 'PRATINJAU-IKLAN']);
    AdEvent::create(['ad_id' => $ad->id, 'type' => 'impression', 'slot' => 'top', 'article_id' => $this->article->id, 'created_at' => now()]);
    AdEvent::create(['ad_id' => $ad->id, 'type' => 'click', 'slot' => 'top', 'article_id' => $this->article->id, 'created_at' => now()]);

    $this->get(route('ad.preview', $ad))->assertForbidden();

    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
    foreach (['/admin/ads', '/admin/ads/create', "/admin/ads/{$ad->id}/edit", '/admin/ad-report'] as $url) {
        $this->get($url)->assertOk();
    }

    // Pratinjau menampilkan iklan nonaktif, tanpa pelacakan, dan noindex
    $this->get(route('ad.preview', $ad))->assertOk()
        ->assertSee('PRATINJAU-IKLAN')
        ->assertSee('noindex', false)
        ->assertDontSee('data-promo-event', false);

    Livewire::test(AdReport::class)
        ->assertSee('Iklan uji')
        ->assertSee('100,00%')
        ->call('export')
        ->assertFileDownloaded();
});

it('reads the admin schedule as WIB so an ad starts when the admin expects', function () {
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());

    Livewire::test(CreateAd::class)
        ->fillForm([
            'name' => 'Jadwal WIB', 'label' => 'Iklan', 'slot' => 'top', 'format' => 'text',
            'title' => 'Promo', 'target_url' => 'https://mitra.example.com',
            'starts_at' => '2026-10-22 13:07', 'ends_at' => '2026-10-23 13:07',
            'targeting' => 'all', 'priority' => 0, 'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Ad::where('name', 'Jadwal WIB')->first()->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-22 06:07');
});
