<?php

namespace App\Support;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Article;
use App\Models\Product;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Papan misi dashboard: pekerjaan nyata dari data katalog, disaring sesuai peran. Kontributor hanya melihat
 * draf miliknya (yang memang bisa ia ubah); Editor/Admin melihat seluruh katalog; Penulis artikelnya sendiri.
 * Setiap misi menunjuk item berikutnya yang perlu dikerjakan.
 */
class MissionBoard
{
    /**
     * @return array<int, array{icon: string, title: string, hint: string, count: int, url: string}>
     */
    public static function for(User $user): array
    {
        $products = self::productScope($user);
        $articles = self::articleScope($user);

        $definitions = [
            [$products, '🛠️', 'Produk perlu diperbaiki', 'Editor memberi catatan perbaikan.', fn (Builder $q) => $q->where('review_status', Product::REVIEW_CHANGES_REQUESTED)],
            [$articles, '🛠️', 'Artikel perlu diperbaiki', 'Editor memberi catatan perbaikan.', fn (Builder $q) => $q->where('review_status', Article::REVIEW_CHANGES_REQUESTED)],
            [$user->isContributor() ? $products : null, '📨', 'Draf produk siap diajukan', 'Lengkapi misinya, lalu klik "Ajukan untuk ditinjau".', fn (Builder $q) => $q->whereNull('review_status')],
            [$user->isContributor() ? $articles : null, '📨', 'Draf artikel siap diajukan', 'Lengkapi misinya, lalu klik "Ajukan untuk ditinjau".', fn (Builder $q) => $q->whereNull('review_status')],
            [$products, '📸', 'Produk belum punya foto', 'Pasang foto utama minimal 800 px.', fn (Builder $q) => $q->whereNull('image')->whereDoesntHave('media', fn (Builder $m) => $m->where('type', 'image'))],
            [$products, '🏷️', 'Produk tanpa merek atau SKU', 'Merek & SKU membantu Google mengenali produk.', fn (Builder $q) => $q->where(fn (Builder $w) => $w->whereNull('brand_id')->orWhereNull('sku')->orWhere('sku', ''))],
            [$products, '📦', 'Produk tanpa status stok', 'Pilih status ketersediaan (tersedia, terbatas, …).', fn (Builder $q) => $q->whereNull('availability')],
            [$products, '⏰', 'Harga sudah kedaluwarsa', 'Perbarui harga dan masa berlakunya.', fn (Builder $q) => $q->where('price_review_needed', true)],
            [$articles, '💬', 'Artikel tanpa keterangan foto', 'Tulis keterangan untuk foto utama.', fn (Builder $q) => $q->whereNotNull('image')->where(fn (Builder $w) => $w->whereNull('image_caption')->orWhere('image_caption', ''))],
            [$articles, '🗂️', 'Artikel tanpa kategori & tag', 'Pilih kategori atau tag agar mudah ditemukan.', fn (Builder $q) => $q->whereDoesntHave('categories')->whereDoesntHave('tags')],
        ];

        $missions = [];

        foreach ($definitions as [$scope, $icon, $title, $hint, $condition]) {
            if (! $scope) {
                continue;
            }

            $query = $condition($scope());
            if (! ($count = (clone $query)->count())) {
                continue;
            }

            $next = $query->latest('updated_at')->first();
            $resource = $next instanceof Product ? ProductResource::class : ArticleResource::class;

            $missions[] = ['icon' => $icon, 'title' => $title, 'hint' => $hint, 'count' => $count, 'url' => $resource::getUrl('edit', ['record' => $next])];
        }

        return $missions;
    }

    /** @return (Closure(): Builder<Product>)|null produk yang bisa dikerjakan pengguna */
    private static function productScope(User $user): ?Closure
    {
        return match (true) {
            $user->isContributor() => fn () => Product::where('created_by', $user->id)->where('is_published', false),
            $user->canReview() => fn () => Product::query(),
            default => null,
        };
    }

    /** @return Closure(): Builder<Article> artikel yang bisa dikerjakan pengguna */
    private static function articleScope(User $user): Closure
    {
        return function () use ($user): Builder {
            $query = Article::query();

            if ($user->writesAsOwnAuthor()) {
                $query->whereHas('author', fn (Builder $q) => $q->where('user_id', $user->id));
            }

            return $user->isContributor() ? $query->whereNull('published_at') : $query;
        };
    }
}
