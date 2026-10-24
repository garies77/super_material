<?php

namespace App\Support\Catalog;

use App\Models\Product;
use App\Models\SalesChannel;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Aturan validasi produk yang sama dengan formulir Filament (PRD 8.12.6): harga coret lebih besar
 * dari harga jual, tanggal diskon wajib bila ada harga coret, slug unik, URL kanal divalidasi.
 */
class ProductRules
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const AVAILABILITY = ['InStock', 'LimitedAvailability', 'PreOrder', 'OutOfStock'];

    /** @return array<string, mixed> */
    public static function product(?Product $product = null): array
    {
        $creating = $product === null;

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:'.self::SLUG_PATTERN, Rule::unique('products', 'slug')->ignore($product?->id)],
            'status' => ['sometimes', Rule::in(['published', 'draft'])],
            'category' => ['sometimes', 'nullable', 'string', Rule::exists('categories', 'slug')],
            'brand' => ['sometimes', 'nullable', 'string', Rule::exists('brands', 'slug')],
            'sku' => ['sometimes', 'nullable', 'string', 'max:100'],
            'mpn' => ['sometimes', 'nullable', 'string', 'max:100'],
            'badge' => ['sometimes', 'nullable', 'string', 'max:255'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'specifications' => ['sometimes', 'nullable', 'array', 'max:100'],
            'price_display' => ['sometimes', Rule::in(['show', 'hidden'])],
            'price_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'price_max' => ['sometimes', 'nullable', 'integer', 'min:0', 'gte:price_min'],
            'availability' => ['sometimes', 'nullable', Rule::in(self::AVAILABILITY)],
            'price_valid_until' => ['sometimes', 'nullable', 'date'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s]+$/'],
            'shipping_note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guarantee_note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'show_quantity' => ['sometimes', 'boolean'],
            'rating_value' => ['sometimes', 'nullable', 'numeric', 'between:1,5', 'required_with:rating_count'],
            'rating_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'required_with:rating_value'],
            'rating_updated_at' => ['sometimes', 'nullable', 'date'],
            'is_featured' => ['sometimes', 'boolean'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:320'],
            'canonical_url' => ['sometimes', 'nullable', 'url:http,https', 'max:255'],
            'noindex' => ['sometimes', 'boolean'],
            'faqs' => ['sometimes', 'array', 'max:50'],
            'faqs.*.question' => ['required', 'string', 'max:255'],
            'faqs.*.answer' => ['required', 'string', 'max:5000'],
        ] + ($creating ? self::prefixed('variants', self::variants()) + self::prefixed('links', self::links()) : []);
    }

    /** @return array<string, mixed> */
    public static function variants(): array
    {
        return [
            '' => ['present', 'array', 'max:100'],
            '*.name' => ['required', 'string', 'max:255'],
            '*.slug' => ['nullable', 'string', 'max:255', 'regex:'.self::SLUG_PATTERN],
            '*.option_group' => ['nullable', 'string', 'max:100'],
            '*.sku' => ['nullable', 'string', 'max:100'],
            '*.price' => ['required', 'integer', 'min:1'],
            '*.compare_at_price' => ['nullable', 'integer', 'gt:{prefix}*.price'],
            '*.discount_starts_at' => ['nullable', 'required_with:{prefix}*.compare_at_price', 'date'],
            '*.discount_ends_at' => ['nullable', 'required_with:{prefix}*.compare_at_price', 'date', 'after_or_equal:{prefix}*.discount_starts_at'],
            '*.image_url' => ['nullable', 'string', 'max:1000'],
            '*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public static function links(): array
    {
        return [
            '' => ['present', 'array', 'max:20'],
            '*.channel' => ['required', 'string', 'distinct', Rule::exists('sales_channels', 'slug')],
            '*.url' => ['nullable', 'string', 'max:1000'],
            '*.message' => ['nullable', 'string', 'max:255'],
            '*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Rumus untuk kelompok bertingkat (variants.*, links.*). Kunci '' berarti kelompok itu sendiri.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public static function prefixed(string $prefix, array $rules, bool $optional = true): array
    {
        $out = [];
        foreach ($rules as $key => $ruleSet) {
            $ruleSet = array_map(fn ($r) => is_string($r) ? str_replace('{prefix}', $prefix.'.', $r) : $r, $ruleSet);
            if ($key === '') {
                $out[$prefix] = $optional ? array_merge(['sometimes'], array_diff($ruleSet, ['present'])) : $ruleSet;
            } else {
                $out[$prefix.'.'.$key] = $ruleSet;
            }
        }

        return $out;
    }

    /**
     * Pemeriksaan lanjutan yang tidak bisa diungkapkan dengan aturan bawaan:
     * slug varian unik dalam satu produk dan URL kanal harus mengarah ke domain yang diizinkan kanalnya.
     */
    public static function after(Validator $validator, string $variantsKey = 'variants', string $linksKey = 'links'): void
    {
        $data = $validator->getData();

        $slugs = [];
        foreach ((array) ($data[$variantsKey] ?? []) as $i => $variant) {
            $slug = ProductWriter::variantSlug((array) $variant);
            if ($slug === '') {
                continue;
            }
            if (in_array($slug, $slugs, true)) {
                $validator->errors()->add("{$variantsKey}.{$i}.slug", 'Slug varian harus unik dalam satu produk.');
            }
            $slugs[] = $slug;
        }

        $links = (array) ($data[$linksKey] ?? []);
        if (! $links) {
            return;
        }

        $channels = SalesChannel::whereIn('slug', collect($links)->pluck('channel')->filter()->all())->get()->keyBy('slug');
        foreach ($links as $i => $link) {
            $channel = $channels[$link['channel'] ?? ''] ?? null;
            if (! $channel) {
                continue;
            }

            $error = self::linkError($channel, $link['url'] ?? null);
            if ($error) {
                $validator->errors()->add("{$linksKey}.{$i}.url", $error);
            }
        }
    }

    public static function linkError(SalesChannel $channel, ?string $url): ?string
    {
        $url = trim((string) $url);

        if ($channel->isWhatsapp()) {
            return $url === '' || preg_match('/^[0-9+\-\s]{8,20}$/', $url) ? null : 'Nomor WhatsApp hanya boleh berisi angka.';
        }

        if ($url === '') {
            return 'URL wajib diisi untuk kanal marketplace.';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return 'URL kanal harus berupa alamat http atau https yang valid.';
        }

        foreach ($channel->allowed_hosts ?: [] as $allowed) {
            $allowed = strtolower(trim((string) $allowed));
            if ($allowed !== '' && ($host === $allowed || str_ends_with($host, '.'.$allowed))) {
                return null;
            }
        }

        return "Domain {$host} tidak diizinkan untuk kanal {$channel->name}.";
    }
}
