<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\WritesProducts;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\ProductRules;
use App\Support\Catalog\ProductWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Mengubah kolom yang sama di banyak produk sekaligus (maks. 50), dalam DUA langkah. Langkah 1: kirim products dan changes (dry_run bawaan true) untuk melihat pratinjau perubahan per produk dan mendapat confirmation_token; tunjukkan pratinjau ke pengguna dan minta persetujuan. Langkah 2: kirim confirmation_token dengan dry_run=false untuk menyimpan; token berlaku 10 menit dan hanya sekali pakai. Kolom yang boleh diubah: status, category, brand, badge, unit, availability, price_display, price_valid_until, show_quantity, is_featured, shipping_note, guarantee_note, whatsapp_number, noindex. Harga, nama, slug, dan deskripsi tetap diubah per produk lewat update-product.')]
#[IsDestructive]
class BulkUpdateProducts extends AbilityTool
{
    use WritesProducts;

    public const MAX_PRODUCTS = 50;

    public const TOKEN_MINUTES = 10;

    /** @var list<string> */
    public const FIELDS = [
        'status', 'category', 'brand', 'badge', 'unit', 'availability', 'price_display', 'price_valid_until',
        'show_quantity', 'is_featured', 'shipping_note', 'guarantee_note', 'whatsapp_number', 'noindex',
    ];

    protected string $ability = 'catalog:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $dryRun = $request->get('dry_run', true) !== false;

        return $dryRun ? $this->preview($request) : $this->apply($request);
    }

    private function preview(Request $request): Response|ResponseFactory
    {
        $input = $this->validated($request, [
            'products' => ['required', 'array', 'min:1', 'max:'.self::MAX_PRODUCTS],
            'products.*' => ['required', 'string', 'distinct'],
            'changes' => ['required', 'array', 'min:1'],
        ]);

        $unknownFields = array_diff(array_keys($input['changes']), self::FIELDS);
        if ($unknownFields) {
            return Response::error('Kolom ini tidak bisa diubah massal: '.implode(', ', $unknownFields).'. Pakai update-product per produk.');
        }

        $changes = $this->validateProductInput($input['changes'], Arr::only(ProductRules::product(), self::FIELDS));
        $products = $this->resolveProducts($input['products']);
        $missing = array_values(array_diff($input['products'], $products->flatMap(fn (Product $p) => [(string) $p->id, $p->slug])->all()));
        if ($missing) {
            return Response::error('Produk tidak ditemukan: '.implode(', ', $missing).'. Cari dulu dengan search-products.');
        }

        $previews = $products->map(fn (Product $product) => [
            'slug' => $product->slug,
            'name' => $product->name,
            'changes' => $this->diff($product, $changes),
        ])->values();

        $token = Str::random(40);
        Cache::put($this->cacheKey($token), [
            'user_id' => $request->user()->id,
            'product_ids' => $products->modelKeys(),
            'changes' => $changes,
        ], now()->addMinutes(self::TOKEN_MINUTES));

        return Response::structured([
            'dry_run' => true,
            'products_affected' => $previews->filter(fn (array $preview) => $preview['changes'] !== [])->count(),
            'previews' => $previews->all(),
            'confirmation_token' => $token,
            'expires_in_minutes' => self::TOKEN_MINUTES,
            'next_step' => 'Tunjukkan pratinjau ke pengguna. Bila disetujui, panggil lagi dengan confirmation_token dan dry_run=false.',
        ]);
    }

    private function apply(Request $request): Response|ResponseFactory
    {
        $token = (string) $request->get('confirmation_token');
        $plan = $token !== '' ? Cache::get($this->cacheKey($token)) : null;
        if (! $plan || $plan['user_id'] !== $request->user()->id) {
            return Response::error('confirmation_token tidak valid, sudah dipakai, atau kedaluwarsa. Jalankan pratinjau (dry_run=true) lagi.');
        }
        // Dihapus setelah pemiliknya terverifikasi: token sekali pakai, dan percobaan pengguna lain tidak menghanguskannya
        Cache::forget($this->cacheKey($token));

        $results = DB::transaction(function () use ($plan, $request) {
            return Product::query()->whereKey($plan['product_ids'])->get()->map(function (Product $product) use ($plan, $request) {
                $changes = ProductWriter::update($product, $plan['changes']);
                if ($changes) {
                    $this->audit($request, 'product.updated', $product, $changes + ['bulk' => ['from' => null, 'to' => true]]);
                }

                return ['slug' => $product->slug, 'changed' => array_keys($changes)];
            })->values()->all();
        });

        return Response::structured([
            'dry_run' => false,
            'products_updated' => count(array_filter($results, fn (array $result) => $result['changed'] !== [])),
            'results' => $results,
        ]);
    }

    /**
     * @param  list<string>  $keys
     * @return Collection<int, Product>
     */
    private function resolveProducts(array $keys): Collection
    {
        $ids = array_map('intval', array_filter($keys, 'ctype_digit'));

        return Product::query()->with(['category', 'brand'])
            ->where(fn ($q) => $q->whereKey($ids)->orWhereIn('slug', $keys))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diff(Product $product, array $changes): array
    {
        $current = [
            'status' => $product->is_published ? 'published' : 'draft',
            'category' => $product->category?->slug,
            'brand' => $product->brand?->slug,
            'price_valid_until' => $product->price_valid_until?->toDateString(),
        ];

        $diff = [];
        foreach ($changes as $field => $new) {
            $old = array_key_exists($field, $current) ? $current[$field] : $product->{$field};
            if ($field === 'price_valid_until' && $new !== null) {
                $new = substr((string) $new, 0, 10);
            }
            if ((is_bool($old) || is_bool($new)) ? (bool) $old !== (bool) $new : (string) $old !== (string) $new) {
                $diff[$field] = ['from' => $old, 'to' => $new];
            }
        }

        // Validasi sudah memastikan slug kategori/merek ada; nama dipakai agar pratinjau mudah dibaca
        if (isset($diff['category']['to'])) {
            $diff['category']['to_name'] = Category::where('slug', $diff['category']['to'])->value('name');
        }
        if (isset($diff['brand']['to'])) {
            $diff['brand']['to_name'] = Brand::where('slug', $diff['brand']['to'])->value('name');
        }

        return $diff;
    }

    private function cacheKey(string $token): string
    {
        return 'mcp:bulk-update-products:'.hash('sha256', $token);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'products' => $schema->array()->items($schema->string())->description('ID atau slug produk (maks. 50). Wajib saat pratinjau.'),
            'changes' => $schema->object()->description('Kolom dan nilai baru yang sama untuk semua produk, mis. {"category": "cetok-beton", "badge": "Best Seller"}. Wajib saat pratinjau.'),
            'dry_run' => $schema->boolean()->description('Bawaan true = hanya pratinjau. false + confirmation_token = simpan.'),
            'confirmation_token' => $schema->string()->description('Token dari hasil pratinjau; wajib saat dry_run=false.'),
        ];
    }
}
