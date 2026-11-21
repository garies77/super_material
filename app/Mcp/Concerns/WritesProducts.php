<?php

namespace App\Mcp\Concerns;

use App\Http\Resources\Api\AdminProductResource;
use App\Models\AuditLog;
use App\Models\Product;
use App\Support\Catalog\ProductRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Bersama untuk tool MCP yang menulis produk: validasi dan penyimpanan memakai ProductRules dan ProductWriter
 * yang sama dengan REST API admin (PRD 8.12.6), dan setiap perubahan dicatat di AuditLog dengan sumber "mcp".
 * Error validasi dilempar sebagai ValidationException; laravel/mcp mengubahnya menjadi respons error tool.
 */
trait WritesProducts
{
    /** @var array<int, string> */
    private const PRODUCT_RELATIONS = ['category', 'brand', 'variants', 'media', 'links.channel', 'faqs'];

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function validateProductInput(array $input, array $rules): array
    {
        $validator = Validator::make($input, $rules);
        $validator->after(fn ($v) => ProductRules::after($v));

        return $validator->validate();
    }

    /** Produk dari argumen "product": ID (angka) atau slug. */
    protected function findProduct(Request $request): ?Product
    {
        $key = trim((string) $request->get('product'));
        if ($key === '') {
            return null;
        }

        return ctype_digit($key)
            ? Product::query()->whereKey((int) $key)->first()
            : Product::query()->where('slug', $key)->first();
    }

    protected function notFound(): Response
    {
        return Response::error('Produk tidak ditemukan. Cari dulu dengan search-products, lalu kirim ID atau slug-nya di argumen "product".');
    }

    /** Detail produk setelah perubahan, bentuknya sama dengan get-product. */
    protected function productDetail(Product $product): array
    {
        $resourceRequest = new HttpRequest;
        $resourceRequest->attributes->set('api_includes', ['detail']);

        return AdminProductResource::make($product->loadMissing(self::PRODUCT_RELATIONS))->resolve($resourceRequest);
    }

    /** @param  array<string, mixed>  $changes */
    protected function audit(Request $request, string $action, Product $product, array $changes): void
    {
        AuditLog::record(request(), $action, $product, $changes, actor: $request->user(), source: 'mcp');
    }

    /** @return array<string, Type> */
    protected static function productIdentifierSchema(JsonSchema $schema): array
    {
        return [
            'product' => $schema->string()->required()
                ->description('ID (angka) atau slug produk yang diubah, mis. dari search-products.'),
        ];
    }

    /**
     * Kolom produk yang boleh dikirim; semuanya opsional (kecuali name saat membuat).
     *
     * @return array<string, Type>
     */
    protected static function productFieldsSchema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Nama produk.'),
            'slug' => $schema->string()->description('Slug URL (huruf kecil, angka, tanda hubung). Kosong = dibuat dari nama. Mengubah slug produk lama membuat redirect 301 otomatis.'),
            'status' => $schema->string()->enum(['draft', 'published'])->description('draft = belum tampil di situs, published = tampil.'),
            'category' => $schema->string()->description('Slug kategori dari list-categories, atau null untuk mengosongkan.'),
            'brand' => $schema->string()->description('Slug merek dari list-brands, atau null untuk mengosongkan.'),
            'sku' => $schema->string(),
            'mpn' => $schema->string()->description('Kode part dari produsen.'),
            'badge' => $schema->string()->description('Label kecil di kartu produk, mis. "Best Seller".'),
            'unit' => $schema->string()->description('Satuan jual, mis. "sak", "kotak isi 6 pcs".'),
            'summary' => $schema->string()->description('Ringkasan singkat (maks. 2.000 karakter).'),
            'description' => $schema->string()->description('Deskripsi HTML; disanitasi saat disimpan.'),
            'specifications' => $schema->object()->description('Spesifikasi sebagai objek {"Atribut": "Nilai"}.'),
            'price_display' => $schema->string()->enum(['show', 'hidden'])->description('hidden = tampil "Hubungi kami untuk harga".'),
            'price_min' => $schema->integer()->min(0)->description('Harga acuan terendah dalam rupiah (tanpa titik).'),
            'price_max' => $schema->integer()->min(0)->description('Harga acuan tertinggi dalam rupiah; harus ≥ price_min.'),
            'availability' => $schema->string()->enum(ProductRules::AVAILABILITY),
            'price_valid_until' => $schema->string()->description('Tanggal harga berlaku sampai (YYYY-MM-DD).'),
            'whatsapp_number' => $schema->string()->description('Nomor WhatsApp khusus produk ini; kosong = nomor utama situs.'),
            'shipping_note' => $schema->string(),
            'guarantee_note' => $schema->string(),
            'show_quantity' => $schema->boolean(),
            'is_featured' => $schema->boolean()->description('Tampilkan sebagai produk unggulan.'),
            'meta_title' => $schema->string()->description('Judul SEO (maks. 255 karakter).'),
            'meta_description' => $schema->string()->description('Deskripsi SEO (maks. 320 karakter).'),
            'canonical_url' => $schema->string(),
            'noindex' => $schema->boolean(),
            'faqs' => $schema->array()->items($schema->object([
                'question' => $schema->string()->required(),
                'answer' => $schema->string()->required(),
            ]))->description('Daftar FAQ; menggantikan seluruh FAQ lama.'),
        ];
    }

    protected static function variantsSchema(JsonSchema $schema): Type
    {
        return $schema->array()->items($schema->object([
            'name' => $schema->string()->required()->description('Nama varian, mis. "Ukuran 14".'),
            'option_group' => $schema->string()->description('Nama kelompok pilihan, mis. "Ukuran". Bawaan "Varian".'),
            'sku' => $schema->string(),
            'price' => $schema->integer()->min(1)->required()->description('Harga jual dalam rupiah.'),
            'compare_at_price' => $schema->integer()->description('Harga coret; harus > price dan wajib disertai tanggal diskon.'),
            'discount_starts_at' => $schema->string()->description('YYYY-MM-DD'),
            'discount_ends_at' => $schema->string()->description('YYYY-MM-DD'),
            'is_active' => $schema->boolean(),
        ]))->max(100);
    }

    protected static function linksSchema(JsonSchema $schema): Type
    {
        return $schema->array()->items($schema->object([
            'channel' => $schema->string()->required()->description('Slug kanal: shopee, tokopedia, lazada, tiktok-shop, atau whatsapp.'),
            'url' => $schema->string()->description('URL produk di marketplace (domain harus sesuai kanal). Untuk WhatsApp: nomor, opsional.'),
            'message' => $schema->string()->description('Pesan awal WhatsApp (opsional).'),
            'is_active' => $schema->boolean()->description('false = tautan disimpan tapi tombol beli tidak tampil.'),
        ]))->max(20);
    }
}
