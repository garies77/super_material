<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Controllers\Api\V1\ProductController as PublicProductController;
use App\Http\Resources\Api\AdminProductResource;
use App\Http\Resources\Api\ApiCollection;
use App\Http\Resources\Api\ProductMediaResource;
use App\Models\AuditLog;
use App\Models\Product;
use App\Support\Catalog\ProductMediaUploader;
use App\Support\Catalog\ProductRules;
use App\Support\Catalog\ProductWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Pengelolaan produk lewat token (PRD 8.12.6). Validasi dan efek samping sama dengan panel admin;
 * data gagal validasi ditolak seluruhnya (422) dan tidak disimpan sebagian.
 *
 * @tags Admin: Produk
 */
class ProductController extends ApiController
{
    protected bool $admin = true;

    private const RELATIONS = ['category', 'brand', 'variants', 'media', 'links.channel', 'faqs'];

    /**
     * Daftar produk semua status
     *
     * Ability: catalog:read. Filter: filter[status]=published|draft, filter[category], filter[brand],
     * filter[q] (nama atau SKU), filter[price_min], filter[price_max].
     *
     * @queryParam filter[status] string published atau draft.
     * @queryParam filter[q] string Cari nama atau SKU.
     * @queryParam sort string name, price, created_at, updated_at; bawaan -updated_at.
     * @queryParam per_page int Bawaan 20, maksimal 100.
     */
    public function index(Request $request): ApiCollection
    {
        $filters = $this->filters($request);
        $query = Product::query()->with(['category', 'brand', 'variants', 'media', 'links.channel']);

        PublicProductController::applyFilters($query, $filters, hidePrices: false);
        if (isset($filters['status'])) {
            $query->where('is_published', $filters['status'] === 'published');
        }
        if (isset($filters['q'])) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['q']).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like));
        }

        $this->applySort($query, $request, [
            'name' => 'name',
            'price' => fn ($q, string $direction) => $q->whereNotNull('price_min')->orderBy('price_min', $direction),
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ], '-updated_at');

        return $this->paginate($query, $request, AdminProductResource::class);
    }

    /**
     * Detail produk (semua status)
     *
     * Ability: catalog:read.
     */
    public function show(Request $request, Product $product): AdminProductResource
    {
        return $this->present($request, $product);
    }

    /**
     * Membuat produk
     *
     * Ability: catalog:write. Langsung terbit kecuali status=draft dikirim. Varian, tautan kanal,
     * dan FAQ boleh dikirim sekaligus. Mendukung header Idempotency-Key.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateWith($request->all(), ProductRules::product());
        $product = ProductWriter::create($data);

        AuditLog::record($request, 'product.created', $product, ['fields' => array_keys($data)]);

        return $this->present($request, $product)->response()->setStatusCode(201);
    }

    /**
     * Mengubah produk
     *
     * Ability: catalog:write. Hanya kolom yang dikirim yang berubah. Perubahan slug membuat redirect 301
     * dari URL lama. Varian dan tautan kanal diubah lewat endpoint tersendiri.
     */
    public function update(Request $request, Product $product): AdminProductResource
    {
        $input = $request->all();
        // gte:price_min dibandingkan dengan nilai tersimpan bila price_min tidak dikirim
        $compare = array_key_exists('price_max', $input) && ! array_key_exists('price_min', $input)
            ? ['price_min' => $product->price_min] : [];

        $data = $this->validateWith($input + $compare, ProductRules::product($product));
        $changes = ProductWriter::update($product, array_diff_key($data, $compare));

        if ($changes) {
            AuditLog::record($request, 'product.updated', $product, $changes);
        }

        return $this->present($request, $product->refresh());
    }

    /**
     * Mengganti set varian
     *
     * Ability: catalog:write. Seluruh varian lama diganti dengan daftar yang dikirim (kirim [] untuk mengosongkan).
     * Harga coret wajib lebih besar dari harga jual dan wajib disertai tanggal mulai dan berakhir diskon.
     */
    public function variants(Request $request, Product $product): AdminProductResource
    {
        $data = $this->validateWith($request->all(), ProductRules::prefixed('variants', ProductRules::variants(), optional: false));
        ProductWriter::replaceVariants($product, $data['variants']);

        AuditLog::record($request, 'product.variants_replaced', $product, ['count' => count($data['variants'])]);

        return $this->present($request, $product->refresh());
    }

    /**
     * Mengatur tautan kanal pembelian
     *
     * Ability: catalog:write. Kanal yang tidak dikirim dihapus. URL marketplace harus berada di domain
     * yang diizinkan kanal; untuk WhatsApp, url berisi nomor (opsional).
     */
    public function links(Request $request, Product $product): AdminProductResource
    {
        $data = $this->validateWith($request->all(), ProductRules::prefixed('links', ProductRules::links(), optional: false));
        ProductWriter::replaceLinks($product, $data['links']);

        AuditLog::record($request, 'product.links_replaced', $product, ['channels' => array_column($data['links'], 'channel')]);

        return $this->present($request, $product->refresh());
    }

    /**
     * Mengunggah media
     *
     * Ability: catalog:write. Kirim multipart dengan file (gambar JPG/PNG/WebP atau video MP4/WebM) atau
     * youtube_url, serta alt_text (wajib). Gambar melewati pipeline yang sama: nama file deskriptif,
     * tiga turunan rasio, dan minimum 50.000 piksel. Mendukung header Idempotency-Key.
     */
    public function media(Request $request, Product $product): JsonResponse
    {
        $data = $this->validateWith($request->all() + $request->allFiles(), ProductMediaUploader::rules($request));
        $media = ProductMediaUploader::store($product, $data);

        AuditLog::record($request, 'product.media_added', $product, ['media_id' => $media->id, 'type' => $media->type, 'path' => $media->path]);

        return ProductMediaResource::make($media)->response()->setStatusCode(201);
    }

    private function present(Request $request, Product $product): AdminProductResource
    {
        $request->attributes->set('api_includes', ['detail']);

        return AdminProductResource::make($product->loadMissing(self::RELATIONS));
    }

    /** @return array<string, mixed> */
    private function validateWith(array $input, array $rules): array
    {
        $validator = Validator::make($input, $rules);
        $validator->after(fn ($v) => ProductRules::after($v));

        return $validator->validate();
    }
}
