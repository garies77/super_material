<?php

namespace App\Http\Controllers;

use App\Models\LinkPage;
use App\Support\Reports\LinkRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Halaman link-in-bio publik /l/{slug} dan pengalihan klik /l/{slug}/{item} yang mencatat statistik. */
class LinkPageController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $page = $this->findPage($slug);
        $items = $page->items()->visible()->with(['product', 'channel', 'linkPage'])->get()
            ->filter(fn ($item) => ! $item->isClickable() || $item->destination() !== null)
            ->values();

        if ($page->is_published) {
            LinkRecorder::view($request, $page);
        }

        return view('link-page', ['page' => $page, 'items' => $items]);
    }

    public function click(Request $request, string $slug, int $item): RedirectResponse
    {
        $page = $this->findPage($slug);
        $linkItem = $page->items()->visible()->whereKey($item)->firstOrFail();
        $destination = $linkItem->isClickable() ? $linkItem->destination() : null;

        if ($destination === null) {
            return redirect($page->url(), 302);
        }

        if ($page->is_published) {
            LinkRecorder::click($request, $linkItem);
        }

        return redirect()->away($destination, 302)->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Draf hanya bisa dilihat tim peninjau (pratinjau dari admin); tayangan & klik draf tidak dicatat. */
    private function findPage(string $slug): LinkPage
    {
        $page = LinkPage::where('slug', $slug)->firstOrFail();
        abort_unless($page->is_published || auth()->user()?->canReview(), 404);

        return $page;
    }
}
