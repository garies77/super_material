<?php

use App\Models\LinkItem;
use App\Models\LinkPage;
use App\Models\LinkStat;
use App\Models\Product;
use App\Models\User;
use App\Support\Reports\LinkRecorder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Instagram 300.0';

beforeEach(function () {
    $this->withHeader('User-Agent', MOBILE_UA);
});

it('shows a published page with only visible items in order', function () {
    $page = LinkPage::factory()->create(['title' => 'Jaya Intero Las']);
    LinkItem::factory()->for($page)->create(['label' => 'Kedua', 'sort' => 2]);
    LinkItem::factory()->for($page)->create(['label' => 'Pertama', 'sort' => 1]);
    LinkItem::factory()->for($page)->create(['label' => 'Nonaktif', 'is_active' => false]);

    $this->get(route('link.show', $page->slug))
        ->assertOk()
        ->assertSee('Jaya Intero Las')
        ->assertSeeInOrder(['Pertama', 'Kedua'])
        ->assertDontSee('Nonaktif');

});

it('hides draft pages from visitors but lets reviewers preview them without counting', function () {
    $page = LinkPage::factory()->draft()->create();

    $this->get(route('link.show', $page->slug))->assertNotFound();

    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]))
        ->get(route('link.show', $page->slug))->assertOk()->assertSee('Pratinjau draf');

    expect(LinkStat::count())->toBe(0);
});

it('counts a page view once per session with its source and device', function () {
    $page = LinkPage::factory()->create();

    $this->get(route('link.show', $page->slug).'?utm_source=Instagram')->assertOk();
    $this->get(route('link.show', $page->slug))->assertOk();

    $stat = LinkStat::sole();
    expect($stat->link_item_id)->toBe(LinkStat::PAGE_VIEW)
        ->and($stat->source)->toBe('instagram')
        ->and($stat->device)->toBe('mobile')
        ->and($stat->count)->toBe(1);
});

it('ignores bots', function () {
    $page = LinkPage::factory()->create();
    $item = LinkItem::factory()->for($page)->create();

    $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get(route('link.show', $page->slug))->assertOk();
    $this->withHeader('User-Agent', 'Googlebot/2.1')->get(route('link.click', [$page->slug, $item->id]))->assertRedirect($item->url);

    expect(LinkStat::count())->toBe(0);
});

it('records a click under the source of the visit and redirects to the stored destination', function () {
    $page = LinkPage::factory()->create();
    $item = LinkItem::factory()->for($page)->create(['url' => 'https://mitra.example.com/katalog']);

    $this->get(route('link.show', $page->slug).'?utm_source=tiktok');
    $this->get(route('link.click', [$page->slug, $item->id]))
        ->assertRedirect('https://mitra.example.com/katalog')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get(route('link.click', [$page->slug, $item->id]));

    $click = LinkStat::where('link_item_id', $item->id)->sole();
    expect($click->count)->toBe(2)->and($click->source)->toBe('tiktok');
});

it('refuses items that belong to another page, are hidden, or are headings', function () {
    $page = LinkPage::factory()->create();
    $other = LinkItem::factory()->create();
    $hidden = LinkItem::factory()->for($page)->create(['is_active' => false]);
    $heading = LinkItem::factory()->for($page)->heading()->create();

    $this->get(route('link.click', [$page->slug, $other->id]))->assertNotFound();
    $this->get(route('link.click', [$page->slug, $hidden->id]))->assertNotFound();
    $this->get(route('link.click', [$page->slug, $heading->id]))->assertRedirect($page->url());

    expect(LinkStat::where('link_item_id', '>', 0)->count())->toBe(0);
});

it('ignores unsafe stored urls instead of redirecting to them', function () {
    $page = LinkPage::factory()->create();
    $item = LinkItem::factory()->for($page)->create(['url' => 'javascript:alert(1)']);

    $this->get(route('link.show', $page->slug))->assertDontSee($item->label);
    $this->get(route('link.click', [$page->slug, $item->id]))->assertRedirect($page->url());
});

it('builds whatsapp links with an international number', function () {
    $page = LinkPage::factory()->create();
    $item = LinkItem::factory()->for($page)->whatsapp('0812-3456-7890')->create();

    $this->get(route('link.click', [$page->slug, $item->id]))
        ->assertRedirect('https://wa.me/6281234567890?text=Halo%2C%20saya%20mau%20tanya');
});

it('sends product buttons through the tracked /go route', function () {
    $this->seed(DatabaseSeeder::class);
    $product = Product::published()->whereHas('links')->firstOrFail();
    $channel = $product->links()->first()->channel;
    $page = LinkPage::factory()->create(['slug' => 'promo']);
    $item = LinkItem::factory()->for($page)->create([
        'type' => 'product', 'link_type' => null, 'url' => null,
        'product_id' => $product->id, 'sales_channel_id' => $channel->id,
    ]);

    $this->get(route('link.click', [$page->slug, $item->id]))
        ->assertRedirect(route('go', [$product->slug, $channel->slug]).'?utm_source=link-bio&utm_medium=link-bio&utm_campaign=promo');
});

it('maps referers to readable sources', function (string $referer, string $source) {
    expect(LinkRecorder::sourceFromReferer($referer))->toBe($source);
})->with([
    ['https://l.instagram.com/?u=x', 'instagram'],
    ['https://www.tiktok.com/@jayaintero', 'tiktok'],
    ['https://t.co/abc', 'x'],
    ['https://www.google.co.id/', 'google'],
    ['https://blog.contoh.com/a', 'blog.contoh.com'],
    ['', 'langsung'],
]);

it('ships complete SEO and share tags for published pages', function () {
    $page = LinkPage::factory()->create([
        'title' => 'Jaya Intero', 'bio' => 'Bengkel las pagar.', 'og_image_path' => 'og/jaya-intero-og.jpg',
        'socials' => [['platform' => 'instagram', 'url' => 'https://www.instagram.com/jayaintero'], ['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@jayaintero']],
    ]);

    $html = $this->get(route('link.show', $page->slug))->assertOk()
        ->assertSee('<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">', false)
        ->assertSee('<link rel="canonical" href="'.$page->url().'">', false)
        ->assertSee('<meta property="og:image" content="'.Storage::disk('public')->url('og/jaya-intero-og.jpg').'">', false)
        ->assertSee('<meta property="og:image:width" content="1200">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<meta property="og:description" content="Bengkel las pagar.">', false)
        ->getContent();

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);
    $data = json_decode($match[1], true);
    expect($data['@type'])->toBe('ProfilePage')
        ->and($data['mainEntity']['name'])->toBe('Jaya Intero')
        ->and($data['mainEntity']['sameAs'])->toBe(['https://www.instagram.com/jayaintero', 'https://www.tiktok.com/@jayaintero']);
});

it('uses a small card when only the profile photo is available', function () {
    $page = LinkPage::factory()->create(['avatar_path' => 'link-bio/logo.png']);

    $this->get(route('link.show', $page->slug))
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee('<meta property="og:image" content="'.Storage::disk('public')->url('link-bio/logo.png').'">', false);
});

it('keeps noindex pages out of Google and the sitemap', function () {
    $listed = LinkPage::factory()->create(['slug' => 'tampil']);
    $hidden = LinkPage::factory()->create(['slug' => 'rahasia', 'noindex' => true]);
    LinkPage::factory()->draft()->create(['slug' => 'draf']);

    $this->get(route('link.show', 'rahasia'))->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    $this->get('/sitemap.xml')->assertSee('sitemap-links.xml', false);
    $this->get('/sitemap-links.xml')->assertOk()
        ->assertSee('<loc>'.$listed->url().'</loc>', false)
        ->assertDontSee($hidden->url(), false)
        ->assertDontSee('/l/draf', false);
});

it('gives a button its own colors on top of the theme', function () {
    $page = LinkPage::factory()->create();
    LinkItem::factory()->for($page)->create(['label' => 'Shopee', 'button_color' => '#ee4d2d']);
    LinkItem::factory()->for($page)->create(['label' => 'Tokopedia', 'button_color' => '#03AC0E', 'text_color' => '#111111']);
    LinkItem::factory()->for($page)->create(['label' => 'Rusak', 'button_color' => 'red;}</style><script>x</script>']);

    $html = $this->get(route('link.show', $page->slug))->assertOk()
        ->assertSee('style="--lb-btn:#EE4D2D;--lb-accent:#EE4D2D;--lb-btn-text:#111111;--lb-accent-text:#111111"', false)
        ->assertSee('style="--lb-btn:#03AC0E;--lb-accent:#03AC0E;--lb-btn-text:#111111;--lb-accent-text:#111111"', false)
        ->getContent();

    expect($html)->not->toContain('<script>x</script>');
});

it('colors social icons by theme text, brand colors, a chosen color, or per icon', function (array $design, array $expected) {
    $page = LinkPage::factory()->create(['theme' => 'light', 'design' => $design, 'socials' => [
        ['platform' => 'instagram', 'url' => 'https://www.instagram.com/jayaintero'],
        ['platform' => 'youtube', 'url' => 'https://www.youtube.com/@jayaintero', 'color' => '#00aa00'],
        ['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@jayaintero', 'color' => 'url(javascript:x)'],
    ]]);

    $html = $this->get(route('link.show', $page->slug))->assertOk()->getContent();
    preg_match_all('/lb-social[^"]*"\s+style="color: ([^"]+)"/', $html, $colors);

    expect($colors[1])->toBe($expected)->and($html)->not->toContain('javascript:x');
})->with([
    'ikut warna teks' => [['social_style' => 'text'], ['#0B0F19', '#00AA00', '#0B0F19']],
    'warna asli merek' => [['social_style' => 'brand'], ['#E4405F', '#00AA00', '#FE2C55']],
    'warna pilihan' => [['social_style' => 'custom', 'social_color' => '#ffc20e'], ['#FFC20E', '#00AA00', '#FFC20E']],
]);
