<?php

namespace App\Support\Reports;

use App\Models\LinkItem;
use App\Models\LinkPage;
use App\Models\LinkStat;
use App\Support\BotDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pencatat link-in-bio: agregat harian (WIB) per sumber & perangkat, tanpa IP atau data pribadi. Bot tidak dihitung.
 * Tayangan dihitung sekali per sesi per halaman; sumber kunjungan disimpan di sesi agar klik berikutnya ikut sumber yang sama
 * (referer saat klik selalu halaman kita sendiri).
 */
class LinkRecorder
{
    public const DIRECT = 'langsung';

    /** Host referer → nama sumber. Browser dalam aplikasi Instagram/TikTok sering tidak mengirim referer; pakai ?utm_source=. */
    private const SOURCES = [
        'instagram.com' => 'instagram',
        'tiktok.com' => 'tiktok',
        'facebook.com' => 'facebook',
        'fb.com' => 'facebook',
        'fb.me' => 'facebook',
        'threads.net' => 'threads',
        'threads.com' => 'threads',
        't.co' => 'x',
        'x.com' => 'x',
        'twitter.com' => 'x',
        'youtube.com' => 'youtube',
        'youtu.be' => 'youtube',
        'whatsapp.com' => 'whatsapp',
        'wa.me' => 'whatsapp',
        'linkedin.com' => 'linkedin',
        'lnkd.in' => 'linkedin',
        'pinterest.com' => 'pinterest',
        't.me' => 'telegram',
        'shopee.co.id' => 'shopee',
        'tokopedia.com' => 'tokopedia',
    ];

    public static function view(Request $request, LinkPage $page): void
    {
        if (BotDetector::isBot($request->userAgent())) {
            return;
        }

        $sourceKey = "link_source_{$page->id}";
        $source = self::detectSource($request);

        // utm_source eksplisit menimpa sumber lama; tanpa itu sumber pertama di sesi dipertahankan
        if ($request->filled('utm_source') || ! $request->session()->has($sourceKey)) {
            $request->session()->put($sourceKey, $source);
        }

        $viewedKey = "link_viewed_{$page->id}";
        if ($request->session()->has($viewedKey)) {
            return;
        }
        $request->session()->put($viewedKey, now()->timestamp);

        self::increment($page->id, LinkStat::PAGE_VIEW, $request->session()->get($sourceKey, $source), self::device($request->userAgent()));
    }

    public static function click(Request $request, LinkItem $item): void
    {
        if (BotDetector::isBot($request->userAgent())) {
            return;
        }

        $source = $request->session()->get("link_source_{$item->link_page_id}") ?? self::detectSource($request);

        self::increment($item->link_page_id, $item->id, $source, self::device($request->userAgent()));
    }

    public static function detectSource(Request $request): string
    {
        $utm = Str::limit(Str::slug((string) $request->query('utm_source')), 60, '');
        if ($utm !== '') {
            return $utm;
        }

        return self::sourceFromReferer((string) $request->headers->get('referer'));
    }

    public static function sourceFromReferer(string $referer): string
    {
        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        if ($host === '') {
            return self::DIRECT;
        }

        $own = strtolower((string) parse_url(url('/'), PHP_URL_HOST));
        if ($host === $own || str_ends_with($host, '.'.$own)) {
            return self::DIRECT;
        }

        foreach (self::SOURCES as $domain => $name) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $name;
            }
        }

        if (preg_match('/(^|\.)google\.[a-z.]+$/', $host)) {
            return 'google';
        }

        return Str::limit(preg_replace('/^(www|m|l|lm)\./', '', $host), 60, '');
    }

    public static function device(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        return match (true) {
            (bool) preg_match('/ipad|tablet|(android(?!.*mobile))/i', $ua) => 'tablet',
            (bool) preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|iemobile/i', $ua) => 'mobile',
            default => 'desktop',
        };
    }

    private static function increment(int $pageId, int $itemId, string $source, string $device): void
    {
        LinkStat::upsert(
            [[
                'date' => now(ClickReport::TIMEZONE)->toDateString(),
                'link_page_id' => $pageId,
                'link_item_id' => $itemId,
                'source' => $source,
                'device' => $device,
                'count' => 1,
            ]],
            ['date', 'link_page_id', 'link_item_id', 'source', 'device'],
            ['count' => DB::raw('count + 1')],
        );
    }
}
