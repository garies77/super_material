<?php

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\User;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
});

function productPhoto(string $path, ?string $alt = null): string
{
    Storage::disk('public')->put($path, UploadedFile::fake()->image('foto.png', 400, 400)->getContent());
    MediaLibrary::register($path)?->update(['alt_text' => $alt]);

    return $path;
}

/** Skor yang tampil di panel "Skor SEO" form. */
function seoPanelScore(Testable $page): int
{
    preg_match('/sm-seo-score[^>]*>\s*(\d+)%/', $page->html(), $match);

    return (int) $match[1];
}

it('loads legacy image and gallery into one photo gallery and saves it to product media in order', function () {
    $product = Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik',
        'image' => productPhoto('products/utama.png'), 'gallery' => [productPhoto('products/samping.png', 'Tampak samping')]]);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertFormSet(['photos' => ['products/utama.png', 'products/samping.png']])
        ->call('save')
        ->assertHasNoFormErrors();

    $product->refresh();

    expect($product->media()->pluck('path')->all())->toBe(['products/utama.png', 'products/samping.png'])
        ->and($product->media()->pluck('alt_text')->all())->toBe(['Semen Gresik', 'Tampak samping'])
        ->and($product->image)->toBe('products/utama.png')
        ->and($product->gallery)->toBeNull();
});

it('reorders and removes photos while keeping alt text and putting videos after photos', function () {
    $product = Product::create(['name' => 'Keramik Roman', 'slug' => 'keramik-roman']);
    $product->media()->createMany([
        ['type' => 'image', 'provider' => 'upload', 'path' => productPhoto('products/a.png'), 'alt_text' => 'Foto A', 'sort_order' => 0],
        ['type' => 'image', 'provider' => 'upload', 'path' => productPhoto('products/b.png'), 'alt_text' => 'Foto B', 'sort_order' => 1],
        ['type' => 'video', 'provider' => 'youtube', 'path' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'alt_text' => 'Video', 'sort_order' => 2],
        ['type' => 'image', 'provider' => 'upload', 'path' => productPhoto('products/c.png'), 'alt_text' => 'Foto C', 'sort_order' => 3],
    ]);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['photos' => ['products/c.png', 'products/a.png']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->media()->get()->map(fn ($m) => [$m->type, $m->path, $m->alt_text])->all())->toBe([
        ['image', 'products/c.png', 'Foto C'],
        ['image', 'products/a.png', 'Foto A'],
        ['video', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'Video'],
    ])->and($product->refresh()->image)->toBe('products/c.png');
});

it('creates a product with uploaded photos and a YouTube video', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'Bata Ringan Citicon',
            'slug' => 'bata-ringan-citicon',
            'photos' => [UploadedFile::fake()->image('bata.jpg', 600, 600)],
            'videos' => [['path' => 'https://youtu.be/dQw4w9WgXcQ', 'alt_text' => 'Cara pasang']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::firstWhere('slug', 'bata-ringan-citicon');
    $photo = $product->media()->where('type', 'image')->first();

    expect($photo->path)->toStartWith('products/bata-ringan-citicon')
        ->and($photo->alt_text)->toBe('Bata Ringan Citicon')
        ->and(Media::where('path', $photo->path)->exists())->toBeTrue()
        ->and($product->media()->where('type', 'video')->first()->only(['provider', 'sort_order']))->toBe(['provider' => 'youtube', 'sort_order' => 1]);
});

it('updates the product SEO score panel from unsaved form input', function () {
    $product = Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik']);
    $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
    $before = seoPanelScore($page);

    $page->fillForm(['availability' => 'InStock']);

    expect(seoPanelScore($page))->toBeGreaterThan($before)
        ->and($product->refresh()->availability)->toBeNull();
});

it('updates the article SEO score panel from unsaved form input', function () {
    $article = Article::create(['title' => 'Cara memilih semen', 'slug' => 'cara-memilih-semen', 'content' => '<p>Isi</p>']);
    $page = Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()]);
    $before = seoPanelScore($page);

    $page->fillForm(['image_caption' => 'Tumpukan semen di gudang']);

    expect(seoPanelScore($page))->toBeGreaterThan($before);
});

it('duplicates a product with its details as an unpublished draft and opens the copy', function () {
    $channel = SalesChannel::create(['name' => 'Shopee', 'slug' => 'shopee', 'type' => 'marketplace', 'allowed_hosts' => ['shopee.co.id'], 'is_active' => true]);
    $product = Product::create(['name' => 'Semen 40 kg', 'slug' => 'semen-40-kg', 'sku' => 'STR-40', 'is_published' => true,
        'specifications' => ['Berat' => '40 kg'], 'rating_value' => 4.9, 'rating_count' => 12]);
    $product->media()->create(['type' => 'image', 'provider' => 'upload', 'path' => productPhoto('products/semen.png'), 'alt_text' => 'Semen', 'sort_order' => 0]);
    $product->variants()->create(['name' => 'Sak 40 kg', 'slug' => 'sak-40-kg', 'sku' => 'STR-40-SAK', 'price' => 52000]);
    $product->links()->create(['sales_channel_id' => $channel->id, 'url' => 'https://shopee.co.id/semen', 'is_active' => true]);
    $product->faqs()->create(['question' => 'Isi berapa?', 'answer' => '40 kg']);

    Livewire::test(ListProducts::class)->callTableAction('duplicate', $product);
    $copy = Product::firstWhere('slug', 'semen-40-kg-salinan');

    expect($copy->only(['name', 'sku', 'is_published', 'rating_value', 'specifications']))
        ->toBe(['name' => 'Semen 40 kg (salinan)', 'sku' => null, 'is_published' => false, 'rating_value' => null, 'specifications' => ['Berat' => '40 kg']])
        ->and($copy->media()->pluck('path')->all())->toBe(['products/semen.png'])
        ->and($copy->variants()->first()->only(['name', 'sku', 'price']))->toBe(['name' => 'Sak 40 kg', 'sku' => null, 'price' => 52000])
        ->and($copy->links()->pluck('url')->all())->toBe(['https://shopee.co.id/semen'])
        ->and($copy->faqs()->pluck('question')->all())->toBe(['Isi berapa?'])
        ->and($product->refresh()->is_published)->toBeTrue()
        ->and(AuditLog::where('action', 'product.duplicated')->count())->toBe(1);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->callAction('duplicate')
        ->assertRedirect(route('filament.admin.resources.products.edit', Product::firstWhere('slug', 'semen-40-kg-salinan-2')));
});
