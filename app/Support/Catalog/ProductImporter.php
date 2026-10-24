<?php

namespace App\Support\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductLink;
use App\Models\SalesChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Impor produk dari CSV/Excel, dipakai panel admin dan API (PRD 8.12.6).
 * Kolom: nama, slug, kategori, merek, harga_min, harga_max, satuan, badge, shopee_url,
 * tokopedia_url, lazada_url, tiktok_url, whatsapp_message, terbit.
 * Baris yang gagal validasi dilewati dan dicatat; baris lain tetap diproses.
 */
class ProductImporter
{
    public const CHANNEL_COLUMNS = [
        'shopee_url' => 'shopee',
        'tokopedia_url' => 'tokopedia',
        'lazada_url' => 'lazada',
        'tiktok_url' => 'tiktok-shop',
    ];

    public const MAX_ROWS = 5000;

    /**
     * @return array{total: int, created: int, updated: int, failed: array<int, array{row: int, errors: array<string, array<int, string>>}>}
     */
    public static function import(string $path, ?string $extension = null): array
    {
        $channels = SalesChannel::all()->keyBy('slug');
        $result = ['total' => 0, 'created' => 0, 'updated' => 0, 'failed' => []];

        foreach (SpreadsheetReader::rows($path, $extension) as $line => $row) {
            if (++$result['total'] > self::MAX_ROWS) {
                $result['total']--;
                $result['failed'][] = ['row' => $line, 'errors' => ['file' => ['Melebihi batas '.self::MAX_ROWS.' baris; sisa baris tidak diproses.']]];
                break;
            }

            $errors = self::validate($row, $channels);
            if ($errors) {
                $result['failed'][] = ['row' => $line, 'errors' => $errors];

                continue;
            }

            $created = DB::transaction(fn () => self::save($row, $channels));
            $result[$created ? 'created' : 'updated']++;
        }

        return $result;
    }

    /** @return array<string, array<int, string>> */
    private static function validate(array $row, $channels): array
    {
        $validator = Validator::make($row, [
            'nama' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:'.ProductRules::SLUG_PATTERN],
            'kategori' => ['nullable', 'string', 'max:255'],
            'merek' => ['nullable', 'string', 'max:255'],
            'harga_min' => ['nullable', 'integer', 'min:0'],
            'harga_max' => ['nullable', 'integer', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'badge' => ['nullable', 'string', 'max:255'],
            'whatsapp_message' => ['nullable', 'string', 'max:255'],
            'terbit' => ['nullable', 'in:0,1,ya,tidak,true,false'],
        ]);

        $validator->after(function ($v) use ($row, $channels) {
            $min = $row['harga_min'] ?? '';
            $max = $row['harga_max'] ?? '';
            if ($min !== '' && $max !== '' && is_numeric($min) && is_numeric($max) && (int) $max < (int) $min) {
                $v->errors()->add('harga_max', 'Harga maksimum harus lebih besar atau sama dengan harga minimum.');
            }

            foreach (self::CHANNEL_COLUMNS as $column => $slug) {
                $url = $row[$column] ?? '';
                if ($url !== '' && isset($channels[$slug]) && ($error = ProductRules::linkError($channels[$slug], $url))) {
                    $v->errors()->add($column, $error);
                }
            }
        });

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }

    /** @return bool true bila produk baru dibuat */
    private static function save(array $row, $channels): bool
    {
        $name = $row['nama'];
        $slug = ($row['slug'] ?? '') !== '' ? $row['slug'] : Str::slug($name);

        $category = ($row['kategori'] ?? '') !== ''
            ? Category::firstOrCreate(['slug' => Str::slug($row['kategori'])], ['name' => $row['kategori'], 'is_active' => true])
            : null;
        $brand = ($row['merek'] ?? '') !== ''
            ? Brand::firstOrCreate(['slug' => Str::slug($row['merek'])], ['name' => $row['merek']])
            : null;

        $published = ! in_array(strtolower($row['terbit'] ?? '1'), ['0', 'tidak', 'false'], true);

        $product = Product::firstOrNew(['slug' => $slug]);
        $created = ! $product->exists;
        $product->fill([
            'name' => $name,
            'category_id' => $category?->id ?? $product->category_id,
            'brand_id' => $brand?->id ?? $product->brand_id,
            'price_min' => ($row['harga_min'] ?? '') !== '' ? (int) $row['harga_min'] : null,
            'price_max' => ($row['harga_max'] ?? '') !== '' ? (int) $row['harga_max'] : null,
            'unit' => ($row['satuan'] ?? '') ?: ($product->unit ?: 'pcs'),
            'badge' => ($row['badge'] ?? '') ?: null,
            'is_published' => $published,
        ]);
        if ($created) {
            $product->show_quantity = true;
            $product->price_display = 'show';
        }
        $product->save();

        foreach (self::CHANNEL_COLUMNS as $column => $channelSlug) {
            $url = $row[$column] ?? '';
            if ($url !== '' && isset($channels[$channelSlug])) {
                ProductLink::updateOrCreate(
                    ['product_id' => $product->id, 'sales_channel_id' => $channels[$channelSlug]->id],
                    ['url' => $url, 'is_active' => true],
                );
            }
        }

        $message = $row['whatsapp_message'] ?? '';
        if ($message !== '' && isset($channels['whatsapp'])) {
            ProductLink::updateOrCreate(
                ['product_id' => $product->id, 'sales_channel_id' => $channels['whatsapp']->id],
                ['message' => $message, 'is_active' => true],
            );
        }

        return $created;
    }
}
