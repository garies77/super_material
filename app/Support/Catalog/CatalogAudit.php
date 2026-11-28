<?php

namespace App\Support\Catalog;

use App\Models\Product;
use App\Support\Reports\CatalogHealth;
use App\Support\SeoScore;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Audit kelengkapan produk dalam satu panggilan untuk asisten AI: kekurangan data dari CatalogHealth,
 * skor SEO dari SeoScore (angka yang sama dengan panel admin), ditambah pemeriksaan yang berguna sebelum
 * merapikan katalog: FAQ, tautan beli, meta, panjang deskripsi, slug, dan kemungkinan produk dobel.
 */
class CatalogAudit
{
    /** @var array<string, string> */
    public const EXTRA_ISSUES = [
        'no_faq' => 'Tanpa FAQ',
        'no_active_link' => 'Tanpa tautan beli aktif',
        'no_meta_description' => 'Deskripsi meta kosong',
        'meta_title_too_long' => 'Judul meta lebih dari 60 karakter',
        'short_description' => 'Deskripsi kurang dari 300 karakter',
        'bad_slug' => 'Slug tidak rapi (spasi/huruf besar)',
        'possible_duplicate' => 'Kemungkinan produk dobel',
    ];

    public const SHORT_DESCRIPTION_CHARS = 300;

    public const META_TITLE_MAX = 60;

    /** Bagian kata nama yang sama (dibanding nama terpendek) agar dua produk dianggap kemungkinan dobel. */
    public const DUPLICATE_OVERLAP = 0.8;

    /** @return array<string, string> semua kode kekurangan => label */
    public static function issueLabels(): array
    {
        return CatalogHealth::ISSUES + self::EXTRA_ISSUES;
    }

    /**
     * @param  Collection<int, Product>  $products  dengan relasi media, links, faqs dimuat
     * @return list<array<string, mixed>>
     */
    public static function audit(Collection $products): array
    {
        $duplicates = self::duplicates($products);
        $labelCodes = array_flip(CatalogHealth::ISSUES);
        $totalChecks = count(self::issueLabels());

        return $products->map(function (Product $product) use ($duplicates, $labelCodes, $totalChecks) {
            $issues = array_map(fn (string $label) => $labelCodes[$label], CatalogHealth::issuesOf($product));

            $plainDescription = trim(strip_tags((string) $product->description));
            $extra = [
                'no_faq' => $product->faqs->isEmpty(),
                'no_active_link' => $product->links->where('is_active', true)->isEmpty(),
                'no_meta_description' => blank($product->meta_description),
                'meta_title_too_long' => mb_strlen((string) $product->meta_title) > self::META_TITLE_MAX,
                'short_description' => mb_strlen($plainDescription) < self::SHORT_DESCRIPTION_CHARS,
                'bad_slug' => ! preg_match(ProductRules::SLUG_PATTERN, (string) $product->slug),
                'possible_duplicate' => isset($duplicates[$product->id]),
            ];
            $issues = array_merge($issues, array_keys(array_filter($extra)));

            $seo = SeoScore::forProduct($product);

            return [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'status' => $product->is_published ? 'published' : 'draft',
                'completeness' => (int) round((1 - count($issues) / $totalChecks) * 100),
                'seo_score' => $seo['score'],
                'issues' => $issues,
                'failed_seo_checks' => array_keys(array_filter($seo['checks'], fn (bool $passed) => ! $passed)),
                'duplicate_of' => $duplicates[$product->id] ?? [],
            ];
        })->sortBy('completeness')->values()->all();
    }

    /**
     * Pasangan produk berkategori atau bermerek sama yang kata-kata namanya hampir sama,
     * mis. "Baut Tanam Beton Dynabolt WOWO M8" dan "Dynabolt Baut Tanam Beton Wowo M8 Panjang 40 mm".
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, list<string>> ID produk => slug produk yang mirip
     */
    public static function duplicates(Collection $products): array
    {
        $words = $products->mapWithKeys(fn (Product $product) => [$product->id => self::nameWords((string) $product->name)]);
        $list = $products->values();
        $found = [];

        for ($i = 0; $i < $list->count(); $i++) {
            for ($j = $i + 1; $j < $list->count(); $j++) {
                [$a, $b] = [$list[$i], $list[$j]];
                $sameGroup = ($a->category_id && $a->category_id === $b->category_id) || ($a->brand_id && $a->brand_id === $b->brand_id);
                if (! $sameGroup) {
                    continue;
                }

                $shortest = min(count($words[$a->id]), count($words[$b->id]));
                if ($shortest < 3) {
                    continue;
                }

                $shared = count(array_intersect($words[$a->id], $words[$b->id]));
                if ($shared / $shortest >= self::DUPLICATE_OVERLAP) {
                    $found[$a->id][] = $b->slug;
                    $found[$b->id][] = $a->slug;
                }
            }
        }

        return $found;
    }

    /** @return list<string> kata unik nama (huruf kecil, tanpa tanda baca) */
    private static function nameWords(string $name): array
    {
        return array_values(array_unique(array_filter(explode(' ', Str::of($name)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->value()))));
    }
}
