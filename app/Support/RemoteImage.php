<?php

namespace App\Support;

use App\Rules\MinImagePixels;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Gambar dari URL (untuk MCP): diunduh lewat RemoteFile, divalidasi seperti unggahan panel admin,
 * lalu disimpan lewat Pustaka Media sehingga gambar identik dipakai ulang dan tercatat di menu Media.
 */
class RemoteImage
{
    /**
     * @param  string  $attribute  Nama argumen untuk pesan error validasi, mis. "image_url".
     * @param  bool  $withDerivatives  Buat turunan 16:9, 4:3, 1:1 (untuk foto utama artikel).
     * @return string path di disk publik
     *
     * @throws ValidationException
     */
    public static function store(string $url, string $attribute, string $directory, string $nameSource, int $minPixels, bool $withDerivatives = false): string
    {
        $maxKilobytes = (int) config('api.uploads.image_max_kb');
        $file = RemoteFile::download($url, $maxKilobytes, $attribute);

        try {
            Validator::make([$attribute => $file], [
                $attribute => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKilobytes, new MinImagePixels($minPixels)],
            ])->validate();

            $path = MediaLibrary::storeUploadedFile($file, $directory, $nameSource);
            if ($withDerivatives) {
                ImageDerivatives::multiRatioUrls(storage_path('app/public/'.$path));
            }

            return $path;
        } finally {
            @unlink($file->getPathname());
        }
    }
}
