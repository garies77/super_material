<?php

use App\Models\LinkItem;
use App\Models\LinkPage;
use App\Models\LinkStat;
use App\Models\Product;
use App\Models\User;
use App\Support\Reports\LinkRecorder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
