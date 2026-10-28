<?php

use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Media\Pages\ManageMedia;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\MediaLibrary;
use App\Support\MediaUsage;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
});

/** Simpan gambar asli ke disk publik palsu lalu daftarkan ke pustaka. */
function libraryImage(string $path, UploadedFile $image): Media
{
    Storage::disk('public')->put($path, $image->getContent());

    return MediaLibrary::register($path);
}

it('reuses the stored file when an identical image is uploaded through an admin form', function () {
    $image = UploadedFile::fake()->image('logo.png', 300, 300);
    $existing = libraryImage('brands/logo-lama.png', $image);

    Livewire::test(ManageBrands::class)
        ->callAction('create', ['name' => 'Holcim', 'slug' => 'holcim', 'logo' => $image])
        ->assertHasNoActionErrors();

    expect(Brand::firstWhere('slug', 'holcim')->logo)->toBe($existing->path)
        ->and(Storage::disk('public')->files('brands'))->toBe(['brands/logo-lama.png'])
        ->and(Media::count())->toBe(1);
});

it('stores and registers a new image uploaded through an admin form', function () {
    Livewire::test(ManageBrands::class)
        ->callAction('create', ['name' => 'Holcim', 'slug' => 'holcim', 'logo' => UploadedFile::fake()->image('baru.png', 300, 200)])
        ->assertHasNoActionErrors();

    $logo = Brand::firstWhere('slug', 'holcim')->logo;
    $media = Media::firstWhere('path', $logo);

    Storage::disk('public')->assertExists($logo);
    expect($media)->not->toBeNull()
        ->and($media->folder)->toBe('brands')
        ->and([$media->width, $media->height])->toBe([300, 200]);
});

it('fills an image field with the image picked from the library', function () {
    $media = libraryImage('products/semen-gresik.png', UploadedFile::fake()->image('semen.png', 400, 400));
    $category = Category::create(['name' => 'Semen', 'slug' => 'semen', 'is_active' => true]);

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->callAction(TestAction::make('pickFromMediaLibrary')->schemaComponent('image'), ['selected' => [$media->path]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($category->refresh()->image)->toBe('products/semen-gresik.png');
});

it('ignores picked paths that are not registered in the library', function () {
    Storage::disk('public')->put('private/rahasia.png', UploadedFile::fake()->image('x.png')->getContent());
    $category = Category::create(['name' => 'Semen', 'slug' => 'semen', 'is_active' => true, 'image' => null]);

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->callAction(TestAction::make('pickFromMediaLibrary')->schemaComponent('image'), ['selected' => ['private/rahasia.png']])
        ->call('save');

    expect($category->refresh()->image)->toBeNull();
});

it('registers existing images once and skips derivatives, temporary uploads, and non-images', function () {
    $disk = Storage::disk('public');
    $disk->put('articles/foto.jpg', UploadedFile::fake()->image('foto.jpg', 200, 200)->getContent());
    $disk->put('derivatives/abc_w320.webp', UploadedFile::fake()->image('d.webp')->getContent());
    $disk->put('livewire-tmp/sementara.png', UploadedFile::fake()->image('t.png')->getContent());
    $disk->put('pages/catatan.txt', 'bukan gambar');

    $this->artisan('media:sync')->assertSuccessful();
    $this->artisan('media:sync')->assertSuccessful();

    expect(Media::pluck('path')->all())->toBe(['articles/foto.jpg']);
});

it('lists the content that uses an image, including JSON-encoded paths', function () {
    $media = libraryImage('products/keramik.png', UploadedFile::fake()->image('k.png'));
    $product = Product::create(['name' => 'Keramik Roman', 'slug' => 'keramik-roman', 'gallery' => [$media->path]]);
    Setting::put('site_og_image', $media->path);
    Product::create(['name' => 'Keramik Lain', 'slug' => 'keramik-lain', 'image' => 'products/keramik-lain.png']);

    $usages = collect(MediaUsage::for($media));

    expect($usages->pluck('title')->all())->toEqualCanonicalizing([$product->name, 'site_og_image'])
        ->and($usages->pluck('type')->all())->toEqualCanonicalizing(['Produk', 'Pengaturan']);
});

it('creates a small thumbnail once when an image is registered', function () {
    $media = libraryImage('products/besar.jpg', UploadedFile::fake()->image('besar.jpg', 1600, 1200));

    // ImageDerivatives menulis turunan ke storage publik asli, bukan disk palsu
    $thumbnail = storage_path('app/public/'.$media->thumbnail_path);

    expect($media->thumbnail_path)->toStartWith('derivatives/')->toEndWith('_w320.webp')
        ->and(getimagesize($thumbnail)[0])->toBe(320)
        ->and($media->thumbnailUrl())->toBe(Storage::disk('public')->url($media->thumbnail_path));

    @unlink($thumbnail);
});

it('uses the embedded bitmap as thumbnail for an SVG export and keeps vector-only SVGs as they are', function () {
    $bitmap = base64_encode(UploadedFile::fake()->image('isi.png', 800, 450)->getContent());
    Storage::disk('public')->put('articles/banner.svg', '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="800" height="450"><image width="800" height="450" xlink:href="data:image/png;base64,'.$bitmap.'"/></svg>');
    Storage::disk('public')->put('logos/vektor.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96"><circle cx="48" cy="48" r="40"/></svg>');

    expect(MediaLibrary::register('articles/banner.svg')->thumbnail_path)->toEndWith('_w320.webp')
        ->and(MediaLibrary::register('logos/vektor.svg')->thumbnail_path)->toBe('logos/vektor.svg');
});

it('fills in missing thumbnails for media registered before thumbnails existed', function () {
    $media = libraryImage('articles/lama.jpg', UploadedFile::fake()->image('lama.jpg', 800, 600));
    $media->update(['thumbnail_path' => null]);

    $this->artisan('media:sync')->assertSuccessful();

    expect($media->refresh()->thumbnail_path)->toEndWith('_w320.webp');
});

it('filters the picker grid by search text', function () {
    libraryImage('products/semen-gresik.png', UploadedFile::fake()->image('a.png', 300, 300));
    libraryImage('products/keramik-roman.png', UploadedFile::fake()->image('b.png', 310, 300));
    $category = Category::create(['name' => 'Semen', 'slug' => 'semen', 'is_active' => true]);

    $page = Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->mountAction(TestAction::make('pickFromMediaLibrary')->schemaComponent('image'))
        ->set('mountedActions.0.data.search', 'keramik');

    view()->share('errors', new ViewErrorBag);
    $grid = (fn ($livewire) => $livewire->getSchema($livewire->getMountedActionSchemaName())->toHtml())($page->instance());

    expect($grid)->toContain('keramik-roman.png')->not->toContain('semen-gresik.png');
});

it('rejects a new upload whose aspect ratio does not match the section', function () {
    $category = Category::create(['name' => 'Semen', 'slug' => 'semen', 'is_active' => true]);

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm(['image' => UploadedFile::fake()->image('lebar.png', 600, 300)])
        ->call('save')
        ->assertHasFormErrors(['image']);

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm(['image' => UploadedFile::fake()->image('persegi.png', 400, 400)])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('fills a square section with a centered square copy when a wide library image is picked', function () {
    $wide = libraryImage('articles/banner-lebar.jpg', UploadedFile::fake()->image('lebar.jpg', 1600, 900));
    $wide->update(['alt_text' => 'Tumpukan semen']);
    $category = Category::create(['name' => 'Semen', 'slug' => 'semen', 'is_active' => true]);

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->callAction(TestAction::make('pickFromMediaLibrary')->schemaComponent('image'), ['selected' => [$wide->path]])
        ->call('save')
        ->assertHasNoFormErrors();

    $copy = Media::firstWhere('path', $category->refresh()->image);

    expect($category->image)->toBe('articles/banner-lebar-400x400.webp')
        ->and([$copy->width, $copy->height])->toBe([400, 400])
        ->and($copy->alt_text)->toBe('Tumpukan semen')
        ->and(Storage::disk('public')->exists($wide->path))->toBeTrue();
});

it('creates a cropped copy for a chosen section size from the media library page', function () {
    $media = libraryImage('products/foto.jpg', UploadedFile::fake()->image('foto.jpg', 2000, 2000));

    Livewire::test(ManageMedia::class)
        ->callTableAction('crop', $media, ['preset' => 'article'])
        ->assertHasNoTableActionErrors();

    $copy = Media::firstWhere('path', 'products/foto-1200x675.webp');

    expect($copy)->not->toBeNull()->and([$copy->width, $copy->height])->toBe([1200, 675]);
});

it('filters the media library by aspect ratio', function () {
    $square = libraryImage('products/persegi.png', UploadedFile::fake()->image('a.png', 500, 500));
    $wide = libraryImage('articles/lebar.png', UploadedFile::fake()->image('b.png', 1600, 900));

    Livewire::test(ManageMedia::class)
        ->filterTable('ratio', '16:9')
        ->assertCanSeeTableRecords([$wide])
        ->assertCanNotSeeTableRecords([$square]);
});

it('lets every active panel role open the media library', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));

    $this->get(MediaResource::getUrl())->assertOk();
})->with(['admin', 'editor', 'author']);

it('uploads several images at once from the media library page', function () {
    Livewire::test(ManageMedia::class)
        ->callAction('upload', ['files' => [
            UploadedFile::fake()->image('Semen Gresik 40kg.jpg', 300, 300),
            UploadedFile::fake()->image('bata-ringan.png', 300, 300),
        ]])
        ->assertHasNoActionErrors();

    expect(Media::orderBy('path')->pluck('path')->all())->toBe(['media/bata-ringan-1.png', 'media/semen-gresik-40kg-1.jpg']);
});
