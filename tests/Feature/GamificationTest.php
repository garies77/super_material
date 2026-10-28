<?php

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\XpEvents\Pages\ListXpEvents;
use App\Filament\Resources\XpEvents\XpEventResource;
use App\Filament\Support\ReviewWorkflow;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use App\Models\XpEvent;
use App\Support\Gamification;
use App\Support\MissionBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->contributor = User::factory()->create(['role' => 'contributor', 'is_active' => true]);
    $this->editor = User::factory()->create(['role' => 'editor', 'is_active' => true]);
});

it('lists open completeness checks first, linking each to the field to fix', function () {
    $product = Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik', 'summary' => 'Semen 40 kg.', 'created_by' => $this->contributor->id]);
    $this->actingAs($this->contributor);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertSee('Misi kelengkapan')
        ->assertSeeHtml('aria-label="Skor kelengkapan')
        ->assertSee('dari 8 terpenuhi')
        ->assertSeeInOrder(['Pilih status ketersediaan stok', 'Selesai ('])
        ->assertSeeHtml('form.availability');
});

it('builds the mission board from the contributor\'s own drafts only', function () {
    Product::create(['name' => 'Draf tanpa foto', 'slug' => 'draf-1', 'created_by' => $this->contributor->id]);
    $fix = Product::create(['name' => 'Perlu perbaikan', 'slug' => 'draf-2', 'created_by' => $this->contributor->id, 'review_status' => 'changes_requested']);
    Product::create(['name' => 'Draf orang lain', 'slug' => 'draf-3', 'created_by' => $this->editor->id]);
    Product::create(['name' => 'Sudah tayang', 'slug' => 'tayang', 'created_by' => $this->contributor->id, 'is_published' => true]);

    $missions = collect(MissionBoard::for($this->contributor))->keyBy('title');

    expect($missions['Produk perlu diperbaiki']['count'])->toBe(1)
        ->and($missions['Produk perlu diperbaiki']['url'])->toEndWith("/admin/products/{$fix->id}/edit")
        ->and($missions['Draf produk siap diajukan']['count'])->toBe(1)
        ->and($missions['Produk belum punya foto']['count'])->toBe(2);
});

it('gives editors catalog-wide missions and hides completed ones', function () {
    $brand = Brand::create(['name' => 'Holcim', 'slug' => 'holcim']);
    Product::create(['name' => 'Lengkap', 'slug' => 'lengkap', 'brand_id' => $brand->id, 'sku' => 'HLC-1', 'availability' => 'InStock', 'image' => 'products/a.jpg']);
    Product::create(['name' => 'Harga lama', 'slug' => 'harga-lama', 'brand_id' => $brand->id, 'sku' => 'HLC-2', 'availability' => 'InStock', 'image' => 'products/b.jpg', 'price_review_needed' => true]);

    $titles = collect(MissionBoard::for($this->editor))->pluck('count', 'title');

    expect($titles->all())->toBe(['Harga sudah kedaluwarsa' => 1]);
});

it('limits authors to missions on their own articles', function () {
    $author = User::factory()->create(['role' => 'author', 'is_active' => true]);
    Article::create(['title' => 'Milik penulis', 'slug' => 'milik-penulis', 'content' => '<p>x</p>', 'author_id' => $author->authorProfile()->id]);
    Article::create(['title' => 'Milik editor', 'slug' => 'milik-editor', 'content' => '<p>x</p>', 'author_id' => $this->editor->authorProfile()->id]);

    expect(collect(MissionBoard::for($author))->pluck('count', 'title')->all())->toBe(['Artikel tanpa kategori & tag' => 1]);
});

it('shows the mission board on the dashboard with an all-done state', function () {
    $this->actingAs($this->contributor)->get('/admin')->assertOk()
        ->assertSee('Papan misi')->assertSee('Semua misi beres')->assertSee('Tambah produk');
});

it('celebrates a complete submission once on the next page and only nudges an incomplete one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('products/semen-holcim.jpg', UploadedFile::fake()->image('semen.jpg', 900, 900)->getContent());
    $brand = Brand::create(['name' => 'Holcim', 'slug' => 'holcim']);
    $complete = Product::create(['name' => 'Semen Holcim', 'slug' => 'semen-holcim', 'created_by' => $this->contributor->id,
        'brand_id' => $brand->id, 'sku' => 'HLC-40', 'price_min' => 52000, 'price_valid_until' => now()->addMonth(),
        'availability' => 'InStock', 'summary' => 'Semen serbaguna 40 kg.']);
    $complete->media()->create(['type' => 'image', 'provider' => 'upload', 'path' => 'products/semen-holcim.jpg', 'alt_text' => 'Semen Holcim', 'sort_order' => 0]);
    $sparse = Product::create(['name' => 'Bata', 'slug' => 'bata', 'created_by' => $this->contributor->id]);
    $sparseScore = ReviewWorkflow::seoScore($sparse);
    $this->actingAs($this->contributor);

    expect(ReviewWorkflow::seoScore($complete))->toBeGreaterThanOrEqual(ReviewWorkflow::CELEBRATE_SCORE)
        ->and($sparseScore)->toBeLessThan(ReviewWorkflow::CELEBRATE_SCORE);

    Livewire::test(EditProduct::class, ['record' => $sparse->getRouteKey()])->callAction('submitForReview')
        ->assertNotified("Diajukan dengan skor {$sparseScore}%");
    expect(session()->has('sm_celebrate'))->toBeFalse();

    Livewire::test(EditProduct::class, ['record' => $complete->getRouteKey()])->callAction('submitForReview');
    expect(session()->has('sm_celebrate'))->toBeTrue();

    // Halaman berikutnya memutar confetti sekali, lalu tanda perayaan habis
    $this->get("/admin/products/{$complete->id}/edit")->assertOk()->assertSee('setTimeout(() => window.smConfetti(), 300)', false);
    $this->get("/admin/products/{$complete->id}/edit")->assertOk()->assertDontSee('setTimeout(() => window.smConfetti(), 300)', false);
});

/** Kontributor mengajukan draf lalu Editor menyetujuinya lewat tombol di halaman edit. */
function submitAndApprove(Product $product, User $contributor, User $editor): void
{
    test()->actingAs($contributor);
    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->callAction('submitForReview');
    test()->actingAs($editor);
    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->callAction('approve');
}

it('awards XP with a quality breakdown when a submission is approved', function () {
    $product = Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik', 'created_by' => $this->contributor->id]);
    $score = ReviewWorkflow::seoScore($product);

    submitAndApprove($product, $this->contributor, $this->editor);
    $event = XpEvent::sole();

    // Skor rendah: tanpa bonus kelengkapan, tetapi disetujui tanpa perbaikan
    expect($score)->toBeLessThan(Gamification::COMPLETE_SCORE)
        ->and($event->only(['user_id', 'points']))->toBe(['user_id' => $this->contributor->id, 'points' => Gamification::POINTS_APPROVED + Gamification::POINTS_FIRST_TIME_RIGHT])
        ->and($event->meta['first_time_right'])->toBeTrue()
        ->and(Gamification::totalXp($this->contributor))->toBe(50);
});

it('skips the first-time-right bonus after a change request and never awards the same content twice', function () {
    $product = Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik', 'created_by' => $this->contributor->id]);

    $this->actingAs($this->contributor);
    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->callAction('submitForReview');
    $this->actingAs($this->editor);
    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->callAction('requestChanges', ['note' => 'Tambah foto.']);
    submitAndApprove($product, $this->contributor, $this->editor);

    // Diturunkan lalu diajukan & disetujui lagi: tidak ada XP baru
    $product->refresh()->update(['is_published' => false, 'created_by' => $this->contributor->id]);
    submitAndApprove($product->refresh(), $this->contributor, $this->editor);

    expect(XpEvent::count())->toBe(1)
        ->and(XpEvent::sole()->points)->toBe(Gamification::POINTS_APPROVED);
});

it('computes levels and progress towards the next one', function (int $xp, string $name, ?string $next, int $progress) {
    expect(Gamification::level($xp))->toMatchArray(['name' => $name, 'next' => $next, 'progress' => $progress]);
})->with([
    'awal' => [0, 'Magang', 'Juru Katalog', 0],
    'tengah level' => [400, 'Juru Katalog', 'Ahli Konten', 50],
    'level tertinggi' => [2000, 'Master Supermaterial', null, 100],
]);

it('earns badges from approvals, ignoring revoked XP', function () {
    foreach (range(0, 4) as $day) {
        XpEvent::factory()->for($this->contributor)->create([
            'award_key' => "approval:product:{$day}", 'created_at' => now()->subDays($day),
            'meta' => ['score' => 100, 'first_time_right' => true],
        ]);
    }

    $earned = fn () => Gamification::badges($this->contributor)->where('earned', true)->pluck('key')->all();

    expect($earned())->toBe(['first_approval', 'consistent']);

    XpEvent::where('award_key', 'approval:product:2')->update(['revoked_at' => now()]);

    expect($earned())->toBe(['first_approval'])
        ->and(Gamification::totalXp($this->contributor))->toBe(160);
});

it('shows the XP card on the contributor dashboard', function () {
    XpEvent::factory()->for($this->contributor)->create(['points' => 250, 'reason' => 'Produk disetujui: Semen Gresik']);

    $this->actingAs($this->contributor)->get('/admin')->assertOk()
        ->assertSee('Juru Katalog')->assertSee('Produk disetujui: Semen Gresik')->assertSee('Langkah Pertama');
});

it('lets only admins review and revoke XP', function () {
    $event = XpEvent::factory()->for($this->contributor)->create();

    $this->actingAs($this->editor)->get(XpEventResource::getUrl())->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    Livewire::test(ListXpEvents::class)->callTableAction('revoke', $event);

    expect($event->refresh()->isRevoked())->toBeTrue()
        ->and(Gamification::totalXp($this->contributor))->toBe(0);

    Livewire::test(ListXpEvents::class)->callTableAction('restore', $event);
    expect(Gamification::totalXp($this->contributor))->toBe($event->points);
});
