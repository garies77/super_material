<?php

namespace App\Support\GoogleMerchant;

use App\Models\Product;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menyinkronkan produk ke Google Merchant Center.
 * Perubahan selama satu request dikumpulkan lalu dikirim sekali setelah respons terkirim,
 * sehingga admin tidak menunggu Google dan produk + varian yang disimpan bersamaan cukup dikirim sekali.
 */
class ProductSync
{
    /** @var array<int, true> */
    private array $pendingProductIds = [];

    /** @var array<string, true> */
    private array $pendingRemovals = [];

    private bool $flushRegistered = false;

    public function __construct(
        private MerchantApiClient $client,
        private ProductInputMapper $mapper,
    ) {}

    public function queue(int $productId): void
    {
        if (! MerchantApiClient::isConfigured()) {
            return;
        }

        $this->pendingProductIds[$productId] = true;
        $this->registerFlush();
    }

    /**
     * @param  array<int, string>  $offerIds
     */
    public function queueRemoval(array $offerIds): void
    {
        if (! MerchantApiClient::isConfigured() || $offerIds === []) {
            return;
        }

        foreach ($offerIds as $offerId) {
            $this->pendingRemovals[$offerId] = true;
        }
        $this->registerFlush();
    }

    public function flush(): void
    {
        $productIds = array_keys($this->pendingProductIds);
        $removals = array_keys($this->pendingRemovals);
        $this->pendingProductIds = [];
        $this->pendingRemovals = [];

        foreach ($removals as $offerId) {
            try {
                $this->client->delete($offerId);
            } catch (Throwable $e) {
                report($e);
            }
        }

        Product::with(['variants', 'brand', 'category', 'media'])
            ->whereKey($productIds)
            ->get()
            ->each(fn (Product $product) => $this->sync($product));
    }

    /**
     * Kirim offer yang layak dan hapus offer lama yang sudah tidak ada (varian dihapus, produk disembunyikan).
     */
    public function sync(Product $product): bool
    {
        $inputs = $this->mapper->map($product);
        $previousOfferIds = (array) ($product->google_merchant_offer_ids ?? []);

        try {
            foreach ($inputs as $input) {
                $this->client->insert($input);
            }

            foreach (array_diff($previousOfferIds, array_keys($inputs)) as $staleOfferId) {
                $this->client->delete($staleOfferId);
            }
        } catch (Throwable $e) {
            report($e);
            $product->updateQuietly(['google_merchant_error' => Str::limit($e->getMessage(), 1000)]);

            return false;
        }

        $product->updateQuietly([
            'google_merchant_offer_ids' => array_keys($inputs) ?: null,
            'google_merchant_synced_at' => now(),
            'google_merchant_error' => null,
        ]);

        return true;
    }

    private function registerFlush(): void
    {
        if ($this->flushRegistered) {
            return;
        }

        $this->flushRegistered = true;
        app()->terminating(function (): void {
            $this->flushRegistered = false;
            $this->flush();
        });
    }
}
