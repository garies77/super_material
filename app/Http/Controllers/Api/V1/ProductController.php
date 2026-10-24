<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\ApiCollection;
use App\Http\Resources\Api\ProductDetailResource;
use App\Http\Resources\Api\ProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** @tags Produk */
class ProductController extends ApiController
{
    public const INCLUDES = ['category', 'brand', 'variants', 'media', 'links'];

    /**
     * Daftar produk
     *
     * Produk terbit dengan filter (filter[category], filter[brand], filter[price_min], filter[price_max]),
     * urutan (sort=name, price, created_at, updated_at; awali - untuk menurun), relasi lewat include,
     * dan paginasi cursor.
     *
     * @queryParam filter[category] string Slug kategori, termasuk subkategori.
     * @queryParam filter[brand] string Slug merek, boleh beberapa dipisah koma.
     * @queryParam filter[price_min] int Harga minimum (rupiah).
     * @queryParam filter[price_max] int Harga maksimum (rupiah).
     * @queryParam include string category,brand,variants,media,links
     * @queryParam sort string Bawaan -featured.
     * @queryParam fields string Kolom yang dikirim, misal id,slug,name,price.
     * @queryParam per_page int Bawaan 20, maksimal 50.
     */
    public function index(Request $request): ApiCollection
    {
        $includes = $this->includes($request, self::INCLUDES);
        $query = Product::published()->with(array_merge(['variants'], array_intersect($includes, ['category', 'brand', 'media'])));

        self::applyFilters($query, $this->filters($request), hidePrices: true);
        self::applyProductSort($this, $query, $request);

        return $this->paginate($query, $request, ProductResource::class);
    }

    /**
     * Detail produk
     *
     * Spesifikasi, media, varian, FAQ, tautan beli (go_url), produk dan artikel terkait.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $product = Product::published()->where('slug', $slug)
            ->with(['category', 'brand', 'media', 'variants', 'faqs'])->firstOrFail();
        $this->includeAll($request, self::INCLUDES);

        // Urutan tetap (bukan acak seperti halaman) agar respons dapat di-cache
        $related = Product::published()->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->with(['media', 'variants'])->orderByDesc('is_featured')->latest()->take(6)->get();
        $articles = $product->articles()->published()->latest('published_at')->take(3)->get();

        $response = (new ProductDetailResource($product, $related, $articles))->response($request);

        return $this->withLastModified($response, $product->updated_at);
    }

    /**
     * Tautan pembelian
     *
     * Tautan beli aktif per kanal dalam bentuk go_url. Parameter variant (slug atau id) dan quantity
     * ikut diteruskan ke go_url sehingga klik tetap tercatat.
     *
     * @queryParam variant string Slug atau id varian.
     * @queryParam quantity int Jumlah, minimal 1.
     */
    public function links(Request $request, string $slug): JsonResponse
    {
        $product = Product::published()->where('slug', $slug)->firstOrFail();

        $request->validate([
            'variant' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $variant = null;
        if ($request->filled('variant')) {
            $value = (string) $request->query('variant');
            $variant = $product->variants()->where('is_active', true)
                ->where(fn ($q) => $q->where('slug', $value)->when(ctype_digit($value), fn ($q) => $q->orWhere('id', (int) $value)))
                ->first();

            if (! $variant) {
                throw ValidationException::withMessages(['variant' => ['Varian tidak ditemukan pada produk ini.']]);
            }
        }

        return response()->json([
            'data' => ProductResource::links($product, $variant, max(1, $request->integer('quantity', 1))),
        ]);
    }

    /**
     * Filter produk bersama (publik dan admin). Pada API publik, filter dan urutan harga tidak
     * menyertakan produk berharga tersembunyi agar harganya tidak dapat ditebak.
     */
    public static function applyFilters(Builder $query, array $filters, bool $hidePrices): void
    {
        if (isset($filters['category'])) {
            $category = Category::where('slug', $filters['category'])->first();
            $query->whereIn('category_id', $category ? $category->descendantIds() : [0]);
        }

        if (isset($filters['brand'])) {
            $slugs = array_filter(array_map('trim', explode(',', (string) $filters['brand'])));
            $query->whereIn('brand_id', Brand::whereIn('slug', $slugs)->pluck('id')->all() ?: [0]);
        }

        $min = isset($filters['price_min']) && is_numeric($filters['price_min']) ? (int) $filters['price_min'] : null;
        $max = isset($filters['price_max']) && is_numeric($filters['price_max']) ? (int) $filters['price_max'] : null;

        if ($min !== null || $max !== null) {
            if ($hidePrices) {
                $query->where('price_display', '!=', 'hidden');
            }
            // Produk yang rentang harganya beririsan dengan filter, sama seperti katalog
            $query->when($min !== null, fn ($q) => $q->whereRaw('coalesce(price_max, price_min) >= ?', [$min]))
                ->when($max !== null, fn ($q) => $q->where('price_min', '<=', $max));
        }
    }

    /**
     * Urutan produk. sort=price hanya menyertakan produk yang menampilkan harga: paginasi cursor
     * tidak dapat membandingkan nilai kosong, dan harga tersembunyi tidak boleh tertebak dari urutan.
     */
    public static function applyProductSort(ApiController $controller, Builder $query, Request $request): void
    {
        $controller->applySort($query, $request, [
            'featured' => function (Builder $q, string $direction) {
                $q->orderBy('is_featured', $direction)->orderBy('created_at', $direction);
            },
            'name' => 'name',
            'price' => function (Builder $q, string $direction) {
                $q->where('price_display', '!=', 'hidden')->whereNotNull('price_min')->orderBy('price_min', $direction);
            },
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ], '-featured');
    }
}
