<?php

namespace App\Filament\Support;

use Closure;
use Filament\Forms\Components\Field;

/**
 * Klik pada gambar untuk menandai bagian penting. State: ['x' => 0..100, 'y' => 0..100] dalam persen.
 */
class FocalPointPicker extends Field
{
    protected string $view = 'filament.forms.components.focal-point-picker';

    protected string|Closure|null $imageUrl = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(['x' => 50, 'y' => 50]);
    }

    public function image(string|Closure|null $url): static
    {
        $this->imageUrl = $url;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->evaluate($this->imageUrl);
    }
}
