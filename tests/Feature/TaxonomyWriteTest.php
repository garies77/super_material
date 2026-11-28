<?php

use App\Mcp\Servers\SupermaterialServer;
use App\Mcp\Tools\CreateBrand;
use App\Mcp\Tools\CreateCategory;
use App\Mcp\Tools\ListTags;
use App\Mcp\Tools\MergeTags;
use App\Mcp\Tools\UpdateBrand;
use App\Mcp\Tools\UpdateCategory;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use App\Support\RemoteFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** @param  array<int, string>  $abilities */
function taxonomyTokenUser(string $role = 'editor', array $abilities = ['catalog:read', 'articles:read', 'taxonomy:write']): User
{
    $user = User::factory()->create(['role' => $role]);

    return $user->withAccessToken($user->createToken('claude', $abilities, now()->addDays(90))->accessToken);
}

it('offers taxonomy write tools only when both the token and the role allow taxonomy:write', function () {
    $tools = [CreateCategory::class, UpdateCategory::class, CreateBrand::class, UpdateBrand::class, MergeTags::class];

    SupermaterialServer::actingAs(taxonomyTokenUser())->tools()->assertRegistered($tools);
    SupermaterialServer::actingAs(taxonomyTokenUser('editor', ['catalog:read']))->tools()->assertNotRegistered($tools);
    SupermaterialServer::actingAs(taxonomyTokenUser('author', ['articles:read', 'taxonomy:write']))->tools()->assertNotRegistered($tools);
});

it('creates subcategories up to three levels and rejects malformed slugs', function () {
    SupermaterialServer::actingAs(taxonomyTokenUser())->tool(CreateCategory::class, ['name' => 'Alat Tukang'])
        ->assertOk()->assertStructuredContentPath('slug', 'alat-tukang');
    SupermaterialServer::actingAs(taxonomyTokenUser())->tool(CreateCategory::class, ['name' => 'Alat Beton', 'parent' => 'alat-tukang'])
        ->assertOk()->assertStructuredContentPath('parent', 'alat-tukang');
    SupermaterialServer::actingAs(taxonomyTokenUser())->tool(CreateCategory::class, ['name' => 'Dynabolt', 'parent' => 'alat-beton'])
        ->assertOk();

    SupermaterialServer::actingAs(taxonomyTokenUser())->tool(CreateCategory::class, ['name' => 'Terlalu Dalam', 'parent' => 'dynabolt'])
        ->assertHasErrors(['maksimal 3 level']);
    SupermaterialServer::actingAs(taxonomyTokenUser())->tool(CreateCategory::class, ['name' => 'Palu', 'slug' => 'Palu-Jaya- Intero '])
        ->assertHasErrors(['huruf kecil, angka, dan tanda hubung']);

    expect(Category::count())->toBe(3)
        ->and(AuditLog::where('action', 'category.created')->count())->toBe(3);
});

it('renames a category slug with a redirect, blocks cycles, and warns when hiding a category with products', function () {
    $root = Category::create(['name' => 'Alat', 'slug' => 'alat', 'is_active' => true]);
    $category = Category::create(['name' => 'Dnynabolt', 'slug' => 'dnynabolt-beton-jaya-intero', 'parent_id' => $root->id, 'is_active' => true]);
    Product::create(['name' => 'Dynabolt M8', 'slug' => 'dynabolt-m8', 'category_id' => $category->id, 'is_published' => true]);
    $oldPath = $category->publicPath($category->slug);

    SupermaterialServer::actingAs(taxonomyTokenUser())->tool(UpdateCategory::class, [
        'category' => 'dnynabolt-beton-jaya-intero', 'name' => 'Dynabolt', 'slug' => 'dynabolt',
    ])->assertOk()->assertStructuredContentPath('category.slug', 'dynabolt');

    $this->get($oldPath)->assertStatus(301)->assertRedirect($category->publicPath('dynabolt'));

    SupermaterialServer::actingAs(taxonomyTokenUser())->tool(UpdateCategory::class, ['category' => 'alat', 'parent' => 'dynabolt'])
        ->assertHasErrors(['dirinya sendiri atau turunannya']);
    expect($root->refresh()->parent_id)->toBeNull();

    SupermaterialServer::actingAs(taxonomyTokenUser())->tool(UpdateCategory::class, ['category' => 'dynabolt', 'is_active' => false])
        ->assertOk()
        ->assertStructuredContentPath('warnings.0', 'Kategori dinonaktifkan padahal masih berisi 1 produk; halaman kategorinya tidak tampil lagi di situs.');
});

it('creates a brand with a logo from a url and renames it', function () {
    Storage::fake('public');
    RemoteFile::resolveHostsUsing(fn () => ['93.184.216.34']);
    Http::fake(['https://cdn.example.com/*' => Http::response(UploadedFile::fake()->image('logo.png', 200, 200)->getContent())]);

    SupermaterialServer::actingAs(taxonomyTokenUser())->tool(CreateBrand::class, ['name' => 'WOLF', 'logo_url' => 'https://cdn.example.com/wolf.png'])
        ->assertOk()->assertStructuredContentPath('slug', 'wolf');

    $brand = Brand::firstWhere('slug', 'wolf');
    Storage::disk('public')->assertExists($brand->logo);

    SupermaterialServer::actingAs(taxonomyTokenUser())->tool(UpdateBrand::class, ['brand' => 'wolf', 'slug' => 'WOLF-Brand'])
        ->assertHasErrors(['huruf kecil']);
    SupermaterialServer::actingAs(taxonomyTokenUser())->tool(UpdateBrand::class, ['brand' => (string) $brand->id, 'name' => 'Wolf Tools'])
        ->assertOk()->assertStructuredContentPath('changed', ['name']);

    RemoteFile::resolveHostsUsing(null);
});

it('merges duplicate tags without duplicating links and redirects the old tag page', function () {
    $source = Tag::create(['name' => 'tips-tukang', 'slug' => 'tips-tukang-2']);
    $target = Tag::create(['name' => 'tips tukang', 'slug' => 'tips-tukang']);
    $both = Article::create(['title' => 'Dua tag', 'slug' => 'dua-tag', 'content' => '<p>x</p>']);
    $sourceOnly = Article::create(['title' => 'Satu tag', 'slug' => 'satu-tag', 'content' => '<p>x</p>']);
    $both->tags()->attach([$source->id, $target->id]);
    $sourceOnly->tags()->attach($source->id);
    $source->products()->attach(Product::create(['name' => 'Palu', 'slug' => 'palu'])->id);

    SupermaterialServer::actingAs(taxonomyTokenUser())->tool(ListTags::class, ['query' => 'tips'])
        ->assertOk()
        // Diurutkan per nama: "tips tukang" (spasi) sebelum "tips-tukang"
        ->assertStructuredContentPath('tags.0.slug', 'tips-tukang')
        ->assertStructuredContentPath('tags.1.articles', 2);

    SupermaterialServer::actingAs(taxonomyTokenUser())->tool(MergeTags::class, ['source' => 'tips-tukang-2', 'target' => 'tips-tukang-2'])
        ->assertHasErrors(['source dan target sama']);

    SupermaterialServer::actingAs(taxonomyTokenUser())->tool(MergeTags::class, ['source' => 'tips-tukang-2', 'target' => 'tips-tukang'])
        ->assertOk()
        ->assertStructuredContentPath('moved', 2)
        ->assertStructuredContentPath('tag.articles', 2)
        ->assertStructuredContentPath('tag.products', 1);

    expect(Tag::find($source->id))->toBeNull()
        ->and($both->tags()->pluck('slug')->all())->toBe(['tips-tukang']);

    $this->get(route('article.tag', 'tips-tukang-2', false))->assertStatus(301)->assertRedirect(route('article.tag', 'tips-tukang', false));
});

it('creates and updates categories and brands through the REST API with the same rules', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $token = $user->createToken('test', ['taxonomy:write'], now()->addDays(90))->plainTextToken;
    $readOnly = $user->createToken('test', ['catalog:read'], now()->addDays(90))->plainTextToken;

    $this->withToken($readOnly)->postJson('/api/v1/admin/categories', ['name' => 'Cetok'])->assertForbidden();
    // Guard Sanctum menyimpan pengguna dari request sebelumnya; reset agar token berikutnya dibaca ulang
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->postJson('/api/v1/admin/categories', ['name' => 'Cetok'])
        ->assertCreated()->assertJsonPath('data.slug', 'cetok');
    $this->withToken($token)->patchJson('/api/v1/admin/categories/cetok', ['slug' => 'Cetok Beton'])
        ->assertStatus(422);

    $this->withToken($token)->postJson('/api/v1/admin/brands', ['name' => 'FURANO'])
        ->assertCreated()->assertJsonPath('data.slug', 'furano');
    $this->withToken($token)->patchJson('/api/v1/admin/brands/furano', ['name' => 'Furano'])
        ->assertOk()->assertJsonPath('data.name', 'Furano');
});
