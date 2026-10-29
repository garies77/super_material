<?php

use App\Filament\Pages\CatalogHealthReport;
use App\Filament\Pages\TeamReport;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\CatalogSnapshot;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\XpEvent;
use App\Support\Reports\CatalogHealth;
use App\Support\Reports\ReportPeriod;
use App\Support\Reports\TeamMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-10-20 12:00', 'Asia/Jakarta')));

afterEach(fn () => Carbon::setTestNow());

/** Catat langkah alur tinjauan pada waktu WIB tertentu. */
function reviewLog(string $action, User $actor, Product $product, string $at): AuditLog
{
    return AuditLog::create([
        'user_id' => $actor->id,
        'source' => 'admin',
        'action' => $action,
        'subject_type' => $product->getMorphClass(),
        'subject_id' => $product->id,
        'created_at' => Carbon::parse($at, 'Asia/Jakarta')->utc(),
    ]);
}

function approvalXp(User $contributor, Product $product, int $score, bool $firstTimeRight, string $at): XpEvent
{
    return XpEvent::create([
        'user_id' => $contributor->id,
        'points' => 50,
        'reason' => 'Produk disetujui',
        'subject_type' => $product->getMorphClass(),
        'subject_id' => $product->id,
        'award_key' => 'approval:'.$product->getMorphClass().':'.$product->id,
        'meta' => ['score' => $score, 'first_time_right' => $firstTimeRight],
        'created_at' => Carbon::parse($at, 'Asia/Jakarta')->utc(),
    ]);
}

it('summarises submissions, quality and review waiting time per contributor and reviewer', function () {
    $ani = User::factory()->create(['name' => 'Ani', 'role' => 'contributor']);
    $budi = User::factory()->create(['name' => 'Budi', 'role' => 'contributor']);
    $editor = User::factory()->create(['name' => 'Editor', 'role' => 'editor']);
    [$semen, $bata, $cat] = collect(['Semen', 'Bata', 'Cat'])->map(fn (string $name) => Product::create(['name' => $name, 'slug' => strtolower($name)]));

    // Semen: langsung disetujui setelah 2 jam
    reviewLog(TeamMetrics::SUBMITTED, $ani, $semen, '2026-10-18 08:00');
    reviewLog(TeamMetrics::APPROVED, $editor, $semen, '2026-10-18 10:00');
    approvalXp($ani, $semen, 100, true, '2026-10-18 10:00');
    // Bata: diminta perbaikan setelah 6 jam, diajukan ulang, disetujui 4 jam kemudian
    reviewLog(TeamMetrics::SUBMITTED, $ani, $bata, '2026-10-18 09:00');
    reviewLog(TeamMetrics::CHANGES_REQUESTED, $editor, $bata, '2026-10-18 15:00');
    reviewLog(TeamMetrics::SUBMITTED, $ani, $bata, '2026-10-19 08:00');
    reviewLog(TeamMetrics::APPROVED, $editor, $bata, '2026-10-19 12:00');
    approvalXp($ani, $bata, 80, false, '2026-10-19 12:00');
    // Cat: masih menunggu; pengajuan lama di luar periode tidak dihitung
    reviewLog(TeamMetrics::SUBMITTED, $budi, $cat, '2026-10-20 07:00');
    reviewLog(TeamMetrics::SUBMITTED, $budi, $cat, '2026-10-01 07:00');

    $period = ReportPeriod::fromFilters(['period' => '7d']);

    expect(TeamMetrics::submissions($period))->toBe(4)
        ->and(TeamMetrics::reviewDecisions($period)->pluck('hours')->all())->toBe([2.0, 6.0, 4.0])
        ->and(TeamMetrics::medianHours(TeamMetrics::reviewDecisions($period)))->toBe(4.0)
        ->and(TeamMetrics::contributors($period)->values()->all())->toBe([
            ['user_id' => $ani->id, 'name' => 'Ani', 'submissions' => 3, 'approved' => 2, 'changes' => 1, 'score' => 90.0, 'first_time_right' => 50.0, 'xp' => 100],
            ['user_id' => $budi->id, 'name' => 'Budi', 'submissions' => 1, 'approved' => 0, 'changes' => 0, 'score' => null, 'first_time_right' => null, 'xp' => 0],
        ])
        ->and(TeamMetrics::reviewers($period)->values()->all())->toBe([
            ['name' => 'Editor', 'reviews' => 3, 'approved' => 2, 'changes' => 1, 'median_hours' => 4.0],
        ])
        ->and(TeamMetrics::formatHours(0.5))->toBe('30 menit')
        ->and(TeamMetrics::formatHours(36))->toBe('1,5 hari');
});

it('lists the current review queue oldest first', function () {
    $contributor = User::factory()->create(['name' => 'Ani', 'role' => 'contributor']);
    $newer = Product::create(['name' => 'Baru', 'slug' => 'baru', 'review_status' => 'pending', 'submitted_by' => $contributor->id, 'submitted_at' => now()->subHour()]);
    Article::create(['title' => 'Lama', 'slug' => 'lama', 'content' => '<p>x</p>', 'review_status' => 'pending', 'submitted_by' => $contributor->id, 'submitted_at' => now()->subDays(3)]);
    Product::create(['name' => 'Perbaikan', 'slug' => 'perbaikan', 'review_status' => 'changes_requested', 'submitted_at' => now()->subDays(5)]);

    expect(TeamMetrics::queue()->values()->map(fn (array $item) => [$item['type'], $item['title'], $item['submitter']])->all())->toBe([
        ['Artikel', 'Lama', 'Ani'],
        ['Produk', 'Baru', 'Ani'],
    ])->and(TeamMetrics::queue()->last()['id'])->toBe($newer->id);
});

it('finds missing product data and snapshots the catalog health with a weekly comparison', function () {
    $brand = Brand::create(['name' => 'Gresik', 'slug' => 'gresik']);
    $category = Category::create(['name' => 'Semen', 'slug' => 'semen']);
    $complete = Product::create([
        'name' => 'Lengkap', 'slug' => 'lengkap', 'is_published' => true, 'image' => 'products/semen-gresik.jpg', 'summary' => 'Ringkas',
        'category_id' => $category->id, 'brand_id' => $brand->id, 'sku' => 'SG-40', 'price_valid_until' => now()->addMonth(),
    ]);
    $bare = Product::create(['name' => 'Kosong', 'slug' => 'kosong', 'is_published' => true, 'price_valid_until' => now()->subDay()]);
    Product::create(['name' => 'Draf', 'slug' => 'draf', 'is_published' => false]);

    expect(CatalogHealth::issueCounts())->toBe(['no_photo' => 1, 'no_description' => 1, 'no_category' => 1, 'no_brand' => 1, 'no_sku' => 1, 'stale_price' => 1])
        ->and(CatalogHealth::productsWithIssues()->pluck('name')->all())->toBe(['Kosong'])
        ->and(CatalogHealth::issuesOf($complete))->toBe([])
        ->and(CatalogHealth::issuesOf($bare))->toBe(array_values(CatalogHealth::ISSUES))
        ->and(CatalogHealth::productsWithIssues()->tap(fn ($q) => CatalogHealth::applyIssue($q, 'no_sku'))->count())->toBe(1);

    CatalogSnapshot::create(['date' => '2026-10-12', 'metrics' => ['products' => 2, 'complete' => 0, 'issues' => [], 'product_seo' => ['avg' => 10.0, 'buckets' => []], 'article_seo' => ['avg' => 0.0, 'buckets' => []]]]);
    $this->artisan('reports:snapshot-catalog')->assertSuccessful();

    $metrics = CatalogHealth::latest()->metrics;

    expect(CatalogHealth::latest()->date->toDateString())->toBe('2026-10-20')
        ->and($metrics)->toMatchArray(['products' => 2, 'complete' => 1, 'articles' => 0])
        ->and(array_sum($metrics['product_seo']['buckets']))->toBe(2)
        ->and(CatalogHealth::weekAgo()->date->toDateString())->toBe('2026-10-12')
        ->and(CatalogHealth::weeklyTrend()->keys()->all())->toBe(['12 Okt', '19 Okt']);
});

it('opens the team and catalog reports for editors only', function () {
    Product::create(['name' => 'Kosong', 'slug' => 'kosong', 'is_published' => true]);

    $this->actingAs(User::factory()->create(['role' => 'contributor', 'is_active' => true]));
    $this->get(TeamReport::getUrl())->assertForbidden();
    $this->get(CatalogHealthReport::getUrl())->assertForbidden();

    $editor = User::factory()->create(['role' => 'editor', 'is_active' => true]);

    $this->actingAs($editor)->get(TeamReport::getUrl())->assertOk()
        ->assertSee('Kinerja tim')->assertSee('Kiriman masuk')->assertSee('Kinerja kontributor')->assertSee('Peninjau')->assertSee('Antrean tinjauan saat ini');

    expect(CatalogSnapshot::count())->toBe(0);
    $this->get(CatalogHealthReport::getUrl())->assertOk()
        ->assertSee('Kesehatan katalog')->assertSee('Produk lengkap')->assertSee('Sebaran skor SEO')->assertSee('Kekurangan data produk')
        ->assertSee('Tren mingguan')->assertSee('Produk yang perlu dilengkapi')->assertSee('Kosong');
    expect(CatalogSnapshot::count())->toBe(1);

    Livewire::test(CatalogHealthReport::class)->callAction('refresh')->assertNotified('Skor katalog diperbarui');
});
