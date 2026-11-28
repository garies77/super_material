<?php

use App\Mcp\Servers\SupermaterialServer;
use App\Mcp\Tools\AddProductMedia;
use App\Mcp\Tools\CreateArticle;
use App\Mcp\Tools\RemoveProductMedia;
use App\Mcp\Tools\ReorderProductMedia;
use App\Mcp\Tools\UpdateArticle;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use App\Support\RemoteFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    // Nama host uji dianggap publik kecuali yang berawalan "internal"
    RemoteFile::resolveHostsUsing(fn (string $host) => str_starts_with($host, 'internal') ? ['10.0.0.5'] : ['93.184.216.34']);
});

afterEach(fn () => RemoteFile::resolveHostsUsing(null));

/** @param  array<int, string>  $abilities */
function mediaTokenUser(array $abilities = ['catalog:read', 'catalog:write', 'articles:read', 'articles:write']): User
{
    $user = User::factory()->create(['role' => 'editor']);

    return $user->withAccessToken($user->createToken('claude', $abilities, now()->addDays(90))->accessToken);
}

function pngBytes(int $width = 400, int $height = 400): string
{
    return UploadedFile::fake()->image('foto.png', $width, $height)->getContent();
}

it('offers the media tools only to tokens that can write the catalog', function () {
    $tools = [AddProductMedia::class, RemoveProductMedia::class, ReorderProductMedia::class];

    SupermaterialServer::actingAs(mediaTokenUser())->tools()->assertRegistered($tools);
    SupermaterialServer::actingAs(mediaTokenUser(['catalog:read']))->tools()->assertNotRegistered($tools);
});

it('downloads a product photo from a url and stores it like an admin upload', function () {
    Http::fake(['https://cdn.example.com/*' => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png'])]);
    $product = Product::create(['name' => 'Palu Kambing', 'slug' => 'palu-kambing']);

    SupermaterialServer::actingAs(mediaTokenUser())->tool(AddProductMedia::class, [
        'product' => 'palu-kambing', 'url' => 'https://cdn.example.com/palu.png', 'alt_text' => 'Palu kambing 16 oz',
    ])->assertOk()->assertStructuredContentPath('product.media.0.alt', 'Palu kambing 16 oz');

    $media = $product->media()->sole();
    expect($media->path)->toBe('products/palu-kambing-1.png')
        ->and(AuditLog::where('action', 'product.media_added')->exists())->toBeTrue();
    Storage::disk('public')->assertExists('products/palu-kambing-1.png');
});

it('refuses urls that are not public https or that redirect inside the network', function (string $url, string $message) {
    Http::fake([
        'https://cdn.example.com/pindah' => Http::response('', 302, ['Location' => 'https://internal.example.com/rahasia.png']),
        '*' => Http::response(pngBytes()),
    ]);
    $product = Product::create(['name' => 'Palu', 'slug' => 'palu']);

    SupermaterialServer::actingAs(mediaTokenUser())->tool(AddProductMedia::class, ['product' => 'palu', 'url' => $url, 'alt_text' => 'Palu'])
        ->assertHasErrors([$message]);

    expect($product->media()->count())->toBe(0);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'internal') || str_starts_with($request->url(), 'http:'));
})->with([
    'http biasa' => ['http://cdn.example.com/palu.png', 'Hanya URL https publik'],
    'host internal' => ['https://internal.example.com/palu.png', 'jaringan internal'],
    'IP privat langsung' => ['https://192.168.1.1/palu.png', 'jaringan internal'],
    'redirect ke host internal' => ['https://cdn.example.com/pindah', 'jaringan internal'],
    'port lain' => ['https://cdn.example.com:8443/palu.png', 'port selain 443'],
]);

it('rejects downloads that are too large or too small to be a product photo', function () {
    config(['api.uploads.image_max_kb' => 1]);
    Http::fake([
        'https://cdn.example.com/besar.png' => Http::response(str_repeat('x', 2048)),
        'https://cdn.example.com/kecil.png' => Http::response(pngBytes(100, 100)),
    ]);
    Product::create(['name' => 'Palu', 'slug' => 'palu']);

    SupermaterialServer::actingAs(mediaTokenUser())->tool(AddProductMedia::class, ['product' => 'palu', 'url' => 'https://cdn.example.com/besar.png', 'alt_text' => 'Palu'])
        ->assertHasErrors(['lebih besar dari 1 KB']);

    config(['api.uploads.image_max_kb' => 5120]);
    SupermaterialServer::actingAs(mediaTokenUser())->tool(AddProductMedia::class, ['product' => 'palu', 'url' => 'https://cdn.example.com/kecil.png', 'alt_text' => 'Palu'])
        ->assertHasErrors();

    expect(Product::firstWhere('slug', 'palu')->media()->count())->toBe(0);
});

it('reorders all product media and removes one by id', function () {
    $product = Product::create(['name' => 'Palu', 'slug' => 'palu']);
    $other = Product::create(['name' => 'Cetok', 'slug' => 'cetok']);
    [$first, $second, $third] = collect(['a', 'b', 'c'])->map(fn (string $name, int $i) => $product->media()->create([
        'type' => 'image', 'provider' => 'upload', 'path' => "products/{$name}.png", 'alt_text' => $name, 'sort_order' => $i,
    ]))->all();
    $foreign = $other->media()->create(['type' => 'image', 'provider' => 'upload', 'path' => 'products/x.png', 'alt_text' => 'x', 'sort_order' => 0]);

    SupermaterialServer::actingAs(mediaTokenUser())->tool(ReorderProductMedia::class, ['product' => 'palu', 'media_ids' => [$third->id, $first->id]])
        ->assertHasErrors(['harus berisi semua media']);

    SupermaterialServer::actingAs(mediaTokenUser())->tool(ReorderProductMedia::class, ['product' => 'palu', 'media_ids' => [$third->id, $first->id, $second->id]])
        ->assertOk();
    expect($product->media()->pluck('id')->all())->toBe([$third->id, $first->id, $second->id]);

    SupermaterialServer::actingAs(mediaTokenUser())->tool(RemoveProductMedia::class, ['product' => 'palu', 'media_id' => $foreign->id])
        ->assertHasErrors(['Media tidak ditemukan di produk ini']);
    SupermaterialServer::actingAs(mediaTokenUser())->tool(RemoveProductMedia::class, ['product' => 'palu', 'media_id' => $first->id])
        ->assertOk();

    expect($product->media()->pluck('id')->all())->toBe([$third->id, $second->id])
        ->and($foreign->fresh())->not->toBeNull()
        ->and(AuditLog::whereIn('action', ['product.media_reordered', 'product.media_removed'])->count())->toBe(2);
});

it('sets the article cover image from a url on create and update', function () {
    Http::fake(['https://cdn.example.com/*' => Http::response(pngBytes(1200, 675), 200, ['Content-Type' => 'image/png'])]);

    SupermaterialServer::actingAs(mediaTokenUser())->tool(CreateArticle::class, [
        'title' => 'Cara Pasang Dynabolt', 'content' => '<p>Isi</p>', 'image_url' => 'https://cdn.example.com/sampul.png',
    ])->assertOk();

    $article = Article::firstWhere('slug', 'cara-pasang-dynabolt');
    expect($article->image)->toStartWith('articles/cara-pasang-dynabolt');
    Storage::disk('public')->assertExists($article->image);

    // Gambar gagal: artikel tidak berubah sama sekali
    SupermaterialServer::actingAs(mediaTokenUser())->tool(UpdateArticle::class, [
        'article' => $article->slug, 'title' => 'Judul Baru', 'image_url' => 'https://internal.example.com/x.png',
    ])->assertHasErrors(['jaringan internal']);
    expect($article->refresh()->title)->toBe('Cara Pasang Dynabolt');

    Http::fake(['https://cdn2.example.com/*' => Http::response(pngBytes(1200, 676), 200, ['Content-Type' => 'image/png'])]);
    $oldImage = $article->image;
    SupermaterialServer::actingAs(mediaTokenUser())->tool(UpdateArticle::class, [
        'article' => $article->slug, 'image_url' => 'https://cdn2.example.com/sampul-baru.png',
    ])->assertOk()->assertStructuredContentPath('changed', ['image']);

    expect($article->refresh()->image)->not->toBe($oldImage);
});
