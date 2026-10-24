<?php

namespace App\Support\Api;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Atribut style hanya menyisakan properti tata letak yang aman: lebar gambar hasil resize
 * dan perataan teks. Tinggi gambar sengaja dibuang agar rasio terjaga saat layar menyempit.
 */
class SafeStyleAttribute implements AttributeSanitizerInterface
{
    private const ALLOWED = [
        'width' => '/^\d{1,4}(\.\d+)?(px|%)$/',
        'max-width' => '/^\d{1,4}(\.\d+)?(px|%)$/',
        'text-align' => '/^(left|right|center|justify|start|end)$/',
    ];

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $kept = [];
        foreach (explode(';', $value) as $declaration) {
            [$property, $val] = array_map('trim', explode(':', $declaration, 2) + [1 => '']);
            $property = strtolower($property);
            if (isset(self::ALLOWED[$property]) && preg_match(self::ALLOWED[$property], strtolower($val))) {
                $kept[] = "{$property}: {$val}";
            }
        }

        return $kept ? implode('; ', $kept) : null;
    }
}
