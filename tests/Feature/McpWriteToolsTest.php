<?php

use App\Mcp\Servers\SupermaterialServer;
use App\Mcp\Tools\CreateProduct;
use App\Mcp\Tools\ReplaceProductVariants;
use App\Mcp\Tools\SetProductLinks;
use App\Mcp\Tools\UpdateProduct;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

/** Pengguna yang memakai token API dengan ability tertentu, seperti saat datang lewat /mcp. */
function mcpWriter(string $role = 'editor', array $abilities = ['catalog:read', 'catalog:write']): User
{
    $user = User::factory()->create(['role' => $role]);

    return $user->withAccessToken($user->createToken('claude', $abilities, now()->addDays(90))->accessToken);
}

it('offers write tools only when both the token and the role allow catalog:write', function () {
    $writeTools = [CreateProduct::class, UpdateProduct::class, ReplaceProductVariants::class, SetProductLinks::class];

    SupermaterialServer::actingAs(mcpWriter())->tools()->assertRegistered($writeTools);

    // Token hanya baca: tool tulis tidak terdaftar
    SupermaterialServer::actingAs(mcpWriter('admin', ['catalog:read']))->tools()->assertNotRegistered($writeTools);

    // Peran Penulis tidak boleh menulis katalog walau token-nya punya catalog:write
    SupermaterialServer::actingAs(mcpWriter('author', ['catalog:write', 'articles:read']))
        ->tool(CreateProduct::class, ['name' => 'Palu'])
        ->assertNotRegistered();
});

it('creates a product as a draft by default with variants and links, and audits it as mcp', function () {
    $user = mcpWriter();

    SupermaterialServer::actingAs($user)->tool(CreateProduct::class, [
        'name' => 'Cetok Stainless Uji',
        'category' => 'semen-perekat',
        'unit' => 'buah',
        'variants' => [['name' => 'Ukuran 14', 'price' => 13100], ['name' => 'Ukuran 17', 'price' => 15500]],
        'links' => [['channel' => 'shopee', 'url' => 'https://shopee.co.id/cetok-uji']],
    ])
        ->assertOk()
        ->assertStructuredContentPath('slug', 'cetok-stainless-uji')
        ->assertStructuredContentPath('status', 'draft');

    $product = Product::where('slug', 'cetok-stainless-uji')->sole();
    expect($product->is_published)->toBeFalse()
        ->and($product->variants()->count())->toBe(2)
        ->and($product->links()->sole()->url)->toBe('https://shopee.co.id/cetok-uji');

    $log = AuditLog::where('action', 'product.created')->sole();
    expect($log->source)->toBe('mcp')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->token_id)->not->toBeNull()
        ->and($log->subject_id)->toBe($product->id);
});

it('publishes immediately only when asked explicitly', function () {
    SupermaterialServer::actingAs(mcpWriter())->tool(CreateProduct::class, ['name' => 'Palu Uji', 'status' => 'published'])
        ->assertOk()
        ->assertStructuredContentPath('status', 'published');

    expect(Product::where('slug', 'palu-uji')->sole()->is_published)->toBeTrue();
});

it('rejects invalid products entirely without saving anything', function () {
    $before = Product::count();

    SupermaterialServer::actingAs(mcpWriter())->tool(CreateProduct::class, ['unit' => 'buah'])
        ->assertHasErrors();

    SupermaterialServer::actingAs(mcpWriter())->tool(CreateProduct::class, [
        'name' => 'Produk Tautan Salah',
        'links' => [['channel' => 'shopee', 'url' => 'https://contoh-palsu.com/produk']],
    ])->assertHasErrors(['tidak diizinkan untuk kanal Shopee']);

    expect(Product::count())->toBe($before)->and(AuditLog::where('action', 'product.created')->exists())->toBeFalse();
});

it('updates only the fields sent, by id or slug, and records the changes', function () {
    $product = Product::query()->first();
    $originalName = $product->name;

    SupermaterialServer::actingAs(mcpWriter())->tool(UpdateProduct::class, ['product' => $product->slug, 'badge' => 'Best Seller', 'price_min' => 10000, 'price_max' => 12000])
        ->assertOk()
        ->assertStructuredContentPath('product.badge', 'Best Seller');

    $product->refresh();
    expect($product->name)->toBe($originalName)
        ->and($product->badge)->toBe('Best Seller')
        ->and($product->price_max)->toBe(12000);

    $changes = AuditLog::where('action', 'product.updated')->sole()->changes;
    expect($changes)->toHaveKeys(['badge', 'price_min', 'price_max'])->and($changes)->not->toHaveKey('name');

    // price_max dibandingkan dengan price_min tersimpan bila price_min tidak dikirim
    SupermaterialServer::actingAs(mcpWriter())->tool(UpdateProduct::class, ['product' => (string) $product->id, 'price_max' => 5000])
        ->assertHasErrors();
    expect($product->refresh()->price_max)->toBe(12000);

    SupermaterialServer::actingAs(mcpWriter())->tool(UpdateProduct::class, ['product' => 'tidak-ada', 'badge' => 'X'])
        ->assertHasErrors(['Produk tidak ditemukan.']);

    SupermaterialServer::actingAs(mcpWriter())->tool(UpdateProduct::class, ['product' => $product->slug])
        ->assertHasErrors(['Tidak ada kolom yang dikirim']);
});

it('replaces variants and links as whole lists and rejects links outside the channel domain', function () {
    $product = Product::query()->has('links')->first();
    $user = mcpWriter();

    SupermaterialServer::actingAs($user)->tool(ReplaceProductVariants::class, ['product' => $product->slug, 'variants' => [['name' => 'Kotak isi 6', 'price' => 64000]]])
        ->assertOk();
    expect($product->variants()->pluck('name')->all())->toBe(['Kotak isi 6']);

    SupermaterialServer::actingAs($user)->tool(SetProductLinks::class, ['product' => $product->slug, 'links' => [
        ['channel' => 'lazada', 'url' => 'https://www.lazada.co.id/products/pdp-i1.html'],
        ['channel' => 'tokopedia', 'url' => 'https://www.tokopedia.com/toko/produk', 'is_active' => false],
    ]])->assertOk();

    expect($product->links()->with('channel')->get()->mapWithKeys(fn ($l) => [$l->channel->slug => $l->is_active])->all())
        ->toEqualCanonicalizing(['lazada' => true, 'tokopedia' => false]);

    SupermaterialServer::actingAs($user)->tool(SetProductLinks::class, ['product' => $product->slug, 'links' => [
        ['channel' => 'shopee', 'url' => 'https://tokopedia.com/salah-kanal'],
    ]])->assertHasErrors(['tidak diizinkan untuk kanal Shopee']);

    expect($product->links()->count())->toBe(2)
        ->and(AuditLog::where('source', 'mcp')->pluck('action')->all())->toEqualCanonicalizing(['product.variants_replaced', 'product.links_replaced']);
});
