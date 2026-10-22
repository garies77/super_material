<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Support\BotDetector;
use Illuminate\Http\Request;

/**
 * Modal kampanye (PRD 8.9): pencatatan impresi, klik CTA, dan penutupan dari browser,
 * serta pratinjau desktop/mobile untuk Admin/Editor.
 */
class CampaignController extends Controller
{
    public function event(Request $request, Campaign $campaign)
    {
        $type = (string) $request->input('type');
        abort_unless(in_array($type, CampaignEvent::TYPES, true), 422);

        if ($campaign->isLive()) {
            $device = (string) $request->input('d');
            $path = '/'.ltrim((string) parse_url((string) $request->input('p', ''), PHP_URL_PATH), '/');

            // Tidak menyimpan IP mentah; user agent hanya dipotong ringkas.
            CampaignEvent::create([
                'campaign_id' => $campaign->id,
                'type' => $type,
                'device' => in_array($device, ['desktop', 'mobile'], true) ? $device : null,
                'page_path' => mb_substr($path, 0, 500),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 120),
                'is_bot' => BotDetector::isBot($request->userAgent()),
                'created_at' => now(),
            ]);
        }

        return response()->noContent();
    }

    /** Pratinjau berdampingan: bingkai desktop dan mobile. */
    public function preview(Campaign $campaign)
    {
        $this->authorizePreview();

        return view('campaign-preview', ['campaign' => $campaign]);
    }

    /** Beranda dengan modal kampanye ini langsung terbuka, tanpa frekuensi dan tanpa pelacakan. */
    public function previewFrame(Campaign $campaign)
    {
        $this->authorizePreview();
        view()->share('smCampaignPreview', $campaign);

        return response(app(HomeController::class)())->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function authorizePreview(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'editor'], true), 403);
    }
}
