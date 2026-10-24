<?php

use App\Filament\Pages\ManageHomepage;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\ClickEvent;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

function homeSection(string $key): HomeSection
{
    return HomeSection::where('key', $key)->firstOrFail();
}

function setSectionSettings(string $key, array $settings): void
{
    $section = homeSection($key);
    $section->update(['settings' => array_replace($section->settings ?? [], $settings)]);
}

/** Potongan HTML di antara dua penanda, untuk memeriksa isi satu seksi saja. */
function between(string $html, string $start, string $end): string
{
    $from = strpos($html, $start);

    return substr($html, $from, strpos($html, $end, $from) - $from);
}

it('seeds the five default sections in the original order', function () {
    expect(HomeSection::ordered()->pluck('key')->all())
        ->toBe(['hero', 'categories', 'products_featured', 'articles_recent', 'cta_large']);

    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('Temukan bahan bangunan')->toContain('tepat untuk proyek Anda')
        ->toContain('100% Produk Asli &amp; SNI')->toContain('Produk pilihan')->toContain('Panduan &amp; artikel')
        ->toContain('Butuh material dalam jumlah besar?')->toContain('https://wa.me/6281234567890')
        ->and(substr_count($html, '<h1'))->toBe(1);
});

it('renders sections in sort_order', function () {
    homeSection('articles_recent')->update(['sort_order' => 2]);
    homeSection('categories')->update(['sort_order' => 4]);

    $main = between($this->get('/')->getContent(), '</header>', '<footer');

    expect(strpos($main, 'Panduan &amp; artikel'))->toBeLessThan(strpos($main, 'Produk pilihan'))
        ->and(strpos($main, 'Produk pilihan'))->toBeLessThan(strpos($main, '/kategori/'));
});

it('hides inactive sections without errors and keeps an H1 when the hero is off', function () {
    homeSection('articles_recent')->update(['is_active' => false]);
    homeSection('categories')->update(['is_active' => false]);
    homeSection('hero')->update(['is_active' => false]);

    $html = $this->get('/')->assertOk()->getContent();
    $main = between($html, '</header>', '<footer');

    expect($main)->not->toContain('Panduan &amp; artikel')->not->toContain('/kategori/')
        ->not->toContain('Temukan bahan bangunan')
        ->and(substr_count($html, '<h1'))->toBe(1);
});

it('shows the category image on the homepage card, falling back to the initial', function () {
    $category = Category::active()->whereNull('parent_id')->has('products')->first();
    $category->update(['image' => 'categories/semen.webp']);

    $main = between($this->get('/')->getContent(), '</header>', '<footer');

    expect($main)->toContain('storage/categories/semen.webp')
        ->and(substr_count($main, 'rounded-xl bg-brand-50 font-display'))->toBeGreaterThan(0); // kategori lain tetap huruf awal
});

it('counts published products in subcategories, matching the category page', function () {
    $parent = Category::create(['name' => 'Induk Uji', 'slug' => 'induk-uji', 'is_active' => true, 'sort_order' => 0]);
    $child = Category::create(['name' => 'Anak Uji', 'slug' => 'anak-uji', 'parent_id' => $parent->id, 'is_active' => true]);
    $grandchild = Category::create(['name' => 'Cucu Uji', 'slug' => 'cucu-uji', 'parent_id' => $child->id, 'is_active' => true]);
    foreach ([[$child, true], [$child, true], [$grandchild, true], [$child, false]] as $i => [$category, $published]) {
        Product::create(['name' => "Produk Turunan {$i}", 'slug' => "produk-turunan-{$i}", 'category_id' => $category->id, 'is_published' => $published]);
    }

    $card = between(between($this->get('/')->getContent(), '</header>', '<footer'), 'Induk Uji', '</a>');

    // 3 terbit di subkategori (draf tidak dihitung), bukan 0 dari produk yang menempel langsung
    expect($card)->toContain('3 produk');
    $this->get('/kategori/induk-uji')->assertOk()->assertSee('3</span> produk ditemukan', false);
});

it('renders custom hero text and trust badges', function () {
    setSectionSettings('hero', [
        'heading' => 'Bangun rumah impian',
        'heading_accent' => 'tanpa ribet',
        'trust_badges' => [['text' => 'Gratis ongkir Medan'], ['text' => 'Garansi resmi']],
        'show_search' => false,
    ]);

    $html = $this->get('/')->getContent();
    $h1 = between($html, '<h1', '</h1>');

    expect($h1)->toContain('Bangun rumah impian')->toContain('tanpa ribet')
        ->and($html)->toContain('Gratis ongkir Medan')->toContain('Garansi resmi')
        ->not->toContain('100% Produk Asli')->not->toContain('id="q-hero"');
});

it('reads trust badges saved in the old nested shape', function () {
    setSectionSettings('hero', ['trust_badges' => [['text' => ['text' => 'Badge Bersarang']], 'Badge Polos']]);

    $this->get('/')->assertOk()->assertSee('Badge Bersarang')->assertSee('Badge Polos');
});

it('shows the most clicked products first in popular mode', function () {
    [$top, $second] = Product::published()->orderByDesc('id')->take(2)->get()->all();
    $top->update(['name' => 'PRODUK-TERLARIS']);
    $second->update(['name' => 'PRODUK-KEDUA']);
    foreach ([[$top, 5], [$second, 2]] as [$product, $clicks]) {
        for ($i = 0; $i < $clicks; $i++) {
            ClickEvent::create(['product_id' => $product->id, 'is_bot' => false, 'created_at' => now()]);
        }
    }
    // Klik bot tidak dihitung
    for ($i = 0; $i < 10; $i++) {
        ClickEvent::create(['product_id' => $second->id, 'is_bot' => true, 'created_at' => now()]);
    }
    setSectionSettings('products_featured', ['selection_mode' => 'popular', 'limit' => 4]);

    $section = between($this->get('/')->getContent(), 'Produk pilihan', '</section>');

    expect(strpos($section, 'PRODUK-TERLARIS'))->toBeLessThan(strpos($section, 'PRODUK-KEDUA'));
});

it('shows only the picked products, in the picked order, in manual mode', function () {
    [$a, $b] = Product::published()->take(2)->get()->all();
    $a->update(['name' => 'PILIHAN-A']);
    $b->update(['name' => 'PILIHAN-B']);
    setSectionSettings('products_featured', ['selection_mode' => 'manual', 'product_ids' => [(string) $b->id, (string) $a->id]]);

    $section = between($this->get('/')->getContent(), 'Produk pilihan', '</section>');

    expect(substr_count($section, '/produk/'))->toBe(2)
        ->and(strpos($section, 'PILIHAN-B'))->toBeLessThan(strpos($section, 'PILIHAN-A'));
});

it('supports more than one product block', function () {
    $product = Product::published()->first();
    $product->update(['name' => 'BLOK-KEDUA-PRODUK']);
    HomeSection::create(['key' => 'products_extra', 'type' => 'products', 'title' => 'Diskon Mingguan', 'sort_order' => 6,
        'settings' => ['selection_mode' => 'manual', 'product_ids' => [$product->id], 'limit' => 4]]);

    $section = between($this->get('/')->getContent(), 'Diskon Mingguan', '</section>');

    expect($section)->toContain('BLOK-KEDUA-PRODUK')->and(substr_count($section, '/produk/'))->toBe(1);
});

it('sorts articles by views in popular mode and by publish date in latest mode', function () {
    $articles = Article::published()->orderBy('published_at')->take(3)->get();
    $articles[0]->update(['title' => 'ARTIKEL-TERPOPULER', 'views_count' => 9000, 'published_at' => now()->subYear()]);
    $articles[1]->update(['title' => 'ARTIKEL-TERBARU', 'views_count' => 0, 'published_at' => now()->subMinute()]);

    setSectionSettings('articles_recent', ['sort_by' => 'popular']);
    $section = between($this->get('/')->getContent(), 'Panduan &amp; artikel', '</section>');
    expect(strpos($section, 'ARTIKEL-TERPOPULER'))->toBeLessThan(strpos($section, 'ARTIKEL-TERBARU') ?: PHP_INT_MAX);

    setSectionSettings('articles_recent', ['sort_by' => 'latest']);
    $section = between($this->get('/')->getContent(), 'Panduan &amp; artikel', '</section>');
    expect(strpos($section, 'ARTIKEL-TERBARU'))->toBeLessThan(strpos($section, 'ARTIKEL-TERPOPULER') ?: PHP_INT_MAX);
});

it('uses a custom WhatsApp number in the CTA block', function () {
    setSectionSettings('cta_large', ['whatsapp_number' => '0811-2223-334', 'button_text' => 'Tanya Sekarang']);

    $this->get('/')->assertSee('https://wa.me/628112223334', false)->assertSee('Tanya Sekarang');
});

it('lets an admin reorder, hide, and edit sections from the dashboard', function () {
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());

    $page = Livewire::test(ManageHomepage::class)->assertOk();
    $sections = $page->get('data.sections');
    $uuids = array_keys($sections);
    $find = fn (string $key) => collect($sections)->search(fn ($s) => $s['data']['key'] === $key);

    // Artikel pindah ke atas produk, kategori disembunyikan, judul hero diganti, banner kampanye dimatikan
    $sections[$find('categories')]['data']['is_active'] = false;
    $sections[$find('hero')]['data']['settings']['heading'] = 'Judul Dari Dashboard';
    $sections[$find('hero')]['data']['banner']['active'] = false;
    $order = [$find('hero'), $find('categories'), $find('articles_recent'), $find('products_featured'), $find('cta_large')];
    $reordered = array_combine($order, array_map(fn ($u) => $sections[$u], $order));
    expect(count($reordered))->toBe(count($uuids));

    $page->set('data.sections', $reordered)->call('save')->assertHasNoFormErrors()->assertNotified('Beranda disimpan');

    expect(HomeSection::ordered()->pluck('key')->all())->toBe(['hero', 'categories', 'articles_recent', 'products_featured', 'cta_large'])
        ->and(homeSection('categories')->is_active)->toBeFalse()
        ->and(Setting::getBool('hero_banner_active', true))->toBeFalse()
        ->and(AuditLog::where('action', 'homepage.update')->exists())->toBeTrue();

    $html = $this->get('/')->getContent();
    expect($html)->toContain('Judul Dari Dashboard')
        ->and(strpos($html, 'Panduan &amp; artikel'))->toBeLessThan(strpos($html, 'Produk pilihan'));
});

it('keeps the homepage customizer admin-only', function () {
    $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'Rahasia#2026', 'role' => 'editor']);

    $this->actingAs($editor)->get(ManageHomepage::getUrl())->assertForbidden();
});
