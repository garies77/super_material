<?php

use App\Models\Product;
use App\Support\GoogleMerchant\ProductSync;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

/**
 * @param  array<string, mixed>  $merchantBody
 */
function fakeGoogle(int $merchantStatus = 200, array $merchantBody = []): void
{
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'merchantapi.googleapis.com/*' => Http::response($merchantBody, $merchantStatus),
    ]);
}

/**
 * Aktifkan sinkron dengan kunci service account sementara (dibuat saat tes, tidak pernah disimpan di repo).
 */
function configureGoogleMerchant(): void
{
    $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
    $windowsConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
    if (is_file($windowsConfig)) {
        $options['config'] = $windowsConfig;
    }

    openssl_pkey_export(openssl_pkey_new($options), $privateKey, null, $options);

    $path = tempnam(sys_get_temp_dir(), 'gmc');
    file_put_contents($path, json_encode([
        'type' => 'service_account',
        'client_email' => 'sync@supermaterial.iam.gserviceaccount.com',
        'private_key' => $privateKey,
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ]));

    config()->set('services.google_merchant', [
        'account_id' => '5902937861',
        'data_source_id' => '10793704472',
        'credentials' => $path,
        'content_language' => 'id',
        'feed_label' => 'ID',
    ]);
}

/**
 * @return array<int, Request>
 */
function merchantRequests(string $method): array
{
    return Http::recorded(fn (Request $request) => str_contains($request->url(), 'merchantapi.googleapis.com')
        && $request->method() === $method)->map(fn ($pair) => $pair[0])->all();
}

it('sends every active variant of a published product as a grouped offer with its sale price', function () {
    configureGoogleMerchant();
    fakeGoogle();

    $this->artisan('merchant:sync', ['--product' => 'semen-tiga-roda-50-kg'])->assertSuccessful();

    $inserts = collect(merchantRequests('POST'));
    expect($inserts)->toHaveCount(2)
        ->and($inserts->first()->url())->toContain('/accounts/5902937861/productInputs:insert')
        ->toContain('dataSource=accounts%2F5902937861%2FdataSources%2F10793704472')
        ->and($inserts->first()->header('Authorization')[0])->toBe('Bearer test-token');

    $offer = $inserts->firstWhere(fn (Request $request) => $request['offerId'] === 'STR-40');
    expect($offer['contentLanguage'])->toBe('id')
        ->and($offer['feedLabel'])->toBe('ID')
        ->and($offer['productAttributes'])->toMatchArray([
            'title' => 'Semen Tiga Roda 50 kg - Sak 40 kg',
            'itemGroupId' => Product::where('slug', 'semen-tiga-roda-50-kg')->value('sku') ?: 'semen-tiga-roda-50-kg',
            'price' => ['amountMicros' => '58000000000', 'currencyCode' => 'IDR'],
            'salePrice' => ['amountMicros' => '52000000000', 'currencyCode' => 'IDR'],
            'condition' => 'NEW',
            'brand' => 'Tiga Roda',
        ])
        ->and($offer['productAttributes']['link'])->toEndWith('/produk/semen-tiga-roda-50-kg?varian=sak-40-kg');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'oauth2.googleapis.com')
        && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
        && substr_count($request['assertion'], '.') === 2);

    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->first();
    expect($product->google_merchant_offer_ids)->toEqualCanonicalizing(['STR-40', 'STR-50'])
        ->and($product->google_merchant_synced_at)->not->toBeNull()
        ->and($product->google_merchant_error)->toBeNull();
});

it('removes offers from Google once the product price is hidden', function () {
    configureGoogleMerchant();
    fakeGoogle();
    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->first();
    $product->updateQuietly(['google_merchant_offer_ids' => ['STR-40', 'STR-50']]);

    $product->update(['price_display' => 'hidden']);
    app(ProductSync::class)->flush();

    expect(merchantRequests('POST'))->toBeEmpty()
        ->and(collect(merchantRequests('DELETE'))->map->url()->implode(' '))
        ->toContain('/productInputs/'.rtrim(strtr(base64_encode('id~ID~STR-40'), '+/', '-_'), '='))
        ->toContain('/productInputs/'.rtrim(strtr(base64_encode('id~ID~STR-50'), '+/', '-_'), '='))
        ->and($product->fresh()->google_merchant_offer_ids)->toBeNull();
});

it('records the Merchant API error on the product when Google rejects it', function () {
    configureGoogleMerchant();
    fakeGoogle(400, ['error' => ['message' => 'Invalid data source']]);

    $this->artisan('merchant:sync', ['--product' => 'semen-tiga-roda-50-kg'])->assertFailed();

    expect(Product::where('slug', 'semen-tiga-roda-50-kg')->value('google_merchant_error'))->toContain('Invalid data source');
});

it('does not contact Google while the integration is not configured', function () {
    fakeGoogle();
    config()->set('services.google_merchant.account_id', null);

    Product::where('slug', 'semen-tiga-roda-50-kg')->first()->update(['name' => 'Semen Tiga Roda 50 kg Baru']);
    app(ProductSync::class)->flush();

    Http::assertNothingSent();
});
