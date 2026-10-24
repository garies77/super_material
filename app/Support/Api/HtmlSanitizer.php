<?php

namespace App\Support\Api;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as Sanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitasi HTML berbasis parser dengan allow-list (PRD Keamanan Pilar 1): skrip, atribut on*,
 * URL javascript:, iframe, objek, dan gaya inline dibuang. Dipakai halaman publik dan API.
 */
class HtmlSanitizer
{
    private static ?Sanitizer $sanitizer = null;

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        return self::sanitizer()->sanitize($html);
    }

    private static function sanitizer(): Sanitizer
    {
        return self::$sanitizer ??= new Sanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowAttribute('class', '*')
                ->allowAttribute('id', '*')
                ->allowAttribute('style', ['img', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div'])
                ->withAttributeSanitizer(new SafeStyleAttribute)
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowMediaSchemes(['http', 'https', 'data'])
                ->withMaxInputLength(2_000_000)
        );
    }
}
