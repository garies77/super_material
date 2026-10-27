<?php

use App\Filament\Resources\ApiTokens\Pages\ManageApiTokens;
use App\Models\ApiToken;
use App\Models\AuditLog;
use App\Models\ClickEvent;
use App\Models\Media;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\SalesChannel;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

function apiToken(string $role = 'admin', array $abilities = ['catalog:read', 'catalog:write', 'import:write', 'reports:read'], array $userAttrs = []): string
{
    $user = User::factory()->create(['role' => $role] + $userAttrs);

    return $user->createToken('test', $abilities, now()->addDays(90))->plainTextToken;
}

function validProductPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Rangka Baja Ringan C75',
        'category' => 'besi-baja',
        'brand' => 'krakatau-steel',
        'summary' => 'Ringkasan produk.',
        'variants' => [
            ['name' => 'C75 0,70 mm', 'sku' => 'C75-070', 'price' => 28000, 'compare_at_price' => 70000,
                'discount_starts_at' => now()->subDay()->toDateTimeString(), 'discount_ends_at' => now()->addWeek()->toDateTimeString()],
        ],
        'links' => [
            ['channel' => 'shopee', 'url' => 'https://shopee.co.id/rangka-c75'],
            ['channel' => 'whatsapp'],
        ],
    ], $overrides);
}

it('rejects requests without a token or with an invalid token', function () {
    $this->getJson('/api/v1/admin/products')->assertStatus(401)->assertJsonPath('error.code', 'unauthenticated');
    $this->withToken('1|salah')->getJson('/api/v1/admin/products')->assertStatus(401);
});

it('rejects expired tokens', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $token = $user->createToken('lama', ['catalog:read'], now()->subMinute())->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/products')->assertStatus(401);
});

it('rejects tokens of deactivated users', function () {
    $token = apiToken('admin', ['catalog:read'], ['is_active' => false]);

    $this->withToken($token)->getJson('/api/v1/admin/products')->assertStatus(401);
});

it('returns 403 when the token lacks the ability', function () {
    $token = apiToken('admin', ['catalog:read']);

    $this->withToken($token)->postJson('/api/v1/admin/products', validProductPayload())
        ->assertStatus(403)->assertJsonPath('error.code', 'insufficient_ability');
    expect(Product::where('name', 'Rangka Baja Ringan C75')->exists())->toBeFalse();
});

it('returns 403 when the user role does not allow the ability, even if the token has it', function () {
    $token = apiToken('author', ['catalog:read', 'catalog:write']);

    $this->withToken($token)->getJson('/api/v1/admin/products')
        ->assertStatus(403)->assertJsonPath('error.code', 'insufficient_role');

    app('auth')->forgetGuards();
    $editor = apiToken('editor', ['reports:read']);
    $this->withToken($editor)->getJson('/api/v1/admin/reports/clicks')->assertStatus(403)->assertJsonPath('error.code', 'insufficient_role');
});

it('demoting a user removes access immediately', function () {
    $user = User::factory()->create(['role' => 'editor']);
    $token = $user->createToken('t', ['catalog:read'])->plainTextToken;
    $user->update(['role' => 'author']);

    $this->withToken($token)->getJson('/api/v1/admin/products')->assertStatus(403);
});

it('lists products of every status for admins', function () {
    $draft = Product::query()->first();
    $draft->update(['is_published' => false]);

    $response = $this->withToken(apiToken())->getJson('/api/v1/admin/products?filter[status]=draft&per_page=100')->assertOk();

    expect(collect($response->json('data'))->pluck('slug')->all())->toBe([$draft->slug])
        ->and($response->json('data.0.status'))->toBe('draft');

    $this->withToken(apiToken())->getJson('/api/v1/admin/products?per_page=101')->assertStatus(422);
});

it('creates a published product with variants and links, writing an audit log', function () {
    $response = $this->withToken(apiToken())->postJson('/api/v1/admin/products', validProductPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.slug', 'rangka-baja-ringan-c75')
        ->assertJsonPath('data.variants.0.discount_percent', 60);

    $product = Product::findOrFail($response->json('data.id'));
    expect($product->links()->count())->toBe(2);

    $log = AuditLog::where('action', 'product.created')->first();
    expect($log)->not->toBeNull()
        ->and($log->subject_id)->toBe($product->id)
        ->and($log->token_id)->not->toBeNull()
        ->and($log->ip_hash)->not->toBe('127.0.0.1');

    $this->getJson('/api/v1/products/rangka-baja-ringan-c75')->assertOk();
});

it('saves drafts only when status=draft is sent explicitly', function () {
    $this->withToken(apiToken())->postJson('/api/v1/admin/products', validProductPayload(['status' => 'draft']))
        ->assertCreated()->assertJsonPath('data.status', 'draft');

    $this->getJson('/api/v1/products/rangka-baja-ringan-c75')->assertNotFound();
});

it('rejects invalid data entirely with 422 and saves nothing', function (array $override, string $field) {
    $payload = validProductPayload();
    data_set($payload, array_key_first($override), $override[array_key_first($override)]);

    $this->withToken(apiToken())->postJson('/api/v1/admin/products', $payload)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['fields' => [$field]]]);

    expect(Product::where('name', 'Rangka Baja Ringan C75')->exists())->toBeFalse();
})->with([
    'harga coret tidak lebih besar' => [['variants.0.compare_at_price' => 28000], 'variants.0.compare_at_price'],
    'tanggal diskon kosong' => [['variants.0.discount_ends_at' => null], 'variants.0.discount_ends_at'],
    'slug sudah dipakai' => [['slug' => 'semen-tiga-roda-50-kg'], 'slug'],
    'domain kanal tidak diizinkan' => [['links.0.url' => 'https://evil.example.com/x'], 'links.0.url'],
    'kategori tidak ada' => [['category' => 'tidak-ada'], 'category'],
]);

it('does not create duplicates when the same Idempotency-Key is retried', function () {
    $token = apiToken();
    $headers = ['Idempotency-Key' => 'n8n-run-42'];
    $payload = validProductPayload();

    $first = $this->withToken($token)->postJson('/api/v1/admin/products', $payload, $headers)->assertCreated();
    $second = $this->withToken($token)->postJson('/api/v1/admin/products', $payload, $headers)->assertCreated();

    expect($second->headers->get('Idempotent-Replayed'))->toBe('true')
        ->and($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Product::where('name', 'Rangka Baja Ringan C75')->count())->toBe(1);

    $this->withToken($token)->postJson('/api/v1/admin/products', validProductPayload(['name' => 'Lain']), $headers)
        ->assertStatus(409)->assertJsonPath('error.code', 'idempotency_key_reused');
});

it('updates a product, creating a 301 redirect when the slug changes', function () {
    $product = Product::published()->first();
    $oldPath = '/produk/'.$product->slug;

    $this->withToken(apiToken())->patchJson("/api/v1/admin/products/{$product->id}", ['slug' => 'slug-baru-produk', 'price_max' => 999999])
        ->assertOk()->assertJsonPath('data.slug', 'slug-baru-produk');

    expect(Redirect::where('from_path', $oldPath)->value('to_path'))->toBe('/produk/slug-baru-produk');
    $this->get($oldPath)->assertRedirect('/produk/slug-baru-produk')->assertStatus(301);
    expect(AuditLog::where('action', 'product.updated')->first()->changes)->toHaveKey('slug');

    $this->withToken(apiToken())->patchJson("/api/v1/admin/products/{$product->id}", ['price_max' => 1, 'price_min' => 5])->assertStatus(422);
});

it('replaces variants and links', function () {
    $product = Product::published()->has('variants', '>=', 2)->first();
    $token = apiToken();

    $this->withToken($token)->putJson("/api/v1/admin/products/{$product->id}/variants", ['variants' => [
        ['name' => 'Ukuran A', 'price' => 10000],
    ]])->assertOk()->assertJsonCount(1, 'data.variants')->assertJsonPath('data.variants.0.slug', 'ukuran-a');

    $this->withToken($token)->putJson("/api/v1/admin/products/{$product->id}/variants", ['variants' => [
        ['name' => 'Ukuran A', 'price' => 10000], ['name' => 'Ukuran A', 'price' => 12000],
    ]])->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['variants.1.slug']]]);

    $this->withToken($token)->putJson("/api/v1/admin/products/{$product->id}/links", ['links' => [
        ['channel' => 'tokopedia', 'url' => 'https://www.tokopedia.com/toko/produk'],
    ]])->assertOk()->assertJsonCount(1, 'data.links')->assertJsonPath('data.links.0.channel', 'tokopedia');

    $this->withToken($token)->putJson("/api/v1/admin/products/{$product->id}/links", ['links' => [
        ['channel' => 'tokopedia', 'url' => 'javascript:alert(1)'],
    ]])->assertStatus(422);
});

it('uploads images through the same pipeline and enforces alt text and minimum pixels', function () {
    Storage::fake('public');
    $product = Product::published()->first();
    $token = apiToken();

    $this->withToken($token)->post("/api/v1/admin/products/{$product->id}/media", [
        'file' => UploadedFile::fake()->image('IMG00023.JPG', 100, 100),
        'alt_text' => 'Tampak depan',
    ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['file']]]);

    $this->withToken($token)->post("/api/v1/admin/products/{$product->id}/media", [
        'file' => UploadedFile::fake()->image('IMG00023.JPG', 400, 400),
    ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['alt_text']]]);

    $response = $this->withToken($token)->post("/api/v1/admin/products/{$product->id}/media", [
        'file' => UploadedFile::fake()->image('IMG00023.JPG', 400, 400),
        'alt_text' => 'Tampak depan',
    ], ['Accept' => 'application/json'])->assertCreated();

    expect($response->json('data.url'))->toContain("/products/{$product->slug}-1.jpg")
        ->and($response->json('data.alt'))->toBe('Tampak depan');

    $this->withToken($token)->postJson("/api/v1/admin/products/{$product->id}/media", [
        'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'alt_text' => 'Video pemasangan',
    ])->assertCreated()->assertJsonPath('data.provider', 'youtube');
});

it('reuses an identical image uploaded through the API and registers it in the media library', function () {
    Storage::fake('public');
    $product = Product::published()->first();
    $token = apiToken();
    $image = UploadedFile::fake()->image('foto.jpg', 400, 400);

    foreach (['Tampak depan', 'Tampak depan lagi'] as $alt) {
        $this->withToken($token)->post("/api/v1/admin/products/{$product->id}/media", ['file' => $image, 'alt_text' => $alt],
            ['Accept' => 'application/json'])->assertCreated();
    }

    $uploaded = collect(Storage::disk('public')->files('products'))->filter(fn (string $path) => str_starts_with($path, "products/{$product->slug}-"));

    expect($product->media()->whereIn('alt_text', ['Tampak depan', 'Tampak depan lagi'])->pluck('path')->unique()->all())->toBe(["products/{$product->slug}-1.jpg"])
        ->and($uploaded->values()->all())->toBe(["products/{$product->slug}-1.jpg"])
        ->and(Media::pluck('path')->all())->toBe(["products/{$product->slug}-1.jpg"]);
});

it('imports CSV through the queue and reports failed rows', function () {
    $csv = "nama,kategori,merek,harga_min,harga_max,satuan,shopee_url\n"
        ."Semen Impor Baru,Semen & Perekat,Tiga Roda,50000,60000,sak,https://shopee.co.id/x\n"
        ."Produk Rusak,,,90000,1000,pcs,\n"
        ."Produk Domain Asing,,,1000,2000,pcs,https://evil.example.com/x\n";

    $response = $this->withToken(apiToken())->post('/api/v1/admin/imports', [
        'file' => UploadedFile::fake()->createWithContent('produk.csv', $csv),
    ], ['Accept' => 'application/json'])->assertCreated();

    $id = $response->json('data.id');
    $status = $this->withToken(apiToken())->getJson("/api/v1/admin/imports/{$id}")->assertOk();

    expect($status->json('data.status'))->toBe('completed')
        ->and($status->json('data.created_count'))->toBe(1)
        ->and($status->json('data.failed_count'))->toBe(2)
        ->and(collect($status->json('data.failed_rows'))->pluck('row')->all())->toBe([3, 4])
        ->and(Product::where('slug', 'semen-impor-baru')->value('is_published'))->toBeTrue();
});

it('imports Excel files', function () {
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>nama</t></si><si><t>harga_min</t></si><si><t>Produk Excel</t></si></sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
        .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>'
        .'<row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2"><v>15000</v></c></row>'
        .'</sheetData></worksheet>');
    $zip->close();

    $this->withToken(apiToken())->post('/api/v1/admin/imports', [
        'file' => new UploadedFile($path, 'produk.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
    ], ['Accept' => 'application/json'])->assertCreated();

    expect(Product::where('slug', 'produk-excel')->value('price_min'))->toBe(15000);
});

it('reports aggregated clicks and exports CSV', function () {
    $product = Product::published()->first();
    $channel = SalesChannel::where('slug', 'shopee')->first();
    foreach ([false, false, true] as $isBot) {
        ClickEvent::create([
            'product_id' => $product->id, 'sales_channel_id' => $channel->id, 'is_bot' => $isBot,
            'page_url' => url('/artikel/cara-memilih-semen-untuk-rumah'), 'created_at' => now(),
        ]);
    }
    $token = apiToken();

    $byProduct = $this->withToken($token)->getJson('/api/v1/admin/reports/clicks')->assertOk();
    expect($byProduct->json('data.0'))->toBe(['product_id' => $product->id, 'slug' => $product->slug, 'name' => $product->name, 'clicks' => 2])
        ->and($byProduct->json('meta.total_clicks'))->toBe(2);

    $this->withToken($token)->getJson('/api/v1/admin/reports/clicks?group_by=article')->assertOk()
        ->assertJsonPath('data.0.slug', 'cara-memilih-semen-untuk-rumah')->assertJsonPath('data.0.clicks', 2);
    $this->withToken($token)->getJson('/api/v1/admin/reports/clicks?group_by=channel&filter[include_bots]=1')->assertOk()
        ->assertJsonPath('data.0.clicks', 3);
    $this->withToken($token)->getJson('/api/v1/admin/reports/clicks?group_by=day')->assertOk()->assertJsonPath('data.0.clicks', 2);
    $this->withToken($token)->getJson('/api/v1/admin/reports/clicks?group_by=email')->assertStatus(422);

    $csv = $this->withToken($token)->get('/api/v1/admin/reports/clicks/export?group_by=category')->assertOk();
    expect($csv->headers->get('Content-Type'))->toContain('text/csv')
        ->and($csv->streamedContent())->toContain('slug,name,clicks');
});

it('rate limits admin tokens with 429', function () {
    config(['api.rate_limits.admin' => 1]);
    $token = apiToken();
    RateLimiter::clear('admin|'.ApiToken::latest('id')->value('id'));

    $this->withToken($token)->getJson('/api/v1/admin/products')->assertOk();
    $this->withToken($token)->getJson('/api/v1/admin/products')->assertStatus(429)->assertHeader('Retry-After');
});

it('lets admins manage API tokens in Filament but not editors', function () {
    $admin = User::where('email', 'admin@supermaterial.test')->first();
    $this->actingAs($admin)->get('/admin/api-tokens')->assertOk();
});

it('keeps editors out of API token management', function () {
    $this->actingAs(User::factory()->create(['role' => 'editor']))->get('/admin/api-tokens')->assertForbidden();
});

it('requires password confirmation for write tokens in Filament', function () {
    $admin = User::where('email', 'admin@supermaterial.test')->first();
    $this->actingAs($admin);

    Livewire::test(ManageApiTokens::class)
        ->callAction('create', ['user_id' => $admin->id, 'name' => 'n8n', 'abilities' => ['catalog:write'], 'expires_at' => now()->addDays(90)->toDateString(), 'password' => 'salah'])
        ->assertHasActionErrors(['password']);
    expect($admin->tokens()->count())->toBe(0);

    Livewire::test(ManageApiTokens::class)
        ->callAction('create', ['user_id' => $admin->id, 'name' => 'n8n baca', 'abilities' => ['catalog:read'], 'expires_at' => null])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $token = $admin->tokens()->first();
    expect($token->abilities)->toBe(['catalog:read'])
        ->and($token->expires_at)->toBeNull()
        ->and($token->getAttributes())->not->toHaveKey('plain_token');
});
