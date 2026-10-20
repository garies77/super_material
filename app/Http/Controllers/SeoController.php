<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    public function robots()
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /go/',
            'Disallow: /cari',
            'Disallow: /livewire',
            'Allow: /',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }

    public function index()
    {
        $types = ['products', 'categories', 'articles', 'pages'];
        $xml = '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($types as $t) {
            $xml .= '<sitemap><loc>'.e(url("/sitemap-{$t}.xml")).'</loc></sitemap>';
        }

        return $this->xml($xml.'</sitemapindex>');
    }

    public function show(string $type)
    {
        $xml = Cache::remember("sitemap.{$type}", 600, function () use ($type) {
            $rows = match ($type) {
                'products' => Product::published()->where('noindex', false)->get(['slug', 'updated_at'])
                    ->map(fn ($m) => [route('product.show', $m->slug), $m->updated_at]),
                'categories' => Category::active()->where('noindex', false)->get(['slug', 'updated_at'])
                    ->map(fn ($m) => [route('category.show', $m->slug), $m->updated_at]),
                'articles' => Article::published()->where('noindex', false)->get(['slug', 'updated_at'])
                    ->map(fn ($m) => [route('article.show', $m->slug), $m->updated_at]),
                'pages' => Page::published()->get(['slug', 'updated_at'])
                    ->map(fn ($m) => [route('page.show', $m->slug), $m->updated_at])
                    ->prepend([url('/'), now()])->prepend([route('article.index'), now()])->prepend([route('product.index'), now()]),
                default => abort(404),
            };

            $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach ($rows as [$loc, $mod]) {
                $xml .= '<url><loc>'.e($loc).'</loc><lastmod>'.$mod->toAtomString().'</lastmod></url>';
            }

            return $xml.'</urlset>';
        });

        return $this->xml($xml);
    }

    private function xml(string $body)
    {
        return response($body, 200, ['Content-Type' => 'application/xml']);
    }
}
