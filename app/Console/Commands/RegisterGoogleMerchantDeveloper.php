<?php

namespace App\Console\Commands;

use App\Support\GoogleMerchant\MerchantApiClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

/**
 * Langkah wajib sekali jalan sebelum Merchant API bisa dipakai: menautkan project Google Cloud
 * ke akun Merchant Center dan mendaftarkan email kontak teknis.
 */
class RegisterGoogleMerchantDeveloper extends Command
{
    protected $signature = 'merchant:register {email : Email Google kontak teknis (bukan email service account)}';

    protected $description = 'Daftarkan project Google Cloud ke akun Google Merchant Center (sekali saja)';

    public function handle(MerchantApiClient $client): int
    {
        if (! MerchantApiClient::isConfigured()) {
            $this->error('Google Merchant belum dikonfigurasi (GOOGLE_MERCHANT_ACCOUNT_ID, GOOGLE_MERCHANT_DATA_SOURCE_ID, file kredensial).');

            return self::FAILURE;
        }

        try {
            $projectIds = $client->registerDeveloper($this->argument('email'));
        } catch (RequestException $e) {
            $this->error('Registrasi ditolak Google: '.($e->response->json('error.message') ?? $e->getMessage()));

            return self::FAILURE;
        }

        $this->info('Project Google Cloud tertaut: '.implode(', ', $projectIds));
        $this->line('Merchant API bisa dipakai sekitar 5 menit lagi.');

        return self::SUCCESS;
    }
}
