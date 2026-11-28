<?php

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Mcp\Servers\SupermaterialServer;
use App\Mcp\Tools\ArchiveArticle;
use App\Mcp\Tools\ArchiveProduct;
use App\Mcp\Tools\CreateProduct;
use App\Mcp\Tools\GetAuditLog;
use App\Mcp\Tools\PublishArticle;
use App\Mcp\Tools\RestoreArticle;
use App\Mcp\Tools\RestoreProduct;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use App\Support\Catalog\ProductImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** @param  array<int, string>  $abilities */
function lifecycleTokenUser(string $role = 'editor', array $abilities = ['catalog:read', 'catalog:write', 'articles:read', 'articles:write', 'audit:read']): User
{
    $user = User::factory()->create(['role' => $role]);

    return $user->withAccessToken($user->createToken('claude', $abilities, now()->addDays(90))->accessToken);
}

it('archives a product only with explicit confirmation when published, hides it, and restores it', function () {
    $product = Product::create(['name' => 'Palu Kambing', 'slug' => 'palu-kambing', 'is_published' => true]);
    $path = $product->publicPath('palu-kambing');
    $this->get($path)->assertOk();

    SupermaterialServer::actingAs(lifecycleTokenUser())->tool(ArchiveProduct::class, ['product' => 'palu-kambing'])
        ->assertHasErrors(['masih terbit']);
    expect($product->fresh()->trashed())->toBeFalse();

    SupermaterialServer::actingAs(lifecycleTokenUser())->tool(ArchiveProduct::class, ['product' => 'palu-kambing', 'force_unpublish' => true])
        ->assertOk()->assertStructuredContentPath('archived', 'palu-kambing');

    $this->assertSoftDeleted($product);
    $this->get($path)->assertNotFound();

    SupermaterialServer::actingAs(lifecycleTokenUser())->tool(RestoreProduct::class, ['product' => 'palu-kambing'])
        ->assertOk()->assertStructuredContentPath('slug', 'palu-kambing');

    expect($product->fresh()->trashed())->toBeFalse()
        ->and(AuditLog::whereIn('action', ['product.archived', 'product.restored'])->count())->toBe(2);
    $this->get($path)->assertOk();
});

it('keeps archived slugs reserved for new products and imports', function () {
    Product::create(['name' => 'Cetok Stainless', 'slug' => 'cetok-stainless'])->delete();

    SupermaterialServer::actingAs(lifecycleTokenUser())->tool(CreateProduct::class, ['name' => 'Cetok Stainless'])
        ->assertOk()->assertStructuredContentPath('slug', 'cetok-stainless-2');

    $csv = tempnam(sys_get_temp_dir(), 'impor').'.csv';
    file_put_contents($csv, "nama,slug,harga_min\nCetok Stainless,cetok-stainless,15000\n");
    $result = ProductImporter::import($csv, 'csv');
    @unlink($csv);

    expect($result['created'])->toBe(0)
        ->and($result['failed'][0]['errors']['slug'][0])->toContain('sedang diarsipkan');
});

it('lets authors archive and restore only their own articles', function () {
    $author = lifecycleTokenUser('author', ['articles:read', 'articles:write']);
    $own = Article::create(['title' => 'Milik saya', 'slug' => 'milik-saya', 'content' => '<p>x</p>', 'author_id' => $author->authorProfile()->id]);
    Article::create(['title' => 'Milik orang lain', 'slug' => 'milik-orang-lain', 'content' => '<p>x</p>', 'published_at' => now()->subDay()]);

    SupermaterialServer::actingAs($author)->tool(ArchiveArticle::class, ['article' => 'milik-orang-lain', 'force_unpublish' => true])
        ->assertHasErrors(['Artikel tidak ditemukan']);

    SupermaterialServer::actingAs($author)->tool(ArchiveArticle::class, ['article' => 'milik-saya'])->assertOk();
    $this->assertSoftDeleted($own);

    SupermaterialServer::actingAs($author)->tool(RestoreArticle::class, ['article' => 'milik-saya'])->assertOk();
    expect($own->fresh()->trashed())->toBeFalse();
});

it('blocks publishing incomplete articles unless asked, and schedules future dates', function () {
    $article = Article::create(['title' => 'Cara Pasang Dynabolt', 'slug' => 'cara-pasang-dynabolt', 'content' => '<p>Isi</p>']);

    SupermaterialServer::actingAs(lifecycleTokenUser())->tool(PublishArticle::class, ['article' => 'cara-pasang-dynabolt'])
        ->assertHasErrors(['belum siap terbit', 'Tanpa foto utama', 'Deskripsi meta kosong']);
    expect($article->fresh()->published_at)->toBeNull();

    SupermaterialServer::actingAs(lifecycleTokenUser())->tool(PublishArticle::class, [
        'article' => 'cara-pasang-dynabolt', 'skip_checks' => true, 'published_at' => now()->addDays(3)->toIso8601String(),
    ])->assertOk()->assertStructuredContentPath('status', 'scheduled');

    SupermaterialServer::actingAs(lifecycleTokenUser())->tool(PublishArticle::class, ['article' => 'cara-pasang-dynabolt', 'skip_checks' => true])
        ->assertOk()->assertStructuredContentPath('status', 'published');

    expect($article->fresh()->published_at->isPast())->toBeTrue()
        ->and(AuditLog::where('action', 'article.published')->count())->toBe(2);
});

it('shows the audit log only to admins, including history of archived products', function () {
    SupermaterialServer::actingAs(lifecycleTokenUser())->tools()->assertNotRegistered([GetAuditLog::class]);

    $admin = lifecycleTokenUser('admin');
    Product::create(['name' => 'Dynabolt', 'slug' => 'dynabolt']);
    SupermaterialServer::actingAs($admin)->tool(ArchiveProduct::class, ['product' => 'dynabolt'])->assertOk();

    SupermaterialServer::actingAs($admin)->tool(GetAuditLog::class, ['subject_type' => 'product', 'subject' => 'dynabolt'])
        ->assertOk()
        ->assertStructuredContentPath('entries.0.action', 'product.archived')
        ->assertStructuredContentPath('entries.0.source', 'mcp')
        ->assertStructuredContentPath('entries.0.subject_type', 'product');

    SupermaterialServer::actingAs($admin)->tool(GetAuditLog::class, ['subject_type' => 'product', 'subject' => 'tidak-ada'])
        ->assertHasErrors(['Tidak ada product']);
});

it('archives and restores from the product edit page, and only admins can delete permanently', function () {
    $product = Product::create(['name' => 'Meteran', 'slug' => 'meteran']);

    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]));
    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertActionHidden('forceDelete')
        ->callAction('delete');
    $this->assertSoftDeleted($product);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->callAction('restore');
    expect($product->fresh()->trashed())->toBeFalse();
});
