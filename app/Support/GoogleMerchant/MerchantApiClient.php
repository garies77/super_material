<?php

namespace App\Support\GoogleMerchant;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien tipis Google Merchant API (products v1) dengan autentikasi service account.
 * Token akses dibuat dari JWT RS256 yang ditandatangani OpenSSL, tanpa library Google.
 */
class MerchantApiClient
{
    private const BASE_URL = 'https://merchantapi.googleapis.com/products/v1';

    private const SCOPE = 'https://www.googleapis.com/auth/content';

    private const TOKEN_CACHE_KEY = 'google-merchant:access-token';

    public static function isConfigured(): bool
    {
        $config = config('services.google_merchant');

        return filled($config['account_id'] ?? null)
            && filled($config['data_source_id'] ?? null)
            && is_file((string) ($config['credentials'] ?? ''));
    }

    /**
     * Kirim (atau timpa) satu product input ke sumber data API.
     *
     * @param  array<string, mixed>  $productInput
     */
    public function insert(array $productInput): void
    {
        Http::withToken($this->accessToken())
            ->acceptJson()
            ->post($this->accountUrl('productInputs:insert').'?'.$this->dataSourceQuery(), $productInput)
            ->throw();
    }

    /**
     * Hapus product input berdasarkan offer ID; produk yang sudah tidak ada di Google dianggap berhasil.
     */
    public function delete(string $offerId): void
    {
        $config = config('services.google_merchant');
        $name = $this->base64UrlEncode($config['content_language'].'~'.$config['feed_label'].'~'.$offerId);

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->delete($this->accountUrl('productInputs/'.$name).'?'.$this->dataSourceQuery());

        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    private function accountUrl(string $path): string
    {
        return self::BASE_URL.'/accounts/'.config('services.google_merchant.account_id').'/'.$path;
    }

    private function dataSourceQuery(): string
    {
        $config = config('services.google_merchant');

        return http_build_query(['dataSource' => 'accounts/'.$config['account_id'].'/dataSources/'.$config['data_source_id']]);
    }

    private function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function (): string {
            $credentials = $this->credentials();

            return Http::asForm()
                ->post($credentials['token_uri'], [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $this->signedAssertion($credentials),
                ])
                ->throw()
                ->json('access_token');
        });
    }

    /**
     * @return array{client_email: string, private_key: string, token_uri: string}
     */
    private function credentials(): array
    {
        $json = json_decode((string) file_get_contents(config('services.google_merchant.credentials')), true);

        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new RuntimeException('File kredensial Google Merchant bukan kunci service account yang valid.');
        }

        return [
            'client_email' => $json['client_email'],
            'private_key' => $json['private_key'],
            'token_uri' => $json['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        ];
    }

    /**
     * @param  array{client_email: string, private_key: string, token_uri: string}  $credentials
     */
    private function signedAssertion(array $credentials): string
    {
        $now = time();
        $segments = [
            $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64UrlEncode(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];

        if (! openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Gagal menandatangani token Google Merchant dengan kunci service account.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
