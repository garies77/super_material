<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class MinImagePixels implements ValidationRule
{
    public function __construct(protected int $minPixels = 50000) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $file = null;

        if ($value instanceof UploadedFile) {
            $file = $value->getRealPath();
        } elseif (is_string($value) && file_exists($value)) {
            $file = $value;
        }

        if ($file) {
            $size = @getimagesize($file);
            if ($size && ! empty($size[0]) && ! empty($size[1])) {
                $totalPixels = $size[0] * $size[1];
                if ($totalPixels < $this->minPixels) {
                    $fail("Resolusi gambar ({$size[0]}×{$size[1]} = ".number_format($totalPixels, 0, ',', '.').' px) di bawah standar minimum Google Images ('.number_format($this->minPixels, 0, ',', '.').' px). Unggah gambar beresolusi lebih tinggi (PRD 9.13.3 & SEO-01).');
                }
            }
        }
    }
}
