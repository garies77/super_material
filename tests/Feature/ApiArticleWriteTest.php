<?php

use App\Mcp\Servers\SupermaterialServer;
use App\Mcp\Tools\CreateArticle;
use App\Mcp\Tools\UpdateArticle;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

function articleWriter(string $role = 'editor', array $abilities = ['articles:read', 'articles:write']): User
{
    $user = User::factory()->create(['role' => $role]);

    return $user->withAccessToken($user->createToken('ai', $abilities, now()->addDays(90))->accessToken);
}

function articleApiToken(string $role = 'editor', array $abilities = ['articles:read', 'articles:write']): string
{
    return User::factory()->create(['role' => $role])->createToken('ai', $abilities, now()->addDays(90))->plainTextToken;
}

it('creates a sanitized draft article with categories, tags, products and FAQs over REST', function () {
    $product = Product::first();

    $this->withToken(articleApiToken())->postJson('/api/v1/admin/articles', [
        'title' => 'Cara Memasang Bata Ringan',
        'content' => '<h2>Persiapan</h2><p onclick="x()">Siapkan mortar.</p><script>alert(1)</script>',
        'categories' => ['mortar'],
        'tags' => ['Bata Ringan', 'Tips Baru'],
        'products' => [$product->slug],
        'faqs' => [['question' => 'Berapa lama kering?', 'answer' => 'Sekitar 24 jam.']],
        'meta_title' => 'Cara Memasang Bata Ringan yang Rapi',
    ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'cara-memasang-bata-ringan')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.categories.0.slug', 'mortar')
        ->assertJsonPath('data.products.0.slug', $product->slug);

    $article = Article::where('slug', 'cara-memasang-bata-ringan')->sole();
    expect($article->published_at)->toBeNull()
        ->and($article->content)->toContain('<h2>Persiapan</h2>')->not->toContain('<script')->not->toContain('onclick')
        ->and($article->tags->pluck('slug')->sort()->values()->all())->toBe(['bata-ringan', 'tips-baru'])
        ->and($article->faqs()->sole()->answer)->toBe('Sekitar 24 jam.')
        ->and(Tag::where('slug', 'tips-baru')->exists())->toBeTrue();

    expect(AuditLog::where('action', 'article.created')->sole()->source)->toBe('api');
});

it('publishes immediately or schedules when status=published is sent', function () {
    $token = articleApiToken();

    $this->withToken($token)->postJson('/api/v1/admin/articles', ['title' => 'Terbit Sekarang', 'content' => '<p>Isi</p>', 'status' => 'published'])
        ->assertCreated()->assertJsonPath('data.status', 'published');

    $this->withToken($token)->postJson('/api/v1/admin/articles', [
        'title' => 'Terbit Nanti', 'content' => '<p>Isi</p>', 'status' => 'published', 'published_at' => now()->addDays(3)->toIso8601String(),
    ])->assertCreated()->assertJsonPath('data.status', 'scheduled');
});

it('rejects invalid articles entirely and saves nothing', function (array $payload, string $field) {
    $this->withToken(articleApiToken())->postJson('/api/v1/admin/articles', $payload)
        ->assertStatus(422)->assertJsonValidationErrors($field, 'error.fields');

    expect(Article::where('title', 'Uji Gagal')->exists())->toBeFalse();
})->with([
    'missing content' => [['title' => 'Uji Gagal'], 'content'],
    'meta title too long' => [['title' => 'Uji Gagal', 'content' => '<p>x</p>', 'meta_title' => str_repeat('a', 71)], 'meta_title'],
    'unknown category' => [['title' => 'Uji Gagal', 'content' => '<p>x</p>', 'categories' => ['tidak-ada']], 'categories.0'],
]);

it('requires articles:write in both the token and the role', function () {
    $this->withToken(articleApiToken('editor', ['articles:read']))
        ->postJson('/api/v1/admin/articles', ['title' => 'X', 'content' => '<p>x</p>'])->assertForbidden();

    $this->withToken(articleApiToken('contributor', ['articles:write']))
        ->postJson('/api/v1/admin/articles', ['title' => 'X', 'content' => '<p>x</p>'])->assertForbidden();
});

it('updates only the sent fields, can unpublish, and audits the change', function () {
    $article = Article::where('slug', 'cara-memilih-semen-untuk-rumah')->sole();
    $title = $article->title;

    $this->withToken(articleApiToken())->patchJson("/api/v1/admin/articles/{$article->slug}", [
        'summary' => 'Ringkasan baru.',
        'status' => 'draft',
    ])->assertOk()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.summary', 'Ringkasan baru.');

    $article->refresh();
    expect($article->title)->toBe($title)
        ->and($article->published_at)->toBeNull()
        ->and(AuditLog::where('action', 'article.updated')->sole()->changes)->toHaveKey('summary');
});

it('keeps authors to their own articles and their own author profile', function () {
    $author = User::factory()->create(['role' => 'author']);
    $token = $author->createToken('ai', ['articles:read', 'articles:write'], now()->addDays(90))->plainTextToken;
    $someoneElse = Author::where('user_id', '!=', $author->id)->orWhereNull('user_id')->first();

    $this->withToken($token)->postJson('/api/v1/admin/articles', [
        'title' => 'Tulisan Penulis', 'content' => '<p>x</p>', 'author' => $someoneElse->slug,
    ])->assertCreated();

    expect(Article::where('slug', 'tulisan-penulis')->sole()->author_id)->toBe($author->authorProfile()->id);

    $this->withToken($token)->patchJson('/api/v1/admin/articles/cara-memilih-semen-untuk-rumah', ['summary' => 'Bajak'])->assertNotFound();
    $this->withToken($token)->getJson('/api/v1/admin/articles')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'tulisan-penulis');
});

it('offers article write tools over MCP only with articles:write', function () {
    SupermaterialServer::actingAs(articleWriter())->tools()->assertRegistered([CreateArticle::class, UpdateArticle::class]);
    SupermaterialServer::actingAs(articleWriter('admin', ['articles:read']))->tools()->assertNotRegistered([CreateArticle::class, UpdateArticle::class]);
});

it('creates and updates an article over MCP as a draft, audited as mcp', function () {
    $user = articleWriter();

    SupermaterialServer::actingAs($user)->tool(CreateArticle::class, [
        'title' => 'Panduan Cat Tembok Luar',
        'content' => '<h2>Pilih cat</h2><p>Gunakan cat eksterior.</p>',
        'tags' => ['Cat'],
    ])->assertOk()
        ->assertStructuredContentPath('slug', 'panduan-cat-tembok-luar')
        ->assertStructuredContentPath('status', 'draft');

    SupermaterialServer::actingAs($user)->tool(UpdateArticle::class, ['article' => 'panduan-cat-tembok-luar', 'status' => 'published'])
        ->assertOk()->assertStructuredContentPath('article.status', 'published');

    SupermaterialServer::actingAs($user)->tool(UpdateArticle::class, ['article' => 'tidak-ada', 'summary' => 'x'])
        ->assertHasErrors(['Artikel tidak ditemukan']);
    SupermaterialServer::actingAs($user)->tool(UpdateArticle::class, ['article' => 'panduan-cat-tembok-luar'])
        ->assertHasErrors(['Tidak ada kolom']);

    expect(AuditLog::whereIn('action', ['article.created', 'article.updated'])->pluck('source')->unique()->all())->toBe(['mcp']);
});
