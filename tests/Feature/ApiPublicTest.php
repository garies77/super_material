<?php

use App\Models\Article;
use App\Models\Author;
use App\Models\ClickEvent;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('lists only published products in the documented list shape', function () {
    $draft = Product::query()->first();
    $draft->update(['is_published' => false]);

    $response = $this->getJson('/api/v1/products?per_page=50')->assertOk()
        ->assertJsonStructure(['data' => [['id', 'slug', 'name', 'price', 'canonical_url', 'updated_at']], 'links' => ['next', 'prev'], 'meta' => ['per_page', 'total']]);

    $slugs = collect($response->json('data'))->pluck('slug');
    expect($slugs)->not->toContain($draft->slug)
        ->and($response->json('meta.total'))->toBe(Product::published()->count());

    $this->getJson("/api/v1/products/{$draft->slug}")->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

it('paginates with a cursor and enforces per_page limits', function () {
    $first = $this->getJson('/api/v1/products?per_page=1')->assertOk();
    $next = $first->json('links.next');
    expect($next)->toContain('cursor=');

    $second = $this->getJson($next)->assertOk();
    expect($second->json('data.0.id'))->not->toBe($first->json('data.0.id'));

    $this->getJson('/api/v1/products?per_page=51')->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['code', 'message', 'fields' => ['per_page']]]);
});

it('rejects sort and include values outside the whitelist', function () {
    $this->getJson('/api/v1/products?sort=-password')->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    $this->getJson('/api/v1/products?include=secrets')->assertStatus(422);
    $this->getJson('/api/v1/products?sort=-price&include=category,brand,variants,media,links')->assertOk();
});

it('filters products by category, brand, and price', function () {
    $product = Product::published()->with('category', 'brand')->whereNotNull('brand_id')->first();

    $slugs = collect($this->getJson("/api/v1/products?filter[brand]={$product->brand->slug}")->json('data'))->pluck('slug');
    expect($slugs)->toContain($product->slug);

    $none = $this->getJson('/api/v1/products?filter[category]=tidak-ada')->assertOk();
    expect($none->json('data'))->toBe([]);

    $cheap = collect($this->getJson('/api/v1/products?filter[price_max]=1')->json('data'));
    expect($cheap)->toBeEmpty();
});

it('limits fields when requested', function () {
    $item = $this->getJson('/api/v1/products?fields=id,slug')->assertOk()->json('data.0');
    expect(array_keys($item))->toBe(['id', 'slug']);
});

it('never sends prices for products with hidden prices, even through filters or sorting', function () {
    $product = Product::published()->has('variants')->first();
    $product->update(['price_display' => 'hidden']);

    $detail = $this->getJson("/api/v1/products/{$product->slug}")->assertOk();
    expect($detail->json('data.price'))->toBeNull()
        ->and($detail->json('data.discount_percent'))->toBeNull();
    foreach ($detail->json('data.variants') as $variant) {
        expect($variant)->not->toHaveKeys(['price', 'compare_at_price', 'discount_percent']);
    }

    $byPrice = collect($this->getJson('/api/v1/products?sort=price&per_page=50')->json('data'))->pluck('slug');
    $filtered = collect($this->getJson('/api/v1/products?filter[price_min]=0&per_page=50')->json('data'))->pluck('slug');
    expect($byPrice)->not->toContain($product->slug)->and($filtered)->not->toContain($product->slug);
});

it('shows compare-at price only when higher than the selling price', function () {
    $product = Product::published()->has('variants')->with('variants')->first();
    $variant = $product->variants->first();
    $variant->update(['compare_at_price' => $variant->price - 1]);

    $data = collect($this->getJson("/api/v1/products/{$product->slug}")->json('data.variants'))->firstWhere('id', $variant->id);
    expect($data['compare_at_price'])->toBeNull()->and($data['discount_percent'])->toBeNull();
});

it('omits availability and rating when they are not shown on the page', function () {
    $product = Product::published()->first();
    $product->update(['availability' => null, 'rating_value' => null, 'rating_count' => null]);

    $data = $this->getJson("/api/v1/products/{$product->slug}")->json('data');
    expect($data)->not->toHaveKeys(['availability', 'rating']);
});

it('sends purchase links only as tracked go_url and records clicks', function () {
    $product = Product::published()->whereHas('links', fn ($q) => $q->where('url', 'like', 'http%'))->with('links')->first();
    $rawUrl = $product->links->firstWhere(fn ($l) => str_starts_with((string) $l->url, 'http'))->url;

    $detail = $this->getJson("/api/v1/products/{$product->slug}")->assertOk();
    expect($detail->getContent())->not->toContain(str_replace('/', '\/', $rawUrl))->not->toContain($rawUrl);

    $links = $this->getJson("/api/v1/products/{$product->slug}/links?quantity=3")->assertOk()->json('data');
    expect($links)->not->toBeEmpty();
    foreach ($links as $link) {
        expect($link['go_url'])->toStartWith(url('/go/'.$product->slug.'/'))->toContain('qty=3');
    }

    $marketplace = collect($links)->firstWhere('type', 'marketplace');
    $this->get($marketplace['go_url'])->assertRedirect();
    expect(ClickEvent::where('product_id', $product->id)->count())->toBe(1);

    $this->getJson("/api/v1/products/{$product->slug}/links?variant=tidak-ada")->assertStatus(422);
});

it('supports ETag, Last-Modified and 304 responses', function () {
    $first = $this->getJson('/api/v1/products')->assertOk();
    $etag = $first->headers->get('ETag');

    expect($etag)->not->toBeEmpty()
        ->and($first->headers->get('Cache-Control'))->toContain('public');

    $this->getJson('/api/v1/products', ['If-None-Match' => $etag])->assertStatus(304);

    $product = Product::published()->first();
    $detail = $this->getJson("/api/v1/products/{$product->slug}")->assertOk();
    expect($detail->headers->get('Last-Modified'))->not->toBeEmpty();
});

it('serves cached responses and clears them when content changes', function () {
    $product = Product::published()->first();

    $this->getJson("/api/v1/products/{$product->slug}")->assertHeader('X-Cache', 'MISS');
    $this->getJson("/api/v1/products/{$product->slug}")->assertHeader('X-Cache', 'HIT');

    $product->update(['name' => 'Nama Baru Produk']);

    $this->getJson("/api/v1/products/{$product->slug}")->assertHeader('X-Cache', 'MISS')
        ->assertJsonPath('data.name', 'Nama Baru Produk');
});

it('returns only published articles with sanitized HTML', function () {
    $draft = Article::query()->first();
    $draft->update(['published_at' => null]);
    $scheduled = Article::query()->whereKeyNot($draft->id)->first();
    $scheduled->update(['published_at' => now()->addDay()]);

    $slugs = collect($this->getJson('/api/v1/articles?per_page=50')->assertOk()->json('data'))->pluck('slug');
    expect($slugs)->not->toContain($draft->slug)->not->toContain($scheduled->slug);
    $this->getJson("/api/v1/articles/{$draft->slug}")->assertNotFound();

    $article = Article::published()->first();
    $article->update([
        'content' => '<p>Isi</p><script>alert(1)</script><img src="x.jpg" onerror="alert(2)"><a href="javascript:alert(3)">x</a>',
        'custom_js' => 'alert(4)',
    ]);

    $body = $this->getJson("/api/v1/articles/{$article->slug}")->assertOk()->getContent();
    expect($body)->not->toContain('<script')->not->toContain('onerror')->not->toContain('javascript:')->not->toContain('alert(4)');
});

it('exposes site branding read-only', function () {
    $this->getJson('/api/v1/site')->assertOk()
        ->assertJsonStructure(['data' => ['name', 'short_name', 'tagline', 'logo_url', 'favicon_url', 'colors' => ['primary', 'secondary', 'accent'], 'contact']]);

    $this->putJson('/api/v1/site', ['name' => 'Diubah'])->assertStatus(405)->assertJsonPath('error.code', 'method_not_allowed');
    $this->postJson('/api/v1/admin/site', ['name' => 'Diubah'])->assertStatus(405)->assertJsonPath('error.code', 'method_not_allowed');
});

it('serves categories, brands, channels, tags, authors and search', function () {
    $tree = $this->getJson('/api/v1/categories')->assertOk()->json('data');
    $semen = collect($tree)->firstWhere('slug', 'semen-perekat');
    expect(collect($semen['children'])->pluck('slug'))->toContain('mortar');

    $this->getJson('/api/v1/categories/semen-perekat')->assertOk()
        ->assertJsonStructure(['data' => ['slug', 'intro', 'bottom_content', 'breadcrumbs', 'children', 'product_count', 'articles']]);
    $this->getJson('/api/v1/brands')->assertOk()->assertJsonStructure(['data' => [['slug', 'name', 'product_count']]]);
    $this->getJson('/api/v1/sales-channels')->assertOk()->assertJsonFragment(['slug' => 'shopee']);
    $this->getJson('/api/v1/tags')->assertOk();

    $author = Author::active()->whereHas('publishedArticles')->first();
    if ($author) {
        $this->getJson("/api/v1/authors/{$author->slug}")->assertOk()->assertJsonStructure(['data' => ['slug', 'name', 'articles']]);
    }

    $this->getJson('/api/v1/search?q=semen')->assertOk()->assertJsonStructure(['data' => ['query', 'products', 'articles']]);
    $this->getJson('/api/v1/search?q=a')->assertStatus(422);
});

it('uses the API error format for unknown routes', function () {
    $this->getJson('/api/v1/tidak-ada')->assertNotFound()->assertJsonPath('error.code', 'not_found');
});

it('rate limits the public API with 429 and Retry-After', function () {
    config(['api.rate_limits.public' => 2]);
    RateLimiter::clear('public');

    $this->getJson('/api/v1/site')->assertOk()->assertHeader('X-RateLimit-Limit', 2);
    $this->getJson('/api/v1/site')->assertOk();
    $this->getJson('/api/v1/site')->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('error.code', 'too_many_requests');
});

it('keeps the API out of robots and sitemaps', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /api/');
    $this->get('/sitemap-pages.xml')->assertOk()->assertDontSee('/api/');
});

it('publishes public OpenAPI docs and restricts admin docs to admins', function () {
    $spec = $this->getJson('/api/docs.json')->assertOk()->json();
    expect($spec['openapi'])->toStartWith('3.1')
        ->and(array_keys($spec['paths']))->toContain('/products')
        ->and(collect(array_keys($spec['paths']))->filter(fn ($p) => str_starts_with($p, '/admin')))->toBeEmpty();

    $this->get('/api/docs/admin.json')->assertRedirect();

    $editor = User::factory()->create(['role' => 'editor']);
    $this->actingAs($editor)->get('/api/docs/admin.json')->assertForbidden();

    $admin = User::where('email', 'admin@supermaterial.test')->first();
    $adminSpec = $this->actingAs($admin)->getJson('/api/docs/admin.json')->assertOk()->json();
    expect(array_keys($adminSpec['paths']))->toContain('/admin/products');
});
