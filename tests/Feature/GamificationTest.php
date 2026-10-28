<?php

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Support\ReviewWorkflow;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
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

it('shows completeness checks as missions with progress and links to the field to fix', function () {
    $product = Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik', 'created_by' => $this->contributor->id]);
    $this->actingAs($this->contributor);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertSee('Misi kelengkapan')
        ->assertSeeHtml('role="progressbar"')
        ->assertSee('misi selesai')
        ->assertSee('📦 Pilih status ketersediaan stok')
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
        ->assertSee('Papan misi')->assertSee('Semua misi beres!')->assertSee('Tambah produk');
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
