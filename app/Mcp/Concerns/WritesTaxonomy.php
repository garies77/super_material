<?php

namespace App\Mcp\Concerns;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Support\Catalog\TaxonomyRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;

/**
 * Bersama untuk tool MCP yang menulis kategori dan merek: validasi memakai TaxonomyRules, penyimpanan memakai
 * TaxonomyWriter (sama dengan REST API admin), dan setiap perubahan dicatat di AuditLog dengan sumber "mcp".
 */
trait WritesTaxonomy
{
    /** Model dari argumen ID (angka) atau slug. */
    protected function findBySlugOrId(Request $request, string $argument, string $modelClass): ?Model
    {
        $key = trim((string) $request->get($argument));
        if ($key === '') {
            return null;
        }

        return ctype_digit($key)
            ? $modelClass::query()->whereKey((int) $key)->first()
            : $modelClass::query()->where('slug', $key)->first();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function validateTaxonomy(array $input, array $rules, ?Category $category = null, bool $isCategory = false): array
    {
        $validator = Validator::make($input, $rules, TaxonomyRules::messages());
        if ($isCategory) {
            $validator->after(fn ($v) => TaxonomyRules::categoryAfter($v, $category));
        }

        return $validator->validate();
    }

    /** @param  array<string, mixed>  $changes */
    protected function auditTaxonomy(Request $request, string $action, Model $model, array $changes): void
    {
        AuditLog::record(request(), $action, $model, $changes, actor: $request->user(), source: 'mcp');
    }

    /** @return array<string, mixed> */
    protected function categoryDetail(Category $category): array
    {
        $category->loadMissing('parent')->loadCount('products');

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->name,
            'parent' => $category->parent?->slug,
            'is_active' => (bool) $category->is_active,
            'sort_order' => (int) $category->sort_order,
            'intro' => $category->intro,
            'bottom_content' => $category->bottom_content,
            'whatsapp_number' => $category->whatsapp_number,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
            'canonical_url' => $category->canonical_url,
            'noindex' => (bool) $category->noindex,
            'image_url' => $category->image ? asset('storage/'.ltrim($category->image, '/')) : null,
            'products' => $category->products_count,
            'public_url' => $category->url(),
        ];
    }

    /** @return array<string, mixed> */
    protected function brandDetail(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'slug' => $brand->slug,
            'name' => $brand->name,
            'logo_url' => $brand->logo ? asset('storage/'.ltrim($brand->logo, '/')) : null,
            'products' => $brand->products()->count(),
        ];
    }

    /** @return array<string, Type> */
    protected static function categoryFieldsSchema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Nama kategori.'),
            'slug' => $schema->string()->description('Slug URL (huruf kecil, angka, tanda hubung). Kosong = dibuat dari nama. Mengubah slug membuat redirect 301 otomatis.'),
            'parent' => $schema->string()->description('Slug kategori induk dari list-categories, atau null untuk kategori utama. Maksimal 3 level.'),
            'intro' => $schema->string()->description('Teks pengantar di atas daftar produk (teks biasa, maks. 2.000 karakter).'),
            'bottom_content' => $schema->string()->description('Teks pendukung HTML di bawah daftar produk; pakai <h2>/<h3>, <p>, <ul>. Disanitasi saat disimpan.'),
            'is_active' => $schema->boolean()->description('false = kategori dan halamannya disembunyikan dari situs.'),
            'sort_order' => $schema->integer()->min(0)->description('Urutan tampil; angka kecil lebih dulu.'),
            'whatsapp_number' => $schema->string()->description('Nomor WhatsApp khusus kategori; kosong = nomor utama situs.'),
            'meta_title' => $schema->string()->description('Judul SEO, maks. 70 karakter.'),
            'meta_description' => $schema->string()->description('Deskripsi SEO, maks. 320 karakter (ideal 140-160).'),
            'canonical_url' => $schema->string(),
            'noindex' => $schema->boolean(),
            'image_url' => $schema->string()->description('URL https gambar kartu kategori (dipotong persegi; disarankan 200×200 atau lebih, min. 10.000 piksel).'),
        ];
    }

    /** @return array<string, Type> */
    protected static function brandFieldsSchema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Nama merek.'),
            'slug' => $schema->string()->description('Slug (huruf kecil, angka, tanda hubung). Kosong = dibuat dari nama.'),
            'logo_url' => $schema->string()->description('URL https logo merek (PNG/WebP berlatar transparan dianjurkan, min. 10.000 piksel).'),
        ];
    }
}
