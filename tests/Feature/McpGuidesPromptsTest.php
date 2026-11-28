<?php

use App\Mcp\Prompts\TidyProductPrompt;
use App\Mcp\Prompts\WeeklyReportPrompt;
use App\Mcp\Prompts\WriteArticleFromProductsPrompt;
use App\Mcp\Resources\CatalogSummaryResource;
use App\Mcp\Resources\SeoGuideResource;
use App\Mcp\Resources\WritingGuideResource;
use App\Mcp\Servers\SupermaterialServer;
use App\Mcp\Tools\SuggestInternalLinks;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\AiGuides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/** @param  array<int, string>  $abilities */
function guideTokenUser(string $role = 'editor', array $abilities = ['catalog:read', 'catalog:write', 'articles:read', 'articles:write']): User
{
    $user = User::factory()->create(['role' => $role]);

    return $user->withAccessToken($user->createToken('claude', $abilities, now()->addDays(90))->accessToken);
}

it('registers guides, summary, and prompts according to the token abilities', function () {
    SupermaterialServer::actingAs(guideTokenUser())->resources()
        ->assertRegistered([WritingGuideResource::class, SeoGuideResource::class, CatalogSummaryResource::class]);
    SupermaterialServer::actingAs(guideTokenUser())->prompts()
        ->assertRegistered([WriteArticleFromProductsPrompt::class, TidyProductPrompt::class])
        ->assertNotRegistered([WeeklyReportPrompt::class]);

    // Penulis: pedoman ada, ringkasan katalog dan prompt katalog tidak
    $author = guideTokenUser('author', ['articles:read', 'articles:write']);
    SupermaterialServer::actingAs($author)->resources()
        ->assertRegistered([WritingGuideResource::class])
        ->assertNotRegistered([CatalogSummaryResource::class]);
    SupermaterialServer::actingAs($author)->prompts()->assertNotRegistered([TidyProductPrompt::class]);
});

it('serves the default guides and lets admins replace them from site settings', function () {
    SupermaterialServer::actingAs(guideTokenUser())->resource(SeoGuideResource::class)
        ->assertOk()->assertSee('meta_description 140–160 karakter');

    Setting::put(AiGuides::WRITING_KEY, "# Gaya tulis\n- Sapa pembaca dengan \"Bapak/Ibu\".");

    SupermaterialServer::actingAs(guideTokenUser())->resource(WritingGuideResource::class)
        ->assertOk()->assertSee('Bapak/Ibu')->assertDontSee('Sapa pembaca dengan "Anda"');
});

it('summarizes the catalog including archived products', function () {
    Cache::forget(CatalogSummaryResource::CACHE_KEY);
    $category = Category::create(['name' => 'Cetok', 'slug' => 'cetok', 'is_active' => true]);
    Product::create(['name' => 'Cetok A', 'slug' => 'cetok-a', 'category_id' => $category->id, 'is_published' => true, 'price_min' => 13100]);
    Product::create(['name' => 'Cetok B', 'slug' => 'cetok-b', 'is_published' => true])->delete();

    SupermaterialServer::actingAs(guideTokenUser())->resource(CatalogSummaryResource::class)
        ->assertOk()
        ->assertSee(['"published": 1', '"archived": 1', '"slug": "cetok"', '"price_min": 13100']);
});

it('puts prompt arguments and the site guides into the instructions', function () {
    SupermaterialServer::actingAs(guideTokenUser())->prompt(WriteArticleFromProductsPrompt::class, [
        'topik' => 'cara pasang dynabolt', 'produk' => 'dynabolt-m8,palu-wolf',
    ])->assertOk()->assertSee(['Topik yang diminta: cara pasang dynabolt', 'dynabolt-m8,palu-wolf', 'Aturan SEO']);

    SupermaterialServer::actingAs(guideTokenUser())->prompt(TidyProductPrompt::class, [])
        ->assertOk()->assertSee('Jalankan audit-catalog');

    SupermaterialServer::actingAs(guideTokenUser('admin', ['reports:read']))->prompt(WeeklyReportPrompt::class, [])
        ->assertOk()->assertSee(now('Asia/Jakarta')->toDateString());
});

it('suggests related unlinked products and articles for an article', function () {
    $category = Category::create(['name' => 'Dynabolt', 'slug' => 'dynabolt', 'is_active' => true]);
    $linked = Product::create(['name' => 'Dynabolt WOWO M8', 'slug' => 'dynabolt-wowo-m8', 'category_id' => $category->id, 'is_published' => true]);
    Product::create(['name' => 'Baut Tanam Beton M10', 'slug' => 'baut-tanam-m10', 'category_id' => $category->id, 'is_published' => true]);
    Product::create(['name' => 'Kain Mat Fiber', 'slug' => 'kain-mat', 'is_published' => true]);

    $article = Article::create(['title' => 'Cara Pasang Dynabolt di Beton', 'slug' => 'cara-pasang-dynabolt', 'content' => '<p>x</p>']);
    $article->categories()->attach($category->id);
    $article->products()->attach($linked->id);
    $related = Article::create(['title' => 'Memilih Ukuran Dynabolt', 'slug' => 'ukuran-dynabolt', 'content' => '<p>x</p>', 'published_at' => now()->subDay()]);
    $related->categories()->attach($category->id);

    SupermaterialServer::actingAs(guideTokenUser())->tool(SuggestInternalLinks::class, ['article' => 'cara-pasang-dynabolt'])
        ->assertOk()
        ->assertStructuredContentPath('linked_products', ['dynabolt-wowo-m8'])
        ->assertStructuredContentPath('products.0.slug', 'baut-tanam-m10')
        ->assertStructuredContentCount(1, 'products')
        ->assertStructuredContentPath('articles.0.slug', 'ukuran-dynabolt');
});
