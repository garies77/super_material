<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * robots.txt dan peta situs terpecah per jenis konten (PRD 9.4, 9.10.1 poin 9, 9.11.6).
 * Pecahan yang melebihi PER_FILE URL dinomori otomatis: sitemap-products-2.xml, dst.
 */
class SeoController extends Controller
{
    public const TYPES = ['products', 'categories', 'articles', 'pages', 'authors', 'images'];

    public const PER_FILE = 5000;

    /** Path yang di-Disallow di robots.txt; juga dipakai validasi silang noindex (PRD 9.11.3). */
    public const DISALLOW = ['/admin', '/go/', '/ad/', '/livewire'];

    public function robots()
    {
        $lines = ['User-agent: *'];
        foreach (self::DISALLOW as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        array_push($lines, 'Allow: /', '', 'Sitemap: '.url('/sitemap.xml'));

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function index()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (self::TYPES as $type) {
            $entries = $this->entries($type);
            $chunks = max(1, (int) ceil($entries->count() / self::PER_FILE));
            for ($n = 1; $n <= $chunks; $n++) {
                $name = $n === 1 ? "sitemap-{$type}.xml" : "sitemap-{$type}-{$n}.xml";
                $lastmod = $entries->forPage($n, self::PER_FILE)->pluck('lastmod')->filter()->max();
                $xml .= '<sitemap><loc>'.e(url($name)).'</loc>'.($lastmod ? '<lastmod>'.$lastmod.'</lastmod>' : '').'</sitemap>';
            }
        }

        return $this->xml($xml.'</sitemapindex>');
    }

    public function show(string $type, ?int $page = null)
    {
        $page ??= 1;
        $entries = $this->entries($type)->forPage($page, self::PER_FILE);
        abort_if($page > 1 && $entries->isEmpty(), 404);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'.($type === 'images' ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '').'>';

        foreach ($entries as $entry) {
            $xml .= '<url><loc>'.e($entry['loc']).'</loc>';
            if ($type !== 'images' && $entry['lastmod']) {
                $xml .= '<lastmod>'.$entry['lastmod'].'</lastmod>';
            }
            if ($type === 'images') {
                foreach ($entry['images'] as $img) {
                    $xml .= '<image:image><image:loc>'.e($img).'</image:loc></image:image>';
                }
            }
            $xml .= '</url>';
        }

        return $this->xml($xml.'</urlset>');
    }

    public function showNumbered(string $type, int $page)
    {
        abort_if($page < 2, 404);

        return $this->show($type, $page);
    }

    /** Dipanggil observer model (TracksPublicUrl) saat rekaman publik berubah. */
    public static function flushSitemaps(): void
    {
        foreach (self::TYPES as $type) {
            Cache::forget("sitemap.entries.{$type}");
        }
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: ?string, images: array<int, string>}>
     */
    private function entries(string $type): Collection
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        return Cache::remember("sitemap.entries.{$type}", 3600, fn () => (match ($type) {
            'products' => $this->products()->map(fn (Product $p) => $this->entry($p->url(), $p->updated_at)),
            'categories' => $this->categories()->map(fn (Category $c) => $this->entry($c->url(), $c->updated_at)),
            'articles' => $this->articles()->map(fn (Article $a) => $this->entry($a->url(), $a->modifiedAt())),
            'pages' => $this->pages(),
            'authors' => Author::active()->whereHas('publishedArticles')->get()
                ->map(fn (Author $a) => $this->entry($a->url(), $a->updated_at)),
            'images' => $this->images(),
        })->values());
    }

    private function entry(string $loc, $lastmod = null, array $images = []): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod?->toAtomString(),
            'images' => array_values(array_unique(array_filter($images))),
        ];
    }

    /** Hanya URL kanonis yang layak indeks: noindex dan canonical ke URL lain dikecualikan. */
    private function indexable(Collection $models): Collection
    {
        return $models->filter(fn ($m) => ! $m->noindex && (blank($m->canonical_url) || rtrim($m->canonical_url, '/') === rtrim($m->url(), '/')));
    }

    private function products(): Collection
    {
        return $this->indexable(Product::published()->with('media')->get());
    }

    private function categories(): Collection
    {
        return $this->indexable(Category::active()->get());
    }

    private function articles(): Collection
    {
        return $this->indexable(Article::published()->get());
    }

    private function pages(): Collection
    {
        // Halaman daftar: lastmod mengikuti konten terbaru yang ditampilkannya, bukan waktu permintaan
        $latestProduct = ($v = Product::published()->max('updated_at')) ? Carbon::parse($v) : null;
        $latestArticle = Article::published()->get(['published_at', 'content_updated_at', 'created_at'])
            ->map(fn (Article $a) => $a->modifiedAt())->filter()->sortDesc()->first();
        $latestAny = collect([$latestProduct, $latestArticle])->filter()->sortDesc()->first();

        $static = collect([
            $this->entry(url('/'), $latestAny),
            $this->entry(route('product.index'), $latestProduct),
            $this->entry(route('article.index'), $latestArticle),
        ]);

        return $static->merge(
            $this->indexable(Page::published()->get())->map(fn (Page $p) => $this->entry($p->url(), $p->updated_at))
        );
    }

    /** Peta situs gambar: seluruh gambar produk, gambar utama artikel, dan gambar kategori (PRD 9.10.1 poin 9). */
    private function images(): Collection
    {
        return collect()
            ->merge($this->products()->map(fn (Product $p) => $this->entry($p->url(), null, $p->allImageUrls())))
            ->merge($this->articles()->map(fn (Article $a) => $this->entry($a->url(), null, [$a->primaryImageUrl()])))
            ->merge($this->categories()->map(fn (Category $c) => $this->entry($c->url(), null, [$c->primaryImageUrl()])))
            ->filter(fn ($e) => ! empty($e['images']))
            ->filter(fn ($e) => ! collect($e['images'])->contains(fn ($u) => Str::startsWith($u, 'data:')));
    }

    private function xml(string $body)
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
