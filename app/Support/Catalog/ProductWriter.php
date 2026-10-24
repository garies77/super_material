<?php

namespace App\Support\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductLink;
use App\Models\SalesChannel;
use App\Support\Api\HtmlSanitizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Penulisan produk lewat API dan impor. Efek samping (redirect 301 saat slug berubah, pembaruan
 * sitemap dan cache, penanda tinjau harga) berjalan lewat event model yang sama dengan panel admin.
 */
class ProductWriter
{
    private const SCALAR_FIELDS = [
        'name', 'slug', 'sku', 'mpn', 'badge', 'unit', 'summary', 'price_display', 'price_min', 'price_max',
        'availability', 'whatsapp_number', 'shipping_note', 'guarantee_note', 'show_quantity',
        'rating_value', 'rating_count', 'is_featured', 'meta_title', 'meta_description', 'canonical_url', 'noindex',
    ];

    /** Data dari API langsung terbit kecuali status draft dikirim eksplisit (PRD 8.12.6). */
    public static function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = new Product(['is_published' => true, 'price_display' => 'show', 'show_quantity' => true]);
            self::fill($product, $data);
            $product->slug = filled($data['slug'] ?? null) ? $data['slug'] : self::uniqueSlug($product->name);
            $product->save();

            if (array_key_exists('variants', $data)) {
                self::replaceVariants($product, $data['variants']);
            }
            if (array_key_exists('links', $data)) {
                self::replaceLinks($product, $data['links']);
            }
            if (array_key_exists('faqs', $data)) {
                self::replaceFaqs($product, $data['faqs']);
            }

            return $product->refresh();
        });
    }

    /** @return array<string, array{from: mixed, to: mixed}> ringkasan perubahan untuk audit log */
    public static function update(Product $product, array $data): array
    {
        return DB::transaction(function () use ($product, $data) {
            self::fill($product, $data);
            $changes = self::diff($product);
            $product->save();

            if (array_key_exists('faqs', $data)) {
                self::replaceFaqs($product, $data['faqs']);
                $changes['faqs'] = ['from' => null, 'to' => count($data['faqs']).' FAQ'];
            }

            return $changes;
        });
    }

    public static function replaceVariants(Product $product, array $variants): void
    {
        DB::transaction(function () use ($product, $variants) {
            $product->variants()->delete();

            foreach (array_values($variants) as $i => $variant) {
                $compare = $variant['compare_at_price'] ?? null;
                $product->variants()->create([
                    'name' => $variant['name'],
                    'slug' => self::variantSlug($variant),
                    'option_group' => ($variant['option_group'] ?? null) ?: 'Varian',
                    'sku' => $variant['sku'] ?? null,
                    'price' => (int) $variant['price'],
                    'compare_at_price' => $compare !== null ? (int) $compare : null,
                    'discount_starts_at' => $compare !== null && ! empty($variant['discount_starts_at']) ? Carbon::parse($variant['discount_starts_at']) : null,
                    'discount_ends_at' => $compare !== null && ! empty($variant['discount_ends_at']) ? Carbon::parse($variant['discount_ends_at']) : null,
                    'image_path' => $variant['image_url'] ?? null,
                    'is_active' => (bool) ($variant['is_active'] ?? true),
                    'sort_order' => $i,
                ]);
            }

            // Simpan ulang produk agar updated_at, sitemap, dan cache ikut diperbarui
            $product->touch();
        });
    }

    public static function replaceLinks(Product $product, array $links): void
    {
        DB::transaction(function () use ($product, $links) {
            $channels = SalesChannel::whereIn('slug', Arr::pluck($links, 'channel'))->get()->keyBy('slug');
            $keep = [];

            foreach ($links as $link) {
                $channel = $channels[$link['channel']];
                $keep[] = $channel->id;
                ProductLink::updateOrCreate(
                    ['product_id' => $product->id, 'sales_channel_id' => $channel->id],
                    [
                        'url' => filled($link['url'] ?? null) ? trim($link['url']) : null,
                        'message' => $link['message'] ?? null,
                        'is_active' => (bool) ($link['is_active'] ?? true),
                    ],
                );
            }

            $product->links()->whereNotIn('sales_channel_id', $keep)->delete();
            $product->touch();
        });
    }

    public static function replaceFaqs(Product $product, array $faqs): void
    {
        $product->faqs()->delete();
        foreach (array_values($faqs) as $i => $faq) {
            $product->faqs()->create(['question' => $faq['question'], 'answer' => $faq['answer'], 'sort_order' => $i]);
        }
    }

    public static function variantSlug(array $variant): string
    {
        return filled($variant['slug'] ?? null) ? (string) $variant['slug'] : Str::slug((string) ($variant['name'] ?? ''));
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;
        for ($n = 2; Product::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private static function fill(Product $product, array $data): void
    {
        foreach (self::SCALAR_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $product->{$field} = $data[$field];
            }
        }

        if (array_key_exists('status', $data)) {
            $product->is_published = $data['status'] === 'published';
        }
        if (array_key_exists('description', $data)) {
            $product->description = HtmlSanitizer::clean($data['description']);
        }
        if (array_key_exists('specifications', $data)) {
            $product->specifications = self::normalizeSpecifications($data['specifications']);
        }
        if (array_key_exists('category', $data)) {
            $product->category_id = $data['category'] ? Category::where('slug', $data['category'])->value('id') : null;
        }
        if (array_key_exists('brand', $data)) {
            $product->brand_id = $data['brand'] ? Brand::where('slug', $data['brand'])->value('id') : null;
        }
        foreach (['price_valid_until', 'rating_updated_at'] as $date) {
            if (array_key_exists($date, $data)) {
                $product->{$date} = $data[$date] ? Carbon::parse($data[$date]) : null;
            }
        }
    }

    /** Terima objek {"Atribut": "Nilai"} atau daftar [{"name", "value"}]; disimpan sebagai objek seperti di Filament. */
    private static function normalizeSpecifications(?array $specs): ?array
    {
        if (! $specs) {
            return null;
        }

        if (array_is_list($specs)) {
            return collect($specs)->filter(fn ($s) => is_array($s) && filled($s['name'] ?? null))
                ->mapWithKeys(fn ($s) => [(string) $s['name'] => (string) ($s['value'] ?? '')])->all();
        }

        return array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $specs);
    }

    /** @return array<string, array{from: mixed, to: mixed}> */
    private static function diff(Product $product): array
    {
        $short = fn ($v) => is_string($v) && mb_strlen($v) > 200 ? mb_substr($v, 0, 200).'…' : $v;

        return collect($product->getDirty())->except('updated_at')
            ->map(fn ($value, $key) => ['from' => $short($product->getOriginal($key)), 'to' => $short($value)])
            ->map(fn ($c) => array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format(DATE_ATOM) : (is_array($v) ? '[array]' : $v), $c))
            ->all();
    }
}
