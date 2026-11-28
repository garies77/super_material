<?php

use App\Jobs\ProcessProductImport;
use App\Mcp\Servers\SupermaterialServer;
use App\Mcp\Tools\AuditArticles;
use App\Mcp\Tools\AuditCatalog;
use App\Mcp\Tools\BulkUpdateProducts;
use App\Mcp\Tools\GetImportStatus;
use App\Mcp\Tools\StartImport;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\User;
use App\Support\RemoteFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

/** @param  array<int, string>  $abilities */
function auditTokenUser(string $role = 'editor', array $abilities = ['catalog:read', 'catalog:write', 'articles:read', 'import:write']): User
{
    $user = User::factory()->create(['role' => $role]);

    return $user->withAccessToken($user->createToken('claude', $abilities, now()->addDays(90))->accessToken);
}

it('audits the catalog in one call, flags likely duplicates, and filters by issue', function () {
    $category = Category::create(['name' => 'Dynabolt', 'slug' => 'dynabolt', 'is_active' => true]);
    $brand = Brand::create(['name' => 'WOWO', 'slug' => 'wowo']);
    Product::create(['name' => 'Dynabolt Baut Tanam Beton Wowo M8 M10 M12 Panjang 40 - 129 mm', 'slug' => 'dnynabolt-jaya-intero-medan', 'category_id' => $category->id, 'brand_id' => $brand->id, 'is_published' => true]);
    Product::create(['name' => 'Baut Tanam Beton Dynabolt WOWO M8 M10 M12', 'slug' => 'baut-tanam-beton-medan', 'category_id' => $category->id, 'brand_id' => $brand->id, 'is_published' => true]);
    Product::create(['name' => 'Palu Kambing Wolf', 'slug' => 'Palu -Kambing -Wolf', 'is_published' => false]);

    SupermaterialServer::actingAs(auditTokenUser())->tool(AuditCatalog::class, [])
        ->assertOk()
        ->assertStructuredContentPath('total_products', 3)
        ->assertStructuredContentPath('issue_counts.possible_duplicate.count', 2)
        ->assertStructuredContentPath('issue_counts.no_photo.count', 3)
        ->assertStructuredContentPath('issue_counts.bad_slug.count', 1);

    SupermaterialServer::actingAs(auditTokenUser())->tool(AuditCatalog::class, ['issue' => 'possible_duplicate'])
        ->assertOk()
        ->assertStructuredContentPath('matching_products', 2)
        ->assertSee('baut-tanam-beton-medan');

    SupermaterialServer::actingAs(auditTokenUser())->tool(AuditCatalog::class, ['status' => 'draft'])
        ->assertStructuredContentPath('total_products', 1)
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('products.0.slug', 'Palu -Kambing -Wolf')
            ->where('products.0.issues', fn ($issues) => collect($issues)->contains('bad_slug'))
            ->etc());
});

it('audits articles, flags draft linked products, and limits authors to their own articles', function () {
    $draftProduct = Product::create(['name' => 'Cetok Baru', 'slug' => 'cetok-baru', 'is_published' => false]);
    $article = Article::create(['title' => 'Cara Memilih Cetok', 'slug' => 'cara-memilih-cetok', 'content' => '<p>Pendek</p>']);
    $article->products()->attach($draftProduct->id);

    SupermaterialServer::actingAs(auditTokenUser())->tool(AuditArticles::class, [])
        ->assertOk()
        ->assertStructuredContentPath('total_articles', 1)
        ->assertStructuredContentPath('articles.0.draft_products', ['cetok-baru'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('articles.0.issues', fn ($issues) => collect($issues)->intersect(['draft_products', 'no_image', 'short_content'])->count() === 3)
            ->etc());

    SupermaterialServer::actingAs(auditTokenUser('author', ['articles:read']))->tool(AuditArticles::class, [])
        ->assertOk()
        ->assertStructuredContentPath('total_articles', 0);
});

it('previews bulk changes without saving, then applies them once with the owner token', function () {
    Category::create(['name' => 'Cetok', 'slug' => 'cetok', 'is_active' => true]);
    $products = collect(['cetok-a', 'cetok-b'])->map(fn (string $slug) => Product::create(['name' => $slug, 'slug' => $slug, 'is_published' => true]));
    $user = auditTokenUser();

    SupermaterialServer::actingAs($user)->tool(BulkUpdateProducts::class, ['products' => ['cetok-a'], 'changes' => ['price_min' => 1000]])
        ->assertHasErrors(['tidak bisa diubah massal: price_min']);
    SupermaterialServer::actingAs($user)->tool(BulkUpdateProducts::class, ['products' => ['cetok-a', 'tidak-ada'], 'changes' => ['badge' => 'Baru']])
        ->assertHasErrors(['Produk tidak ditemukan: tidak-ada']);

    $token = null;
    SupermaterialServer::actingAs($user)->tool(BulkUpdateProducts::class, [
        'products' => ['cetok-a', (string) $products[1]->id], 'changes' => ['category' => 'cetok', 'badge' => 'Best Seller'],
    ])->assertOk()
        ->assertStructuredContentPath('dry_run', true)
        ->assertStructuredContentPath('products_affected', 2)
        ->assertStructuredContentPath('previews.0.changes.category.to_name', 'Cetok')
        ->assertStructuredContent(function (AssertableJson $json) use (&$token) {
            $json->where('confirmation_token', function (string $value) use (&$token) {
                $token = $value;

                return strlen($value) === 40;
            })->etc();
        });

    // Pratinjau tidak menyimpan apa pun
    expect(Product::whereNotNull('category_id')->count())->toBe(0)->and($token)->toBeString();

    // Token milik pengguna lain ditolak dan tidak menghanguskan token pemiliknya
    SupermaterialServer::actingAs(auditTokenUser())->tool(BulkUpdateProducts::class, ['dry_run' => false, 'confirmation_token' => $token])
        ->assertHasErrors(['confirmation_token tidak valid']);

    SupermaterialServer::actingAs($user)->tool(BulkUpdateProducts::class, ['dry_run' => false, 'confirmation_token' => $token])
        ->assertOk()->assertStructuredContentPath('products_updated', 2);

    expect(Product::where('badge', 'Best Seller')->whereHas('category', fn ($q) => $q->where('slug', 'cetok'))->count())->toBe(2)
        ->and(AuditLog::where('action', 'product.updated')->count())->toBe(2);

    SupermaterialServer::actingAs($user)->tool(BulkUpdateProducts::class, ['dry_run' => false, 'confirmation_token' => $token])
        ->assertHasErrors(['sudah dipakai']);
});

it('starts an import from a url and reports its status only to its owner or an admin', function () {
    Queue::fake();
    Storage::fake('local');
    RemoteFile::resolveHostsUsing(fn () => ['93.184.216.34']);
    Http::fake(['https://docs.example.com/*' => Http::response("nama,slug,harga_min\nCetok Uji,cetok-uji,15000\n", 200, ['Content-Type' => 'text/csv'])]);
    $owner = auditTokenUser();

    SupermaterialServer::actingAs($owner)->tool(StartImport::class, ['url' => 'https://docs.example.com/produk.csv'])
        ->assertOk()->assertStructuredContentPath('status', 'queued');

    $import = ProductImport::sole();
    Queue::assertPushed(ProcessProductImport::class);
    Storage::disk('local')->assertExists($import->file_path);

    SupermaterialServer::actingAs($owner)->tool(GetImportStatus::class, ['id' => $import->id])
        ->assertOk()->assertStructuredContentPath('file_name', 'produk.csv');
    SupermaterialServer::actingAs(auditTokenUser())->tool(GetImportStatus::class, ['id' => $import->id])
        ->assertHasErrors(['Impor tidak ditemukan']);
    SupermaterialServer::actingAs(auditTokenUser('admin'))->tool(GetImportStatus::class, ['id' => $import->id])
        ->assertOk();

    RemoteFile::resolveHostsUsing(null);
});
