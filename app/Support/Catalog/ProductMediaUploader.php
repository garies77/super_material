<?php

namespace App\Support\Catalog;

use App\Filament\Support\DescriptiveFileName;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Rules\MinImagePixels;
use App\Support\ImageDerivatives;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Unggah media produk lewat API dengan pipeline yang sama seperti panel admin (PRD 9.13.3):
 * nama file deskriptif, minimum 50.000 piksel, dan tiga turunan rasio 16:9, 4:3, 1:1.
 */
class ProductMediaUploader
{
    public const DIRECTORY = 'products';

    /** @return array<string, mixed> */
    public static function rules(Request $request): array
    {
        $type = $request->input('type', 'image');
        $fileRules = $type === 'video'
            ? ['mimes:mp4,webm', 'max:'.config('api.uploads.video_max_kb')]
            : ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('api.uploads.image_max_kb'), new MinImagePixels(50000)];

        return [
            'type' => ['sometimes', Rule::in(['image', 'video'])],
            'file' => array_merge(['required_without:youtube_url', 'prohibits:youtube_url', 'file'], $fileRules),
            'youtube_url' => ['required_without:file', 'url:https', 'max:500', 'regex:#^https://(www\.)?(youtube\.com|youtu\.be|m\.youtube\.com)/#i'],
            'alt_text' => ['required', 'string', 'max:255'],
            'poster_url' => ['nullable', 'url:http,https', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public static function store(Product $product, array $data): ProductMedia
    {
        $sort = $data['sort_order'] ?? ((int) $product->media()->max('sort_order') + 1);

        if (! empty($data['youtube_url'])) {
            return $product->media()->create([
                'type' => 'video',
                'provider' => 'youtube',
                'path' => $data['youtube_url'],
                'poster_path' => $data['poster_url'] ?? null,
                'alt_text' => $data['alt_text'],
                'sort_order' => $sort,
            ]);
        }

        /** @var UploadedFile $file */
        $file = $data['file'];
        $type = ($data['type'] ?? 'image') === 'video' ? 'video' : 'image';
        $name = DescriptiveFileName::for($file, $product->slug, self::DIRECTORY, $type === 'video' ? 'video' : null);
        $path = $file->storeAs(self::DIRECTORY, $name, 'public');

        if ($type === 'image') {
            ImageDerivatives::multiRatioUrls(storage_path('app/public/'.$path));
        }

        $media = $product->media()->create([
            'type' => $type,
            'provider' => 'upload',
            'path' => $path,
            'poster_path' => $data['poster_url'] ?? null,
            'alt_text' => $data['alt_text'],
            'sort_order' => $sort,
        ]);

        $product->touch();

        return $media;
    }
}
