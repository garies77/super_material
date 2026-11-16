<?php

use App\Mcp\Servers\SupermaterialServer;
use App\Mcp\Tools\GetArticle;
use App\Mcp\Tools\GetClickReport;
use App\Mcp\Tools\GetProduct;
use App\Mcp\Tools\GetProductReport;
use App\Mcp\Tools\GetReportSummary;
use App\Mcp\Tools\GetTrafficReport;
use App\Mcp\Tools\ListBrands;
use App\Mcp\Tools\ListCategories;
use App\Mcp\Tools\SearchArticles;
use App\Mcp\Tools\SearchProducts;
use App\Models\Article;
use App\Models\ClickEvent;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

/** Pengguna yang sedang memakai token API dengan ability tertentu, seperti saat datang lewat /mcp. */
function mcpUser(string $role, array $abilities, array $userAttrs = []): User
{
    $user = User::factory()->create(['role' => $role] + $userAttrs);

    return $user->withAccessToken($user->createToken('claude', $abilities, now()->addDays(90))->accessToken);
}

it('registers only the tools allowed by both the token ability and the user role', function () {
    SupermaterialServer::actingAs(mcpUser('admin', ['catalog:read']))->tools()
        ->assertRegistered([SearchProducts::class, GetProduct::class, ListCategories::class, ListBrands::class])
        ->assertNotRegistered([SearchArticles::class, GetArticle::class, GetReportSummary::class, GetProductReport::class, GetTrafficReport::class, GetClickReport::class]);

    // Peran Penulis tidak boleh membaca katalog walau token-nya punya catalog:read
    SupermaterialServer::actingAs(mcpUser('author', ['catalog:read', 'articles:read']))->tools()
        ->assertRegistered([SearchArticles::class, GetArticle::class])
        ->assertNotRegistered([SearchProducts::class, GetProduct::class]);

    SupermaterialServer::actingAs(mcpUser('editor', ['catalog:read', 'reports:read']))
        ->tool(GetReportSummary::class)
        ->assertNotRegistered();
});

it('searches products of every status and returns full product detail', function () {
    $draft = Product::query()->first();
    $draft->update(['is_published' => false]);
    $user = mcpUser('admin', ['catalog:read']);

    SupermaterialServer::actingAs($user)->tool(SearchProducts::class, ['status' => 'draft'])
        ->assertOk()
        ->assertStructuredContentPath('total', 1)
        ->assertStructuredContentPath('products.0.slug', $draft->slug)
        ->assertStructuredContentPath('products.0.status', 'draft');

    SupermaterialServer::actingAs($user)->tool(SearchProducts::class, ['query' => $draft->name])
        ->assertOk()
        ->assertStructuredContentPath('products.0.id', $draft->id);

    SupermaterialServer::actingAs($user)->tool(GetProduct::class, ['slug' => $draft->slug])
        ->assertOk()
        ->assertStructuredContentPath('id', $draft->id)
        ->assertStructuredContentPath('status', 'draft')
        ->assertStructuredContentPath('description', $draft->description);

    SupermaterialServer::actingAs($user)->tool(GetProduct::class, ['slug' => 'tidak-ada'])
        ->assertHasErrors(['Produk tidak ditemukan.']);

    SupermaterialServer::actingAs($user)->tool(SearchProducts::class, ['limit' => 500])
        ->assertHasErrors();
});

it('lists categories and brands with product counts', function () {
    $product = Product::published()->with(['category', 'brand'])->whereNotNull('brand_id')->first();
    $user = mcpUser('editor', ['catalog:read']);

    SupermaterialServer::actingAs($user)->tool(ListCategories::class)
        ->assertOk()
        ->assertSee($product->category->slug);

    SupermaterialServer::actingAs($user)->tool(ListBrands::class)
        ->assertOk()
        ->assertSee($product->brand->slug);
});

it('limits authors to their own articles, drafts included', function () {
    $author = mcpUser('author', ['articles:read']);
    $own = Article::create([
        'title' => 'Draf milik penulis', 'slug' => 'draf-milik-penulis', 'content' => '<p>Isi draf</p>',
        'author_id' => $author->authorProfile()->id,
    ]);
    $other = Article::published()->firstOrFail();

    SupermaterialServer::actingAs($author)->tool(SearchArticles::class)
        ->assertOk()
        ->assertStructuredContentPath('total', 1)
        ->assertStructuredContentPath('articles.0.slug', $own->slug)
        ->assertStructuredContentPath('articles.0.status', 'draft');

    SupermaterialServer::actingAs($author)->tool(GetArticle::class, ['slug' => $own->slug])
        ->assertOk()
        ->assertStructuredContentPath('content', '<p>Isi draf</p>');

    SupermaterialServer::actingAs($author)->tool(GetArticle::class, ['slug' => $other->slug])
        ->assertHasErrors(['Artikel tidak ditemukan.']);

    SupermaterialServer::actingAs(mcpUser('editor', ['articles:read']))->tool(SearchArticles::class, ['status' => 'draft'])
        ->assertOk()
        ->assertStructuredContentPath('articles.0.slug', $own->slug);
});

it('reports aggregated clicks without bots and validates the date range', function () {
    $product = Product::published()->first();
    $channel = SalesChannel::where('slug', 'shopee')->first();
    foreach ([false, false, true] as $isBot) {
        ClickEvent::create([
            'product_id' => $product->id, 'sales_channel_id' => $channel->id, 'is_bot' => $isBot,
            'page_url' => url('/artikel/cara-memilih-semen-untuk-rumah'), 'created_at' => now(),
        ]);
    }
    $user = mcpUser('admin', ['reports:read']);

    SupermaterialServer::actingAs($user)->tool(GetClickReport::class)
        ->assertOk()
        ->assertStructuredContentPath('total_clicks', 2)
        ->assertStructuredContentPath('rows.0.slug', $product->slug);

    SupermaterialServer::actingAs($user)->tool(GetClickReport::class, ['group_by' => 'channel', 'include_bots' => true])
        ->assertOk()
        ->assertStructuredContentPath('rows.0.clicks', 3);

    SupermaterialServer::actingAs($user)->tool(GetReportSummary::class, ['date_from' => '2026-01-01', 'date_to' => '2026-01-31'])
        ->assertOk()
        ->assertStructuredContentPath('period.date_from', '2026-01-01')
        ->assertStructuredContentPath('period.previous_to', '2025-12-31');

    SupermaterialServer::actingAs($user)->tool(GetTrafficReport::class, ['date_from' => '2024-01-01', 'date_to' => '2026-01-01'])
        ->assertHasErrors(['Rentang tanggal maksimal 366 hari.']);

    SupermaterialServer::actingAs($user)->tool(GetProductReport::class)
        ->assertOk()
        ->assertStructuredContentPath('top_clicked.0.product_id', $product->id);
});
