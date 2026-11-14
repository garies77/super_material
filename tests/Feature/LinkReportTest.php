<?php

use App\Filament\Pages\LinkReport;
use App\Filament\Reports\Widgets\LinkItemsTable;
use App\Filament\Resources\LinkPages\LinkPageResource;
use App\Filament\Resources\LinkPages\Pages\CreateLinkPage;
use App\Models\LinkItem;
use App\Models\LinkPage;
use App\Models\LinkStat;
use App\Models\User;
use App\Support\Reports\LinkReportMetrics;
use App\Support\Reports\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-13 12:00', 'Asia/Jakarta'));
    $this->page = LinkPage::factory()->create(['title' => 'Bio Utama']);
    $this->other = LinkPage::factory()->create(['title' => 'Bio Promo']);
    $this->wa = LinkItem::factory()->for($this->page)->whatsapp()->create();
    $this->katalog = LinkItem::factory()->for($this->page)->create(['label' => 'Katalog pagar']);
});

afterEach(fn () => Carbon::setTestNow());

function linkStat(LinkPage $page, ?LinkItem $item, int $count, string $date = '2026-11-12', string $source = 'instagram', string $device = 'mobile'): void
{
    LinkStat::create([
        'date' => $date, 'link_page_id' => $page->id, 'link_item_id' => $item?->id ?? LinkStat::PAGE_VIEW,
        'source' => $source, 'device' => $device, 'count' => $count,
    ]);
}

it('computes views, clicks, ctr and per-button share for the selected page and period', function () {
    linkStat($this->page, null, 80);
    linkStat($this->page, null, 20, source: 'tiktok', device: 'desktop');
    linkStat($this->page, $this->wa, 30);
    linkStat($this->page, $this->katalog, 10, source: 'tiktok');
    linkStat($this->page, null, 500, date: '2026-09-01');
    linkStat($this->other, null, 999);

    $period = ReportPeriod::fromFilters(['period' => '7d']);

    expect(LinkReportMetrics::views($period, $this->page->id))->toBe(100)
        ->and(LinkReportMetrics::clicks($period, $this->page->id))->toBe(40)
        ->and(LinkReportMetrics::views($period))->toBe(1099)
        ->and(LinkReportMetrics::daily($period, $this->page->id)['2026-11-12'])->toBe(['views' => 100, 'clicks' => 40]);

    expect(LinkReportMetrics::byItem($period, $this->page->id)->all())->toBe([
        ['page' => 'Bio Utama', 'label' => 'Konsultasi via WhatsApp', 'clicks' => 30, 'share' => '75,0%', 'ctr' => '30,0%'],
        ['page' => 'Bio Utama', 'label' => 'Katalog pagar', 'clicks' => 10, 'share' => '25,0%', 'ctr' => '10,0%'],
    ]);

    expect(LinkReportMetrics::bySource($period, $this->page->id)->all())->toBe([
        ['source' => 'Instagram', 'views' => 80, 'clicks' => 30, 'ctr' => '37,5%', 'share' => '80,0%'],
        ['source' => 'TikTok', 'views' => 20, 'clicks' => 10, 'ctr' => '50,0%', 'share' => '20,0%'],
    ]);
});

it('opens the report and link bio menu for editors only', function () {
    linkStat($this->page, $this->wa, 3);

    $this->actingAs(User::factory()->create(['role' => 'author', 'is_active' => true]));
    $this->get(LinkReport::getUrl())->assertForbidden();
    $this->get(LinkPageResource::getUrl())->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]));
    $this->get(LinkPageResource::getUrl())->assertOk()->assertSee('Bio Utama');
    $this->get(LinkReport::getUrl())->assertOk()
        ->assertSee('Tayangan halaman')->assertSee('Performa per tombol')->assertSee('Rincian sumber kunjungan')->assertSee('Dari mana pengunjung datang')->assertSee('Sumber teratas')->assertSee('Konsultasi via WhatsApp');
});

it('exports per-button clicks to CSV', function () {
    linkStat($this->page, $this->wa, 3);
    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]));

    Livewire::test(LinkItemsTable::class, ['pageFilters' => ['period' => '7d']])
        ->callTableAction('export')
        ->assertFileDownloaded('link-bio-tombol-20261107-20261113.csv');
});

it('creates a page with buttons and rejects unsafe or off-platform links', function () {
    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]));

    Livewire::test(CreateLinkPage::class)
        ->fillForm([
            'title' => 'Jaya Intero', 'slug' => 'jaya-intero', 'theme' => 'light', 'is_published' => true,
            'socials' => [['platform' => 'instagram', 'url' => 'https://evil.example.com/jayaintero']],
            'items' => [['type' => 'link', 'label' => 'Buruk', 'link_type' => 'external', 'url' => 'javascript:alert(1)', 'is_active' => true]],
        ])
        ->call('create')
        ->assertHasFormErrors(['socials.0.url', 'items.0.url']);

    Livewire::test(CreateLinkPage::class)
        ->fillForm([
            'title' => 'Jaya Intero', 'slug' => 'jaya-intero', 'theme' => 'light', 'is_published' => true,
            'socials' => [['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@jayaintero']],
            'items' => [
                ['type' => 'link', 'label' => 'Katalog', 'link_type' => 'external', 'url' => 'https://mitra.example.com', 'is_active' => true],
                ['type' => 'whatsapp', 'label' => 'Chat', 'whatsapp_number' => '081234567890', 'is_active' => true],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = LinkPage::where('slug', 'jaya-intero')->sole();
    expect($page->items->pluck('label')->all())->toBe(['Katalog', 'Chat'])
        ->and($page->validSocials())->toHaveCount(1);
});
