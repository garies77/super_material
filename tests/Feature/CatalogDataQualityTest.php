<?php

use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Mcp\Servers\SupermaterialServer;
use App\Mcp\Tools\UpdateProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Pengguna yang memakai token API tulis katalog, seperti saat datang lewat /mcp. */
function catalogTokenUser(): User
{
    $user = User::factory()->create(['role' => 'editor']);

    return $user->withAccessToken($user->createToken('claude', ['catalog:read', 'catalog:write'], now()->addDays(90))->accessToken);
}

it('rejects admin panel slugs with spaces or capital letters', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

    Livewire::test(ManageBrands::class)
        ->callAction('create', ['name' => 'Wolf', 'slug' => 'WOLF Brand'])
        ->assertHasActionErrors(['slug' => 'regex']);
    expect(Brand::count())->toBe(0);

    $category = Category::create(['name' => 'Palu', 'slug' => 'palu', 'is_active' => true]);
    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm(['slug' => 'Palu-Jaya- Intero '])
        ->call('save')
        ->assertHasFormErrors(['slug' => 'regex']);
    expect($category->refresh()->slug)->toBe('palu');
});

it('rejects prices below the minimum and warns when the reference price jumps', function () {
    $product = Product::create(['name' => 'Dynabolt', 'slug' => 'dynabolt', 'price_min' => 40000, 'price_max' => 50000]);

    SupermaterialServer::actingAs(catalogTokenUser())->tool(UpdateProduct::class, ['product' => 'dynabolt', 'price_min' => 32])
        ->assertHasErrors();
    expect($product->refresh()->price_min)->toBe(40000);

    // Naik 5x: tersimpan, tetapi asisten diminta mengonfirmasi ke pengguna
    SupermaterialServer::actingAs(catalogTokenUser())->tool(UpdateProduct::class, ['product' => 'dynabolt', 'price_min' => 206200, 'price_max' => 210000])
        ->assertOk()
        ->assertStructuredContentPath('warnings.0', 'Harga terendah berubah 416% (Rp40.000 → Rp206.200). Pastikan ini bukan salah ketik.');
    expect($product->refresh()->price_min)->toBe(206200);

    // Perubahan wajar tidak memunculkan peringatan
    SupermaterialServer::actingAs(catalogTokenUser())->tool(UpdateProduct::class, ['product' => 'dynabolt', 'price_min' => 200000])
        ->assertOk()
        ->assertStructuredContentPath('warnings', null);
});

it('lists bad slugs on a dry run and fixes them with redirects when run for real', function () {
    $category = Category::create(['name' => 'Palu', 'slug' => 'Palu-Jaya- Intero ', 'is_active' => true]);
    $brand = Brand::create(['name' => 'Wolf', 'slug' => 'WOLF-Brand']);
    Product::create(['name' => 'Palu Lain', 'slug' => 'palu-kambing-wolf', 'is_published' => true]);
    $product = Product::create(['name' => 'Palu Kambing', 'slug' => 'Palu -Kambing -Wolf', 'is_published' => true]);
    $oldProductPath = $product->publicPath($product->slug);

    $this->artisan('catalog:normalize-slugs', ['--dry-run' => true])
        ->expectsOutputToContain('3 slug perlu dirapikan')
        ->assertSuccessful();
    expect($product->refresh()->slug)->toBe('Palu -Kambing -Wolf');

    $this->artisan('catalog:normalize-slugs')->assertSuccessful();

    expect($category->refresh()->slug)->toBe('palu-jaya-intero')
        ->and($brand->refresh()->slug)->toBe('wolf-brand')
        // slug hasil rapi sudah dipakai produk lain, jadi diberi akhiran
        ->and($product->refresh()->slug)->toBe('palu-kambing-wolf-2');

    $this->get($oldProductPath)->assertRedirect($product->publicPath('palu-kambing-wolf-2'))->assertStatus(301);

    $this->artisan('catalog:normalize-slugs')->expectsOutput('Semua slug sudah rapi.')->assertSuccessful();
});
