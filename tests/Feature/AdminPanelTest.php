<?php

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lets an admin open every admin screen', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());

    foreach ([
        '/admin', '/admin/products', '/admin/products/create', '/admin/products/1/edit',
        '/admin/categories', '/admin/categories/create', '/admin/categories/1/edit',
        '/admin/articles', '/admin/articles/create', '/admin/articles/1/edit',
        '/admin/brands', '/admin/sales-channels', '/admin/pages', '/admin/users',
        '/admin/redirects', '/admin/click-events', '/admin/site-settings',
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('keeps authors out of admin-only areas', function () {
    $this->seed(DatabaseSeeder::class);
    $author = User::factory()->create(['role' => 'author']);
    $this->actingAs($author);

    $this->get('/admin/users')->assertForbidden();
    $this->get('/admin/site-settings')->assertForbidden();
    $this->get('/admin/products')->assertForbidden();
    $this->get('/admin/articles')->assertOk();
});

it('allows editors to manage content but protects settings and users', function () {
    $this->seed(DatabaseSeeder::class);
    $editor = User::factory()->create(['role' => 'editor']);
    $this->actingAs($editor);

    $this->get('/admin/products')->assertOk();
    $this->get('/admin/categories')->assertOk();
    $this->get('/admin/articles')->assertOk();
    $this->get('/admin/users')->assertForbidden();
    $this->get('/admin/site-settings')->assertForbidden();
});

it('orders the sidebar by operational priority and supports the collapsed icon rail', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());

    $html = $this->get('/admin')->assertOk()->getContent();
    // Tiap item punya tooltip saat sidebar diciutkan; urutan tooltip = urutan menu
    preg_match_all("/sidebar\.isOpen \? null : '([^']+)'/", $html, $m);

    expect($m[1])->toBe([
        'Dasbor',
        'Produk', 'Kategori', 'Merek', 'Kanal Beli',
        'Artikel', 'Penulis', 'Halaman', 'Kampanye', 'Formulir', 'Jawaban formulir', 'Media',
        'Laporan Klik', 'Laporan kampanye', 'Riwayat XP',
        'Kustomisasi Beranda', 'Navbar Builder', 'Footer Builder',
        'Pengaturan situs', 'Pengguna', 'Token API', 'Pengalihan URL',
    ])->and($html)->not->toContain('Penggunas');
});

it('uses the panel-layout toggle icon and the slim sidebar scrollbar', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());

    $html = $this->get('/admin')->assertOk()->getContent();

    // Tombol ciut & buka sidebar sama-sama memakai ikon panel, bukan chevron
    expect(substr_count($html, '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M9.5 4v16"/>'))->toBeGreaterThanOrEqual(2);

    // Gaya custom kini di tema Vite (resources/css/filament/admin/theme.css), dimuat sebagai stylesheet panel
    $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));
    expect($html)->toContain(Vite::asset('resources/css/filament/admin/theme.css'))
        ->and($theme)->toContain('.fi-sidebar-nav::-webkit-scrollbar-button')->toContain('.sm-sudo-callout')->toContain('.dark .fi-sidebar-nav');
});

it('asks for the admin password only when granting higher privileges', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
    $author = User::factory()->create(['role' => 'author']);

    Livewire::test(ManageUsers::class)
        ->mountTableAction('edit', $author)
        ->assertTableActionDataSet(['role' => 'author'])
        ->assertFormFieldIsHidden('sudo_password', 'mountedActionSchema0')
        ->setTableActionData(['can_manage_custom_html' => true])
        ->assertFormFieldIsVisible('sudo_password', 'mountedActionSchema0')
        ->setTableActionData(['can_manage_custom_html' => false, 'name' => 'Nama Baru'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($author->fresh()->name)->toBe('Nama Baru');
});
