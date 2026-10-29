<?php

use App\Filament\Pages\ReportCenter;
use App\Models\ClickEvent;
use App\Models\PageViewStat;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\SearchStat;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\WeeklyReportDigest;
use App\Support\Reports\ReportSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Rabu; minggu lalu = Senin 12 – Minggu 18 Okt 2026
    Carbon::setTestNow(Carbon::parse('2026-10-21 12:00', 'Asia/Jakarta'));

    $this->product = Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik', 'is_published' => true]);
    $shopee = SalesChannel::create(['name' => 'Shopee', 'slug' => 'shopee', 'type' => 'marketplace', 'is_active' => true]);
    foreach (['2026-10-13 09:00', '2026-10-14 09:00', '2026-10-07 09:00'] as $at) {
        ClickEvent::create(['product_id' => $this->product->id, 'sales_channel_id' => $shopee->id, 'is_bot' => false, 'created_at' => Carbon::parse($at, 'Asia/Jakarta')->utc()]);
    }
    PageViewStat::insert(['date' => '2026-10-15', 'viewable_type' => 'product', 'viewable_id' => $this->product->id, 'views' => 40]);
    SearchStat::insert([
        ['date' => '2026-10-16', 'term' => 'semen', 'searches' => 5, 'results' => 1],
        ['date' => '2026-10-16', 'term' => 'gypsum', 'searches' => 3, 'results' => 0],
    ]);
});

afterEach(fn () => Carbon::setTestNow());

it('summarises last week from the same numbers as the report center', function () {
    $period = ReportSummary::lastWeek();
    $summary = ReportSummary::build($period);

    expect($summary['period'])->toMatchArray(['from' => '2026-10-12', 'to' => '2026-10-18'])
        ->and($summary['kpis']['clicks'])->toBe(['label' => 'Klik ke toko', 'value' => 2, 'previous' => 1, 'change' => 100.0])
        ->and($summary['kpis']['page_views']['value'])->toBe(40)
        ->and($summary['top_products'])->toBe([['name' => 'Semen Gresik', 'clicks' => 2, 'change' => 100.0]])
        ->and($summary['zero_result_searches'])->toBe([['term' => 'gypsum', 'searches' => 3]])
        ->and($summary['catalog'])->toMatchArray(['products' => 1, 'complete' => 0])
        ->and(ReportSummary::formatChange(null, 0, 3))->toBe('baru')
        ->and(ReportSummary::formatChange(-12.5))->toBe('-12,5%');
});

it('emails the weekly report only when enabled and to valid recipients', function () {
    Notification::fake();

    $this->artisan('reports:send-weekly')->expectsOutputToContain('nonaktif')->assertSuccessful();
    Notification::assertNothingSent();

    Setting::put(ReportSummary::ENABLED_SETTING, true);
    Setting::put(ReportSummary::RECIPIENTS_SETTING, json_encode(['bos@supermaterial.test', 'bukan-email', 'admin@supermaterial.test']));

    $this->artisan('reports:send-weekly')->assertSuccessful();

    Notification::assertSentOnDemandTimes(WeeklyReportDigest::class, 2);
    Notification::assertSentOnDemand(WeeklyReportDigest::class, function (WeeklyReportDigest $notification, array $channels, AnonymousNotifiable $notifiable) {
        $html = (string) $notification->toMail($notifiable)->render();

        return $notifiable->routes['mail'] === 'bos@supermaterial.test'
            && str_contains($notification->toMail($notifiable)->subject, '12 Okt 2026 – 18 Okt 2026')
            && str_contains($html, 'Semen Gresik') && str_contains($html, 'gypsum') && str_contains($html, 'Buka Pusat Laporan');
    });
});

it('lets editors manage recipients and send themselves a sample from the report center', function () {
    Notification::fake();
    $editor = User::factory()->create(['role' => 'editor', 'is_active' => true, 'email' => 'editor@supermaterial.test']);
    $this->actingAs($editor);

    Livewire::test(ReportCenter::class)
        ->callAction('weeklySettings', data: ['enabled' => true, 'recipients' => ['bos@supermaterial.test']])
        ->assertHasNoActionErrors()
        ->callAction('sendSample')
        ->assertNotified('Contoh laporan dikirim ke editor@supermaterial.test');

    expect(ReportSummary::isEnabled())->toBeTrue()
        ->and(ReportSummary::recipients())->toBe(['bos@supermaterial.test']);
    Notification::assertSentTo($editor, WeeklyReportDigest::class);

    Livewire::test(ReportCenter::class)
        ->callAction('weeklySettings', data: ['enabled' => true, 'recipients' => ['bukan-email']])
        ->assertHasActionErrors();
});

it('renders a printable summary for editors only', function () {
    $this->get(route('reports.print'))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'contributor', 'is_active' => true]))->get(route('reports.print'))->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true, 'name' => 'Rina']))
        ->get(route('reports.print', ['period' => 'custom', 'from' => '2026-10-12', 'until' => '2026-10-18']))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('Laporan Kinerja')->assertSee('12 Okt 2026 – 18 Okt 2026')->assertSee('Semen Gresik')
        ->assertSee('gypsum')->assertSee('Kesehatan katalog')->assertSee('oleh Rina');
});

it('exposes summary, product and traffic reports over the admin API', function () {
    $token = User::factory()->create(['role' => 'admin'])->createToken('nopa', ['reports:read'], now()->addDays(90))->plainTextToken;
    $range = 'filter[date_from]=2026-10-12&filter[date_to]=2026-10-18';

    $this->withToken($token)->getJson("/api/v1/admin/reports/summary?{$range}")->assertOk()
        ->assertJsonPath('data.kpis.clicks.value', 2)
        ->assertJsonPath('meta.previous_from', '2026-10-05');

    $this->withToken($token)->getJson("/api/v1/admin/reports/products?{$range}&limit=5")->assertOk()
        ->assertJsonPath('data.top_clicked.0.name', 'Semen Gresik')
        ->assertJsonPath('data.conversion.0.rate', 5);

    $this->withToken($token)->getJson("/api/v1/admin/reports/traffic?{$range}")->assertOk()
        ->assertJsonPath('data.daily_views.2026-10-15', 40)
        ->assertJsonPath('data.zero_result_searches.0.term', 'gypsum');

    $this->withToken($token)->getJson('/api/v1/admin/reports/products?limit=500')->assertStatus(422);
});
