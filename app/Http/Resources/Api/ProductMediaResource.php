<?php

namespace App\Http\Resources\Api;

use App\Models\ProductMedia;
use Illuminate\Http\Request;

/** @mixin ProductMedia */
class ProductMediaResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        /** @var ProductMedia $media */
        $media = $this->resource;
        [$width, $height] = self::dimensions($media);

        return [
            'id' => $media->id,
            'type' => $media->type,
            'provider' => $media->provider,
            'url' => $media->url(),
            'alt' => $media->alt_text,
            'width' => $width,
            'height' => $height,
            'poster_url' => $media->posterUrl(),
            'embed_url' => $media->embedUrl(),
        ];
    }

    /** @return array{0: ?int, 1: ?int} */
    private static function dimensions(ProductMedia $media): array
    {
        if (! $media->isImage() || $media->provider !== 'upload' || str_contains((string) $media->path, '://')) {
            return [null, null];
        }

        $path = storage_path('app/public/'.ltrim((string) $media->path, '/'));
        $size = is_file($path) ? @getimagesize($path) : false;

        return $size ? [(int) $size[0], (int) $size[1]] : [null, null];
    }
}
