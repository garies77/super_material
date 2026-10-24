<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk kueri publik yang paling sering: artikel terbit/terpopuler, produk terbit per kategori/merek,
 * pohon kategori, media & varian kartu produk, agregasi klik populer, dan relasi pivot dari sisi kebalikannya.
 * Dilewati bila index dengan kolom yang sama sudah ada (MySQL membuat index otomatis untuk foreign key).
 */
return new class extends Migration
{
    /** @var array<string, array<int, array<int, string>>> */
    private array $indexes = [
        'articles' => [['published_at'], ['views_count'], ['author_id']],
        'products' => [['is_published', 'category_id'], ['brand_id']],
        'categories' => [['parent_id']],
        'product_media' => [['product_id', 'sort_order']],
        'product_variants' => [['product_id']],
        'click_events' => [['product_id', 'created_at']],
        'article_category' => [['category_id']],
        'article_product' => [['product_id']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes) {
                foreach ($indexes as $columns) {
                    if (! Schema::hasIndex($table, $columns)) {
                        $blueprint->index($columns, $this->name($table, $columns));
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes) {
                foreach ($indexes as $columns) {
                    if (Schema::hasIndex($table, $this->name($table, $columns))) {
                        $blueprint->dropIndex($this->name($table, $columns));
                    }
                }
            });
        }
    }

    private function name(string $table, array $columns): string
    {
        return 'perf_'.$table.'_'.implode('_', $columns);
    }
};
