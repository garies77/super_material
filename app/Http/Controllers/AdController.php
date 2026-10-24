<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\AdEvent;
use App\Models\Article;
use App\Support\BotDetector;
use Illuminate\Http\Request;

/**
 * Pelacakan dan pratinjau Kampanye: rute klik /sm-link/{kampanye} (302, serupa /go/), pencatatan
 * impresi/klik/penutupan dari browser, dan pratinjau untuk Admin/Editor. Jalur ini di-Disallow di robots.txt.
 */
class AdController extends Controller
{
    public function click(Request $request, Ad $ad)
    {
        $destination = $ad->destinationUrl();
        abort_unless($destination, 404);

        if ($ad->isLive()) {
            $this->record($request, $ad, 'click');
        }

        $response = $ad->isExternal() ? redirect()->away($destination, 302) : redirect($destination, 302);

        return $response->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Impresi (≥50% terlihat selama 1 detik), klik tautan internal, atau penutupan pop-up, dikirim via sendBeacon. */
    public function event(Request $request, Ad $ad)
    {
        $type = $request->input('type', 'impression');
        abort_unless(in_array($type, AdEvent::TYPES, true), 422);

        if ($ad->isLive()) {
            $this->record($request, $ad, $type);
        }

        return response()->noContent();
    }

    /**
     * Pratinjau sebelum kampanye tayang. Pop-up dan banner halaman non-artikel tampil dalam bingkai
     * desktop/mobile; banner/sidebar artikel memakai artikel terbaru sebagai contoh.
     */
    public function preview(Request $request, Ad $ad)
    {
        $this->authorizePreview();

        if ($ad->isPopup() || ! in_array('article', $ad->pageList(), true)) {
            return view('campaign-preview', ['campaign' => $ad]);
        }

        $article = Article::published()
            ->when($request->integer('article'), fn ($q, $id) => $q->whereKey($id))
            ->latest('published_at')
            ->firstOrFail();

        return app(ArticleController::class)->render($article, [$ad->slot => $ad], preview: true);
    }

    /** Beranda dengan kampanye ini (pop-up langsung terbuka, atau banner di atas konten); tanpa pelacakan. */
    public function previewFrame(Ad $ad)
    {
        $this->authorizePreview();
        view()->share($ad->isPopup() ? 'smCampaignPreview' : 'smBannerPreview', $ad);

        return response(app(HomeController::class)())->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function authorizePreview(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'editor'], true), 403);
    }

    private function record(Request $request, Ad $ad, string $type): void
    {
        $slot = (string) $request->input('s', $ad->slot);
        $articleId = $request->integer('a') ?: null;
        $page = (string) ($request->headers->get('referer') ?: $request->input('p', ''));

        // Tidak menyimpan IP mentah; user agent hanya dipotong ringkas.
        AdEvent::create([
            'ad_id' => $ad->id,
            'type' => $type,
            'slot' => isset(Ad::SLOTS[$slot]) ? $slot : $ad->slot,
            'article_id' => $articleId && Article::whereKey($articleId)->exists() ? $articleId : null,
            'page_url' => mb_substr($page, 0, 2000) ?: null,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 120),
            'is_bot' => BotDetector::isBot($request->userAgent()),
            'created_at' => now(),
        ]);
    }
}
