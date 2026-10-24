<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;

/**
 * Pengaturan branding, baca saja. Tidak ada endpoint untuk mengubahnya (PRD 8.12.2 poin 6).
 *
 * @tags Situs
 */
class SiteController extends ApiController
{
    /**
     * Branding situs
     *
     * Nama, nama singkat, tagline, logo, favicon, palet warna, dan kontak.
     */
    public function __invoke(): JsonResponse
    {
        $whatsapp = preg_replace('/\D+/', '', (string) Setting::get('whatsapp_number'));
        if ($whatsapp !== '' && str_starts_with($whatsapp, '0')) {
            $whatsapp = '62'.substr($whatsapp, 1);
        }

        return response()->json([
            'data' => [
                'name' => Setting::brandName(),
                'short_name' => Setting::get('site_short_name') ?: Setting::brandName(),
                'tagline' => Setting::get('site_tagline'),
                'description' => Setting::get('site_description'),
                'url' => url('/'),
                'logo_url' => Setting::logoUrl(),
                'favicon_url' => Setting::imageUrl('site_favicon') ?: (file_exists(public_path('favicon.ico')) ? asset('favicon.ico') : null),
                'og_image_url' => Setting::defaultOgImage(),
                'colors' => [
                    'primary' => Setting::themeColor('theme_primary'),
                    'secondary' => Setting::themeColor('theme_secondary'),
                    'accent' => Setting::themeColor('theme_accent'),
                ],
                'contact' => [
                    'whatsapp' => $whatsapp ?: null,
                    'email' => Setting::get('contact_email'),
                    'address' => Setting::get('contact_address'),
                ],
            ],
        ]);
    }
}
