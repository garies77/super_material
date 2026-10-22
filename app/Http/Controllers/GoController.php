<?php

namespace App\Http\Controllers;

use App\Models\ClickEvent;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Support\BotDetector;
use Illuminate\Http\Request;

/**
 * Rute pengalihan /go/{produk}/{kanal}: mencatat klik lalu redirect 302.
 * Tujuan hanya domain yang diizinkan per kanal (tidak bisa jadi open redirect).
 */
class GoController extends Controller
{
    public function __invoke(Request $request, string $product, string $channel)
    {
        $product = Product::published()->where('slug', $product)->firstOrFail();
        $channel = SalesChannel::where('slug', $channel)->where('is_active', true)->firstOrFail();

        $variant = null;
        if ($request->filled('variant')) {
            $variant = $product->variants()->where('id', $request->input('variant'))
                ->orWhere('slug', $request->input('variant'))
                ->first();
        }
        $quantity = max(1, $request->integer('qty', 1));

        $destination = $product->destinationFor($channel, $variant, $quantity);
        if (! $destination) {
            return redirect($product->url(), 302);
        }

        $ua = (string) $request->userAgent();
        $isBot = BotDetector::isBot($ua);

        // Tidak menyimpan IP mentah; user agent hanya dipotong ringkas.
        ClickEvent::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id ?: ($request->integer('variant') ?: null),
            'sales_channel_id' => $channel->id,
            'page_url' => mb_substr((string) $request->headers->get('referer'), 0, 2000) ?: null,
            'referrer' => mb_substr((string) $request->query('ref'), 0, 2000) ?: null,
            'utm_source' => mb_substr((string) $request->query('utm_source'), 0, 255) ?: null,
            'utm_medium' => mb_substr((string) $request->query('utm_medium'), 0, 255) ?: null,
            'utm_campaign' => mb_substr((string) $request->query('utm_campaign'), 0, 255) ?: null,
            'user_agent' => mb_substr($ua, 0, 120),
            'is_bot' => $isBot,
            'created_at' => now(),
        ]);

        return redirect()->away($destination, 302)->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
