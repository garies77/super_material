<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\ProductRules;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Merapikan slug kategori, merek, dan produk yang tidak sesuai ProductRules::SLUG_PATTERN (mis. berspasi
 * atau berhuruf besar, dari sebelum panel admin memvalidasinya). Disimpan lewat model, jadi kategori
 * dan produk otomatis mendapat redirect 301 dari URL lama (TracksPublicUrl).
 */
class NormalizeCatalogSlugs extends Command
{
    protected $signature = 'catalog:normalize-slugs {--dry-run : Tampilkan usulan tanpa menyimpan}';

    protected $description = 'Rapikan slug kategori, merek, dan produk yang berisi spasi atau huruf besar, lengkap dengan redirect 301';

    /** @var array<string, class-string<Model>> */
    private const MODELS = ['Kategori' => Category::class, 'Merek' => Brand::class, 'Produk' => Product::class];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];

        foreach (self::MODELS as $label => $modelClass) {
            $modelClass::query()->orderBy('id')->each(function (Model $record) use ($label, $modelClass, $dryRun, &$rows) {
                if (preg_match(ProductRules::SLUG_PATTERN, (string) $record->slug)) {
                    return;
                }

                $newSlug = $this->uniqueSlug($modelClass, $record);
                $rows[] = [$label, $record->getKey(), $record->slug, $newSlug];

                if (! $dryRun) {
                    $record->slug = $newSlug;
                    $record->save();
                }
            });
        }

        if ($rows === []) {
            $this->info('Semua slug sudah rapi.');

            return self::SUCCESS;
        }

        $this->table(['Jenis', 'ID', 'Slug lama', 'Slug baru'], $rows);
        $this->info($dryRun
            ? count($rows).' slug perlu dirapikan. Jalankan tanpa --dry-run untuk menyimpan.'
            : count($rows).' slug dirapikan; URL lama kategori dan produk dialihkan 301 ke URL baru.');

        return self::SUCCESS;
    }

    /** @param  class-string<Model>  $modelClass */
    private function uniqueSlug(string $modelClass, Model $record): string
    {
        $base = Str::slug((string) $record->slug) ?: Str::slug((string) $record->name) ?: 'item-'.$record->getKey();
        $slug = $base;

        for ($suffix = 2; $modelClass::query()->where('slug', $slug)->whereKeyNot($record->getKey())->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
