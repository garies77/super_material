<?php

namespace App\Console\Commands;

use App\Support\Reports\CatalogHealth;
use Illuminate\Console\Command;

/** Pekerjaan harian: potret kesehatan katalog (kekurangan data & skor SEO) untuk laporan Kesehatan Katalog. */
class SnapshotCatalogHealth extends Command
{
    protected $signature = 'reports:snapshot-catalog';

    protected $description = 'Hitung skor SEO & kekurangan data katalog hari ini untuk tren Kesehatan Katalog';

    public function handle(): int
    {
        $metrics = CatalogHealth::snapshot()->metrics;

        $this->info("{$metrics['products']} produk (skor rata-rata {$metrics['product_seo']['avg']}), {$metrics['articles']} artikel (skor rata-rata {$metrics['article_seo']['avg']}).");

        return self::SUCCESS;
    }
}
