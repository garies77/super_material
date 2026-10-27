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
