<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    public static function all_(): array
    {
        // memo: dibaca puluhan kali per halaman; cukup satu kali ambil dari cache store per request
        return Cache::memo()->remember('settings.all', 3600, fn () => static::query()->pluck('value', 'key')->all());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::all_()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = static::get($key);
        if ($val === null) {
            return $default;
        }

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public static function imageUrl(string $key, ?string $default = null): ?string
    {
        $val = static::get($key);
        if (! $val) {
            return $default;
        }

        if (str_starts_with($val, 'http://') || str_starts_with($val, 'https://')) {
            return $val;
        }

        return asset('storage/'.ltrim($val, '/'));
    }

    public static function logoUrl(?string $default = null): ?string
    {
        return static::imageUrl('site_logo') ?: static::imageUrl('org_logo_url', $default);
    }

    public static function defaultOgImage(): ?string
    {
        return static::imageUrl('site_og_image')
            ?: (static::getBool('hero_banner_active', true) ? static::imageUrl('hero_banner_image') : null)
            ?: static::logoUrl();
    }

    public static function brandName(): string
    {
        return static::get('site_brand_name') ?: static::get('org_name', config('app.name', 'Supermaterial'));
    }

    public static function brandAccent(): ?string
    {
        return static::get('site_brand_accent', 'material');
    }

    public static function brandHtml(string $accentClass = 'text-brand-600'): string
    {
        $name = e(static::brandName());
        $accent = trim((string) static::brandAccent());

        if ($accent !== '' && ($pos = stripos($name, $accent)) !== false) {
            $before = substr($name, 0, $pos);
            $match = substr($name, $pos, strlen($accent));
            $after = substr($name, $pos + strlen($accent));

            return $before.'<span class="'.$accentClass.'">'.$match.'</span>'.$after;
        }

        return $name;
    }

    public const THEME_DEFAULTS = [
        'theme_primary' => '#f59309',
        'theme_secondary' => '#121826',
        'theme_accent' => '#ffae1f',
    ];

    public static function themeColor(string $key): string
    {
        $val = (string) static::get($key, static::THEME_DEFAULTS[$key]);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $val) ? strtolower($val) : static::THEME_DEFAULTS[$key];
    }

    /** Campur dua warna hex; $ratio = porsi warna kedua (0-1). */
    protected static function mixHex(string $a, string $b, float $ratio): string
    {
        $out = '#';
        foreach ([1, 3, 5] as $i) {
            $x = hexdec(substr($a, $i, 2));
            $y = hexdec(substr($b, $i, 2));
            $out .= str_pad(dechex((int) round($x * (1 - $ratio) + $y * $ratio)), 2, '0', STR_PAD_LEFT);
        }

        return $out;
    }

    /** Rasio kontras WCAG 2 antara dua warna hex. */
    public static function contrast(string $a, string $b): float
    {
        $lum = function (string $hex) {
            $c = array_map(function ($i) use ($hex) {
                $v = hexdec(substr($hex, $i, 2)) / 255;

                return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            }, [1, 3, 5]);

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$hi, $lo] = [max($lum($a), $lum($b)), min($lum($a), $lum($b))];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    /** CSS variabel tema (menimpa token Tailwind brand-* dan ink-*). */
    public static function themeCss(): string
    {
        $p = static::themeColor('theme_primary');
        $s = static::themeColor('theme_secondary');
        $a = static::themeColor('theme_accent');

        $vars = [
            'brand-50' => static::mixHex($p, '#ffffff', 0.92),
            'brand-100' => static::mixHex($p, '#ffffff', 0.8),
            'brand-300' => static::mixHex($a, '#ffffff', 0.3),
            'brand-400' => $a,
            'brand-500' => $p,
            'brand-600' => static::mixHex($p, '#000000', 0.15),
            'brand-700' => static::mixHex($p, '#000000', 0.32),
            'ink-800' => static::mixHex($s, '#ffffff', 0.12),
            'ink-900' => $s,
            'ink-950' => static::mixHex($s, '#000000', 0.4),
        ];

        // Teks di atas warna aksen: hitam atau putih, mana yang kontrasnya lebih tinggi (WCAG)
        $vars['on-accent'] = static::contrast($a, '#000000') >= static::contrast($a, '#ffffff') ? '#111111' : '#ffffff';

        $css = '';
        foreach ($vars as $name => $hex) {
            $css .= "--color-{$name}:{$hex};";
        }

        return ':root{'.$css.'}';
    }

    public static function put(string $key, mixed $value): void
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_array($value)) {
            if (count($value) === 1 && isset($value[0]) && is_string($value[0])) {
                $value = $value[0];
            } else {
                $value = json_encode($value);
            }
        } elseif ($value !== null) {
            $value = (string) $value;
        }

        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::memo()->forget('settings.all');
    }
}
