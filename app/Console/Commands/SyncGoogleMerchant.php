<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\GoogleMerchant\MerchantApiClient;
use App\Support\GoogleMerchant\ProductSync;
use Illuminate\Console\Command;

/**
 * Sinkron penuh katalog ke Google Merchant Center: jaring pengaman harian untuk perubahan yang
 * tidak memicu sinkron langsung (ganti foto, diskon berakhir) sekaligus menyegarkan produk
 * sebelum kedaluwarsa 30 hari di Google.
 */
class SyncGoogleMerchant extends Command
{
    protected $signature = 'merchant:sync {--product= : Hanya sinkronkan produk dengan slug ini}';

    protected $description = 'Kirim produk katalog ke Google Merchant Center lewat Merchant API';

    public function handle(ProductSync $sync): int
    {
        if (! MerchantApiClient::isConfigured()) {
            $this->warn('Google Merchant belum dikonfigurasi (GOOGLE_MERCHANT_ACCOUNT_ID, GOOGLE_MERCHANT_DATA_SOURCE_ID, file kredensial).');

            return self::SUCCESS;
        }

        $query = Product::with(['variants', 'brand', 'category', 'media'])
            ->where(fn ($q) => $q->where('is_published', true)->orWhereNotNull('google_merchant_offer_ids'))
            ->when($this->option('product'), fn ($q, $slug) => $q->where('slug', $slug));

        $synced = 0;
        $failed = 0;
        $query->lazyById(50)->each(function (Product $product) use ($sync, &$synced, &$failed) {
            if ($sync->sync($product)) {
                $synced++;
            } else {
                $failed++;
                $this->error("Gagal: {$product->name} — {$product->google_merchant_error}");
            }
        });

        $this->info("{$synced} produk tersinkron, {$failed} gagal.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
