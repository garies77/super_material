<?php

use App\Filament\Pages\Layout\FooterBuilder;
use App\Filament\Pages\Layout\NavbarBuilder;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\SiteLayout;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
});

it('keeps the previous navbar and footer until the admin changes them', function () {
    $this->get('/')->assertOk()
        ->assertSee('href="/produk"', false)->assertSee('href="/artikel"', false)
        ->assertSee('>Kategori<', false)->assertSee('>Informasi<', false);
});

it('applies the navbar built in the dashboard to the public site', function () {
    $page = Page::published()->first();

    Livewire::test(NavbarBuilder::class)
        ->fillForm(['items' => [
            ['label' => 'Beranda', 'link_type' => 'internal', 'url' => '/', 'is_active' => true, 'children' => []],
            ['label' => 'Layanan', 'link_type' => 'internal', 'url' => '/produk', 'is_active' => true, 'children' => [
                ['label' => 'Layanan A', 'link_type' => 'page', 'page_id' => $page->id, 'is_active' => true],
                ['label' => 'Toko Mitra', 'link_type' => 'external', 'url' => 'https://mitra.example.com', 'new_tab' => true, 'is_active' => true],
            ]],
            ['label' => 'Disembunyikan', 'link_type' => 'internal', 'url' => '/x', 'is_active' => false, 'children' => []],
        ]])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/artikel')->assertOk()
        ->assertSee('Beranda')->assertSee('Layanan A')
        ->assertSee('href="'.route('page.show', $page->slug, false).'"', false)
        ->assertSee('href="https://mitra.example.com" target="_blank" rel="noopener"', false)
        ->assertDontSee('Disembunyikan')
        ->assertSee('<summary', false); // menu lipat mobile saat ada submenu
});

it('rejects unsafe navbar links', function () {
    Livewire::test(NavbarBuilder::class)
        ->fillForm(['items' => [['label' => 'Jahat', 'link_type' => 'external', 'url' => 'javascript:alert(1)', 'is_active' => true, 'children' => []]]])
        ->call('save')
        ->assertHasFormErrors();
});

it('applies the footer built in the dashboard to the public site', function () {
    Livewire::test(FooterBuilder::class)
        ->fillForm([
            'show_logo' => true,
            'description' => 'Distributor bahan bangunan Medan.',
            'bottom_align' => 'left',
            'columns' => [
                ['title' => 'Layanan', 'type' => 'links', 'is_active' => true, 'links' => [
                    ['label' => 'Konsultasi', 'link_type' => 'internal', 'url' => '/kontak', 'is_active' => true],
                ]],
                ['title' => 'Hubungi Kami', 'type' => 'contact', 'is_active' => true, 'links' => []],
                ['title' => 'Kolom Mati', 'type' => 'text', 'text' => 'x', 'is_active' => false, 'links' => []],
            ],
            'address' => 'Jl. Gatot Subroto, Medan',
            'phone' => '0812-3456-7890',
            'email' => 'halo@supermaterial.test',
            'socials' => [['platform' => 'instagram', 'url' => 'https://instagram.com/supermaterial']],
            'copyright' => '© {year} {site}. Semua hak dilindungi.',
            'bottom_links' => [['label' => 'Kebijakan Privasi', 'link_type' => 'internal', 'url' => '/kebijakan-privasi', 'is_active' => true]],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/')->assertOk()
        ->assertSee('Distributor bahan bangunan Medan.')
        ->assertSee('>Layanan<', false)->assertSee('href="/kontak"', false)
        ->assertSee('Hubungi Kami')->assertSee('href="tel:081234567890"', false)->assertSee('mailto:halo@supermaterial.test', false)
        ->assertSee('href="https://instagram.com/supermaterial"', false)
        ->assertSee('© '.date('Y').' Supermaterial. Semua hak dilindungi.')
        ->assertSee('Kebijakan Privasi')
        ->assertDontSee('Kolom Mati')
        ->assertDontSee('>Kategori<', false);
});

it('previews unsaved navbar changes without changing the public site', function () {
    Livewire::test(NavbarBuilder::class)
        ->fillForm(['items' => [['label' => 'Menu Uji Coba', 'link_type' => 'internal', 'url' => '/produk', 'is_active' => true, 'children' => []]]])
        ->call('preview');

    $this->get(route('layout.preview'))->assertOk()->assertSee('Menu Uji Coba')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/')->assertDontSee('Menu Uji Coba');
});

it('restricts the builders and layout preview to admins', function () {
    $this->actingAs(User::factory()->create(['role' => 'editor']));

    $this->get('/admin/navbar-builder')->assertForbidden();
    $this->get('/admin/footer-builder')->assertForbidden();
    $this->get(route('layout.preview'))->assertForbidden();
});

it('shows footer social media and contact even without a contact column, skipping empty links', function () {
    // Persis kondisi yang dilaporkan: media sosial tersimpan, kolom hanya Kategori + Informasi
    Setting::put(SiteLayout::FOOTER_KEY, json_encode([
        'show_logo' => true,
        'columns' => [['title' => 'Kategori', 'type' => 'categories', 'is_active' => true], ['title' => 'Informasi', 'type' => 'pages', 'is_active' => true]],
        'phone' => '0822-6090-0807',
        'socials' => [
            ['platform' => 'instagram', 'url' => 'https://www.instagram.com/supermaterial/'],
            ['platform' => 'facebook', 'url' => ''],
            ['platform' => 'tiktok', 'url' => 'javascript:alert(1)'],
        ],
    ]));

    $this->get('/')->assertOk()
        ->assertSee('href="https://www.instagram.com/supermaterial/"', false)
        ->assertSee('aria-label="Instagram"', false)
        ->assertSee('href="tel:082260900807"', false)
        ->assertDontSee('aria-label="Facebook"', false)
        ->assertDontSee('javascript:alert(1)', false);
});

it('shows a single Halaman menu with an add button on the list', function () {
    $this->get('/admin')->assertOk()->assertSee('Halaman')->assertDontSee('Buat Halaman')->assertDontSee('Daftar Halaman');
    $this->get('/admin/pages')->assertOk()->assertSee('Tambah Halaman');
});
