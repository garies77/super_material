<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * PRD 9.10.2: pekerjaan harian yang menandai produk berharga kedaluwarsa untuk ditinjau.
 * JSON-LD sudah tidak menulis priceValidUntil yang lewat; penanda ini memberi tahu admin.
 */
class FlagExpiredPrices extends Command
{
    protected $signature = 'seo:flag-expired-prices';

    protected $description = 'Tandai produk yang masa berlaku harga atau diskonnya sudah lewat untuk ditinjau admin';

    public function handle(): int
    {
        $expired = Product::published()
            ->where('price_display', '!=', 'hidden')
            ->where(fn ($q) => $q
                ->where('price_valid_until', '<', now())
                ->orWhereHas('variants', fn ($v) => $v->where('is_active', true)->whereNotNull('compare_at_price')->where('discount_ends_at', '<', now())))
            ->where('price_review_needed', false);

        $count = (clone $expired)->count();
        $expired->update(['price_review_needed' => true]);

        $this->info("{$count} produk ditandai untuk ditinjau.");

        return self::SUCCESS;
    }
}
