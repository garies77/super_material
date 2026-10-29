<?php

namespace App\Http\Controllers;

use App\Support\ImageDerivatives;
use App\Support\Reports\TrafficRecorder;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = Search::normalize($request->query('q'));
        $type = Search::type($request->query('type'));

        $products = $articles = collect();
        if (mb_strlen($q) >= Search::MIN_LENGTH) {
            if ($type !== 'artikel') {
                $products = Search::products($q)->with(['category', 'brand', 'media', 'variants'])->take(24)->get();
            }
            if ($type !== 'produk') {
                $articles = Search::articles($q)->take(12)->get();
            }
            TrafficRecorder::search($request, $q, $products->count() + $articles->count());
        }

        return view('search', compact('q', 'type', 'products', 'articles'));
    }

    /** Pratinjau live search: maksimal 6 hasil per jenis, hanya kolom yang ditampilkan. */
    public function live(Request $request): JsonResponse
    {
        $q = Search::normalize(is_string($request->query('q')) ? $request->query('q') : '');
        $type = Search::type($request->query('type'));

        if (mb_strlen($q) < Search::MIN_LENGTH) {
            return response()->json(['q' => $q, 'products' => [], 'articles' => []]);
        }

        $products = $type === 'artikel' ? collect() : Search::products($q)->with(['category:id,name', 'brand:id,name', 'media'])
            ->take(6)->get()
            ->map(fn ($p) => [
                'title' => $p->name,
                'url' => $p->url(),
                'image' => ImageDerivatives::resized($p->imageUrl(), 160),
                'meta' => collect([$p->brand?->name, $p->category?->name])->filter()->implode(' · '),
            ]);

        $articles = $type === 'produk' ? collect() : Search::articles($q)
            ->take(6)->get(['id', 'slug', 'title', 'summary', 'image', 'published_at'])
            ->map(fn ($a) => [
                'title' => $a->title,
                'url' => $a->url(),
                'image' => ImageDerivatives::resized($a->imageUrl(), 160),
                'meta' => $a->published_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y'),
                'excerpt' => Str::limit((string) $a->summary, 90),
            ]);

        return response()->json(['q' => $q, 'products' => $products, 'articles' => $articles])
            ->header('X-Robots-Tag', 'noindex');
    }
}
