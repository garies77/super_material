<?php

namespace App\Filament\Support;

use Closure;
use Filament\Forms\Components\FileUpload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * FileUpload yang juga memvalidasi berkas yang sudah tersimpan (path dari Pustaka Media atau data lama).
 * Rule bawaan Filament hanya berlaku untuk unggahan baru, sehingga gambar pilihan pustaka lolos tanpa diperiksa.
 */
class StrictFileUpload extends FileUpload
{
    /** @var array<int, ValidationRule> */
    protected array $storedFileRules = [];

    /** Rule yang menerima path relatif di disk field ini selain unggahan baru. */
    public function storedFileRule(ValidationRule $rule): static
    {
        $this->storedFileRules[] = $rule;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getValidationRules(): array
    {
        $rules = parent::getValidationRules();

        if ($this->storedFileRules !== []) {
            $rules[] = function (string $attribute, mixed $value, Closure $fail): void {
                foreach (array_filter(Arr::wrap($value), 'is_string') as $path) {
                    foreach ($this->storedFileRules as $rule) {
                        $rule->validate($attribute, $path, $fail);
                    }
                }
            };
        }

        return $rules;
    }
}
