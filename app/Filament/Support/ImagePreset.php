<?php

namespace App\Filament\Support;

use Closure;
use Filament\Forms\Components\FileUpload;

/**
 * Menerapkan ukuran baku section (config/media.php) ke field FileUpload: editor potong, rasio wajib,
 * dan resize di browser. Preset bisa berupa closure (mis. bergantung pada format kampanye).
 */
class ImagePreset
{
    /**
     * @return array{label: string, ratio: string|array<int, string>|null, options?: array<int, string|null>, width: ?int, height: ?int}|null
     */
    public static function get(?string $key): ?array
    {
        return $key ? config("media.presets.{$key}") : null;
    }

    /** Satu rasio wajib (bukan daftar) sebagai angka lebar/tinggi; null bila bebas atau ada beberapa pilihan. */
    public static function singleRatio(?array $preset): ?float
    {
        $ratio = $preset['ratio'] ?? null;

        if (! is_string($ratio) || ! str_contains($ratio, ':')) {
            return null;
        }

        [$width, $height] = array_map('floatval', explode(':', $ratio));

        return $height > 0 ? $width / $height : null;
    }

    public static function apply(FileUpload $upload, string|Closure $preset): FileUpload
    {
        $resolve = fn (FileUpload $component): ?array => self::get($component->evaluate($preset));
        $isSingleRatio = fn (FileUpload $component): bool => self::singleRatio($resolve($component)) !== null;

        return $upload
            ->imageEditor()
            ->imageEditorAspectRatioOptions(fn (FileUpload $component): array => array_values(array_unique(
                $resolve($component)['options'] ?? (array) ($resolve($component)['ratio'] ?? [null]),
                SORT_REGULAR,
            )))
            ->imageAspectRatio(fn (FileUpload $component) => $resolve($component)['ratio'] ?? null)
            // Satu gambar: editor terbuka bila rasio tidak cocok. Galeri: potong tengah otomatis (bisa disunting per gambar).
            ->automaticallyOpenImageEditorForAspectRatio(fn (FileUpload $component): bool => ! $component->isMultiple() && $isSingleRatio($component))
            ->automaticallyCropImagesToAspectRatio(fn (FileUpload $component): bool => $component->isMultiple() && $isSingleRatio($component))
            ->automaticallyResizeImagesMode(fn (FileUpload $component): string => filled($resolve($component)['height'] ?? null) ? 'cover' : 'contain')
            ->automaticallyResizeImagesToWidth(fn (FileUpload $component): ?string => self::dimension($resolve($component), 'width'))
            ->automaticallyResizeImagesToHeight(fn (FileUpload $component): ?string => self::dimension($resolve($component), 'height'))
            ->automaticallyUpscaleImagesWhenResizing(false);
    }

    private static function dimension(?array $preset, string $key): ?string
    {
        return filled($preset[$key] ?? null) ? (string) $preset[$key] : null;
    }
}
