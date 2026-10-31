<?php

use App\Filament\Resources\Ads\Pages\EditAd;
use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Media\Pages\ManageMedia;
use App\Filament\Support\MediaPicker;
use App\Models\Ad;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Catalog\ProductImporter;
use App\Support\MediaLibrary;
use App\Support\MediaUsage;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Livewire\Features\SupportTesting\Testable;
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

it('fills the empty alt text field from the picked library image without overwriting typed text', function () {
    $media = libraryImage('kampanye/promo-semen.png', UploadedFile::fake()->image('promo.png', 728, 250));
    $media->update(['alt_text' => 'Promo Semen Gresik 40 kg']);
    $ad = fn (?string $alt) => Ad::create(['name' => 'Promo', 'slot' => 'top', 'format' => 'image', 'target_url' => 'https://mitra.example.com', 'image_alt' => $alt, 'is_active' => true]);

    foreach ([[$ad(null), 'Promo Semen Gresik 40 kg'], [$ad('Tulisan admin'), 'Tulisan admin']] as [$record, $expectedAlt]) {
        Livewire::test(EditAd::class, ['record' => $record->getRouteKey()])
            ->callAction(TestAction::make('pickFromMediaLibrary')->schemaComponent('image'), ['selected' => [$media->path]])
            ->assertFormSet(['image_alt' => $expectedAlt]);
    }
});

it('centers automatic crops on the focal point set in the media library', function () {
    // Kiri merah, kanan biru: potongan persegi dari gambar 2:1 hanya memuat salah satu sisi
    $canvas = imagecreatetruecolor(1600, 800);
    imagefilledrectangle($canvas, 0, 0, 799, 799, imagecolorallocate($canvas, 255, 0, 0));
    imagefilledrectangle($canvas, 800, 0, 1599, 799, imagecolorallocate($canvas, 0, 0, 255));
    ob_start();
    imagepng($canvas);
    Storage::disk('public')->put('products/dua-warna.png', ob_get_clean());
    $media = MediaLibrary::register('products/dua-warna.png');

    Livewire::test(ManageMedia::class)
        ->callTableAction('focus', $media, ['focal' => ['x' => 100, 'y' => 50]])
        ->assertHasNoTableActionErrors();

    $crop = MediaLibrary::cropToRatio($media->refresh(), 1, 400);
    $pixel = imagecolorsforindex($image = imagecreatefromwebp(Storage::disk('public')->path($crop->path)), imagecolorat($image, 200, 200));

    expect([$media->focal_x, $media->focal_y])->toBe([100, 50])
        ->and($crop->path)->toBe('products/dua-warna-400x400-f100-50.webp')
        ->and($pixel['blue'])->toBeGreaterThan(200)->and($pixel['red'])->toBeLessThan(50);
});

it('refuses to delete an image that content still uses and deletes unused ones', function () {
    $used = libraryImage('products/dipakai.png', UploadedFile::fake()->image('a.png'));
    $unused = libraryImage('products/nganggur.png', UploadedFile::fake()->image('b.png', 120, 100));
    Product::create(['name' => 'Semen', 'slug' => 'semen', 'image' => $used->path]);

    Livewire::test(ManageMedia::class)
        ->callTableAction('delete', $used)
        ->callTableAction('delete', $unused);

    Storage::disk('public')->assertExists($used->path);
    Storage::disk('public')->assertMissing($unused->path);
    expect(Media::pluck('path')->all())->toBe([$used->path])
        ->and(AuditLog::where('action', 'media.deleted')->count())->toBe(1);
});

it('deletes images marked in select mode, skipping the ones content still uses', function () {
    $used = libraryImage('articles/dipakai.png', UploadedFile::fake()->image('a.png'));
    $unused = libraryImage('articles/nganggur.png', UploadedFile::fake()->image('b.png', 120, 100));
    $other = libraryImage('articles/lain.png', UploadedFile::fake()->image('c.png', 130, 100));
    Setting::put('site_og_image', $used->path);

    // Penandaan kartu terjadi di browser; server menerima daftar ID bersama permintaan hapus
    Livewire::test(ManageMedia::class)
        ->callAction('select')
        ->assertSeeHtml('sm-select-bar')
        ->assertSeeHtml('data-media-id="'.$unused->id.'"')
        ->assertDontSeeHtml('fi-ta-record-checkbox')
        ->set('selectedMediaIds', [$used->id, $unused->id])
        ->callAction('deleteSelected')
        ->assertSet('isSelectingMedia', false)
        ->assertSet('selectedMediaIds', []);

    expect(Media::orderBy('id')->pluck('path')->all())->toBe([$used->path, $other->path]);
});

it('adds every unused image in the library to the selection in one step', function () {
    $used = libraryImage('articles/dipakai.png', UploadedFile::fake()->image('a.png'));
    $unused = libraryImage('articles/nganggur.png', UploadedFile::fake()->image('b.png', 120, 100));
    $alreadyPicked = libraryImage('articles/lain.png', UploadedFile::fake()->image('c.png', 130, 100));
    Setting::put('site_og_image', $used->path);

    Livewire::test(ManageMedia::class)
        ->callAction('select')
        ->set('selectedMediaIds', [$alreadyPicked->id])
        ->call('selectAllUnusedMedia')
        ->assertSet('selectedMediaIds', [$alreadyPicked->id, $unused->id]);
});

it('ignores select-all requests outside select mode or from authors', function () {
    libraryImage('articles/nganggur.png', UploadedFile::fake()->image('b.png'));

    Livewire::test(ManageMedia::class)->call('selectAllUnusedMedia')->assertSet('selectedMediaIds', []);

    $this->actingAs(User::factory()->create(['role' => 'author', 'is_active' => true]));
    Livewire::test(ManageMedia::class)->set('isSelectingMedia', true)->call('selectAllUnusedMedia')->assertSet('selectedMediaIds', []);
});

it('warns instead of deleting when nothing is selected', function () {
    libraryImage('articles/foto.png', UploadedFile::fake()->image('a.png'));

    Livewire::test(ManageMedia::class)
        ->callAction('select')
        ->callAction('deleteSelected')
        ->assertNotified('Belum ada gambar yang dipilih')
        ->assertSet('isSelectingMedia', true);

    expect(Media::count())->toBe(1);
});

it('filters the media library to images no content uses', function () {
    $used = libraryImage('products/dipakai.png', UploadedFile::fake()->image('a.png'));
    $unused = libraryImage('products/nganggur.png', UploadedFile::fake()->image('b.png', 120, 100));
    Product::create(['name' => 'Semen', 'slug' => 'semen', 'gallery' => [$used->path]]);

    Livewire::test(ManageMedia::class)
        ->filterTable('usage', 'unused')
        ->assertCanSeeTableRecords([$unused])
        ->assertCanNotSeeTableRecords([$used]);
});

it('hides deleting images uploaded by someone else from authors', function () {
    $media = libraryImage('products/foto.png', UploadedFile::fake()->image('a.png'));
    $this->actingAs(User::factory()->create(['role' => 'author', 'is_active' => true]));

    Livewire::test(ManageMedia::class)
        ->assertTableActionHidden('delete', $media)
        ->assertActionHidden('select');
});

it('lets contributors delete their own unused uploads but never anyone else\'s', function () {
    $others = libraryImage('products/milik-admin.png', UploadedFile::fake()->image('a.png'));
    $this->actingAs(User::factory()->create(['role' => 'contributor', 'is_active' => true]));
    $mistake = libraryImage('products/salah-unggah.png', UploadedFile::fake()->image('b.png', 120, 100));
    $inUse = libraryImage('products/dipakai.png', UploadedFile::fake()->image('c.png', 130, 100));
    Setting::put('site_og_image', $inUse->path);

    Livewire::test(ManageMedia::class)
        ->assertTableActionVisible('delete', $mistake)
        ->assertTableActionHidden('delete', $others)
        ->callTableAction('delete', $mistake)
        ->callTableAction('delete', $inUse);

    Storage::disk('public')->assertMissing($mistake->path);
    expect(Media::orderBy('id')->pluck('path')->all())->toBe([$others->path, $inUse->path]);
});

it('shows contributors only their own uploads in select mode and skips other people\'s images', function () {
    $others = libraryImage('articles/milik-admin.png', UploadedFile::fake()->image('a.png'));
    $this->actingAs(User::factory()->create(['role' => 'contributor', 'is_active' => true]));
    $own = libraryImage('articles/punya-saya.png', UploadedFile::fake()->image('b.png', 120, 100));

    Livewire::test(ManageMedia::class)
        ->callAction('select')
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$others])
        ->call('selectAllUnusedMedia')
        ->assertSet('selectedMediaIds', [$own->id])
        // ID milik orang lain yang diselipkan lewat browser tetap ditolak server
        ->set('selectedMediaIds', [$own->id, $others->id])
        ->callAction('deleteSelected')
        ->assertNotified('1 gambar dihapus');

    expect(Media::pluck('path')->all())->toBe([$others->path]);
});

/** Tulis CSV impor produk sementara dan jalankan importer yang sama dengan tombol "Impor CSV". */
function importProducts(string $csv): array
{
    $path = tempnam(sys_get_temp_dir(), 'impor').'.csv';
    file_put_contents($path, $csv);

    return ProductImporter::import($path);
}

it('attaches library images listed in the gambar column of a product import', function () {
    libraryImage('media/semen-depan.png', UploadedFile::fake()->image('a.png', 500, 500));
    libraryImage('media/semen-samping.png', UploadedFile::fake()->image('b.png', 800, 400));

    $result = importProducts("nama,slug,gambar\nSemen Gresik,semen-gresik,\"semen-depan.png, media/semen-samping.png\"\n");
    $product = Product::firstWhere('slug', 'semen-gresik');

    // Gambar 2:1 dipotong ke 1:1 (salinan baru); urutan kolom = urutan galeri
    expect($result['failed'])->toBe([])
        ->and($product->media()->pluck('path')->all())->toBe(['media/semen-depan.png', 'media/semen-samping-400x400.webp'])
        ->and($product->image)->toBe('media/semen-depan.png');

    importProducts("nama,slug,gambar\nSemen Gresik Baru,semen-gresik,\n");

    expect($product->media()->count())->toBe(2);
});

it('skips import rows whose gambar column names images missing from the library', function () {
    $result = importProducts("nama,slug,gambar\nSemen Gresik,semen-gresik,tidak-ada.jpg\n");

    expect(Product::count())->toBe(0)
        ->and($result['failed'][0]['errors']['gambar'][0])->toContain('tidak-ada.jpg');
});

it('warns when a picked image is too small for the section width', function () {
    $small = libraryImage('products/kecil.png', UploadedFile::fake()->image('kecil.png', 200, 200));
    $category = Category::create(['name' => 'Semen', 'slug' => 'semen', 'is_active' => true]);

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->callAction(TestAction::make('pickFromMediaLibrary')->schemaComponent('image'), ['selected' => [$small->path]])
        ->assertNotified('Resolusi gambar rendah untuk section ini');
});

it('loads more images in the picker beyond the first page', function () {
    foreach (range(1, MediaPicker::LIMIT + 1) as $i) {
        Media::factory()->create(['path' => "products/foto-{$i}.jpg", 'name' => "foto-{$i}.jpg"]);
    }
    $category = Category::create(['name' => 'Semen', 'slug' => 'semen', 'is_active' => true]);
    view()->share('errors', new ViewErrorBag);
    $grid = fn (Testable $page) => (fn ($livewire) => $livewire->getSchema($livewire->getMountedActionSchemaName())->toHtml())($page->instance());

    $page = Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->mountAction(TestAction::make('pickFromMediaLibrary')->schemaComponent('image'));

    expect(substr_count($grid($page), 'class="sm-media-item'))->toBe(MediaPicker::LIMIT)
        ->and($grid($page))->toContain('Muat lebih banyak');

    $page->set('mountedActions.0.data.limit', MediaPicker::LIMIT * 2);

    expect(substr_count($grid($page), 'class="sm-media-item'))->toBe(MediaPicker::LIMIT + 1)
        ->and($grid($page))->not->toContain('Muat lebih banyak');
});

it('replaces an image everywhere it is used, including JSON and article HTML', function () {
    $old = libraryImage('products/lama.png', UploadedFile::fake()->image('lama.png', 400, 400));
    $new = libraryImage('products/baru.png', UploadedFile::fake()->image('baru.png', 500, 500));
    $product = Product::create(['name' => 'Semen', 'slug' => 'semen', 'image' => $old->path, 'gallery' => [$old->path]]);
    $product->media()->create(['type' => 'image', 'provider' => 'upload', 'path' => $old->path, 'alt_text' => 'Semen']);
    $article = Article::create(['title' => 'Artikel', 'slug' => 'artikel', 'content' => '<p><img src="/storage/products/lama.png" alt="x"></p>']);
    Setting::put('site_og_image', $old->path);

    Livewire::test(ManageMedia::class)
        ->callTableAction('replace', $old, ['replacement' => $new->id, 'match_ratio' => true])
        ->assertHasNoTableActionErrors();

    expect($product->refresh()->only(['image', 'gallery']))->toBe(['image' => $new->path, 'gallery' => [$new->path]])
        ->and($product->media()->first()->only(['path', 'alt_text']))->toBe(['path' => $new->path, 'alt_text' => 'Semen'])
        ->and($article->refresh()->content)->toContain('/storage/products/baru.png')
        ->and(Setting::get('site_og_image'))->toBe($new->path)
        ->and(MediaUsage::for($old))->toBe([])
        ->and(AuditLog::where('action', 'media.replaced')->value('changes'))->toMatchArray(['updated' => 4]);
});

it('crops the replacement to the ratio of the image it replaces', function () {
    $old = libraryImage('products/persegi.png', UploadedFile::fake()->image('a.png', 400, 400));
    $wide = libraryImage('articles/lebar.png', UploadedFile::fake()->image('b.png', 1600, 900));
    $product = Product::create(['name' => 'Semen', 'slug' => 'semen', 'image' => $old->path]);

    Livewire::test(ManageMedia::class)->callTableAction('replace', $old, ['replacement' => $wide->id, 'match_ratio' => true]);

    expect($product->refresh()->image)->toBe('articles/lebar-900x900.webp');
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
