<?php

namespace App\Support;

use App\Filament\Support\DescriptiveFileName;
use App\Filament\Support\ImagePreset;
use App\Models\AuditLog;
use App\Models\Media;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Pustaka media: mendaftarkan berkas gambar di disk publik dan mencegah unggahan ganda.
 * Berkas yang isinya identik (hash sha256 sama) tidak disimpan ulang; path lama yang dipakai.
 */
class MediaLibrary
{
    /** Direktori turunan otomatis dan sementara; bukan aset yang dipilih pengguna. */
    public const IGNORED_DIRECTORIES = ['derivatives', 'livewire-tmp'];

    /**
     * Penyimpan unggahan untuk setiap FileUpload Filament (dipasang di AppServiceProvider).
     * Unggahan ke disk publik dicek duplikatnya lalu didaftarkan; disk lain disimpan seperti biasa.
     */
    public static function storeFileUpload(BaseFileUpload $component, TemporaryUploadedFile $file): ?string
    {
        if ($component->getDiskName() !== Media::DISK || ! self::isImageMime((string) $file->getMimeType())) {
            return $component->saveUploadedFile($file);
        }

        if ($existing = self::findDuplicate($file->getRealPath())) {
            return $existing->path;
        }

        $path = $component->saveUploadedFile($file);

        if ($path) {
            self::register($path, $file->getClientOriginalName());
        }

        return $path;
    }

    /**
     * Simpan unggahan di luar FileUpload Filament (modal pustaka, API) dengan nama berkas deskriptif.
     * SVG dibersihkan dulu; berkas identik yang sudah terdaftar dipakai ulang.
     */
    public static function storeUploadedFile(UploadedFile $file, string $directory, string $nameSource, ?string $suffix = null): string
    {
        SvgSanitizer::cleanInPlace($file);

        if ($existing = self::findDuplicate($file->getRealPath())) {
            return $existing->path;
        }

        $path = $file->storeAs($directory, DescriptiveFileName::for($file, $nameSource, $directory, $suffix), Media::DISK);
        self::register($path, $file->getClientOriginalName());

        return $path;
    }

    /** Berkas terdaftar yang isinya sama persis dengan berkas lokal ini dan masih ada di disk. */
    public static function findDuplicate(string $localPath): ?Media
    {
        $hash = hash_file('sha256', $localPath);

        return Media::where('hash', $hash)->get()
            ->first(fn (Media $media) => Storage::disk(Media::DISK)->exists($media->path));
    }

    /**
     * Daftarkan berkas yang sudah ada di disk publik. Mengembalikan null bila berkas tidak ada,
     * bukan gambar, atau berada di direktori turunan/sementara.
     */
    public static function register(string $path, ?string $originalName = null): ?Media
    {
        $path = ltrim($path, '/');
        $disk = Storage::disk(Media::DISK);

        if (in_array(self::folderFor($path), self::IGNORED_DIRECTORIES, true) || ! $disk->exists($path)) {
            return null;
        }

        if ($media = Media::firstWhere('path', $path)) {
            return $media;
        }

        $mime = (string) $disk->mimeType($path);
        if (! self::isImageMime($mime)) {
            return null;
        }

        $absolute = $disk->path($path);
        $dimensions = ImageDerivatives::dimensions($absolute);

        $media = Media::create([
            'path' => $path,
            'folder' => self::folderFor($path),
            'name' => Str::limit($originalName ?: basename($path), 250, ''),
            'mime_type' => $mime,
            'size' => (int) $disk->size($path),
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'hash' => hash_file('sha256', $absolute),
            'uploaded_by' => auth()->id(),
        ]);

        // Sekali saat unggah, bukan saat grid dirender
        $media->generateThumbnail();

        return $media;
    }

    /**
     * Salinan gambar yang dipotong tengah ke rasio section dan diperkecil ke lebar maksimum (tanpa memperbesar).
     * Berkas asli tidak diubah karena bisa dipakai konten lain. SVG dan format lain dikembalikan apa adanya.
     */
    public static function cropToRatio(Media $media, float $ratio, ?int $maxWidth = null): Media
    {
        if (! $media->isCroppable()) {
            return $media;
        }

        $cropWidth = min($media->width, (int) floor($media->height * $ratio));
        $width = min($cropWidth, $maxWidth ?? $cropWidth);
        $height = (int) round($width / $ratio);
        // Titik fokus selain tengah masuk ke nama berkas: potongan lama yang sudah dipakai konten tidak tertimpa
        $focal = [$media->focal_x ?? 50, $media->focal_y ?? 50];
        $focalSuffix = $focal === [50, 50] ? '' : "-f{$focal[0]}-{$focal[1]}";
        $path = self::folderFor($media->path).'/'.pathinfo($media->path, PATHINFO_FILENAME)."-{$width}x{$height}{$focalSuffix}.webp";

        if ($existing = Media::firstWhere('path', $path)) {
            return $existing;
        }

        $disk = Storage::disk(Media::DISK);
        if (! ImageDerivatives::createDerivative($disk->path($media->path), $disk->path($path), $width, $height, $focal[0], $focal[1]) || ! $disk->exists($path)) {
            return $media;
        }

        $crop = self::register($path, basename($path));
        $crop?->update(['alt_text' => $media->alt_text]);

        return $crop ?? $media;
    }

    /**
     * Hapus berkas pustaka beserta thumbnail-nya. Pemanggil wajib memastikan berkas tidak dipakai
     * (MediaUsage); fungsi ini sendiri tidak memeriksa pemakaian.
     */
    public static function delete(Media $media): void
    {
        $disk = Storage::disk(Media::DISK);
        $disk->delete($media->path);

        if ($media->thumbnail_path && $media->thumbnail_path !== $media->path && str_starts_with($media->thumbnail_path, 'derivatives/')) {
            $disk->delete($media->thumbnail_path);
        }

        AuditLog::record(request(), 'media.deleted', $media, ['path' => $media->path]);
        $media->delete();
    }

    /**
     * Cari gambar pustaka dari teks yang ditulis admin (mis. di Excel): path lengkap, nama, atau nama berkas.
     * Bila beberapa gambar bernama sama, yang terbaru dipakai.
     */
    public static function findByReference(string $reference): ?Media
    {
        $reference = trim(str_replace('\\', '/', $reference), " /\t");

        if ($reference === '') {
            return null;
        }

        return Media::where('path', $reference)->first()
            ?? Media::whereRaw('LOWER(name) = ?', [mb_strtolower($reference)])->latest('id')->first()
            ?? Media::where('path', 'like', '%/'.$reference)->latest('id')->get()
                ->first(fn (Media $media) => strcasecmp(basename($media->path), $reference) === 0);
    }

    /** Sesuaikan gambar dengan preset section (config/media.php): rasio lain dibuatkan salinan terpotong. */
    public static function fitToPreset(Media $media, string $presetKey): Media
    {
        $preset = ImagePreset::get($presetKey);
        $ratio = ImagePreset::singleRatio($preset);

        return $ratio === null || $media->matchesRatio($ratio) ? $media : self::cropToRatio($media, $ratio, $preset['width'] ?? null);
    }

    public static function folderFor(string $path): string
    {
        return str_contains($path, '/') ? Str::before($path, '/') : 'media';
    }

    public static function isImageMime(string $mime): bool
    {
        return str_starts_with($mime, 'image/');
    }
}
