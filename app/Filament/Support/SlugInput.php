<?php

namespace App\Filament\Support;

use App\Support\Catalog\ProductRules;
use Filament\Forms\Components\TextInput;

/**
 * Aturan slug yang sama dengan REST API dan MCP (ProductRules::SLUG_PATTERN): huruf kecil, angka, dan tanda hubung.
 * Tanpa ini, panel admin bisa menyimpan slug berspasi atau berhuruf besar yang membuat URL berisi %20.
 */
class SlugInput
{
    public const MESSAGE = 'Slug hanya boleh huruf kecil, angka, dan tanda hubung tanpa spasi, mis. palu-kambing-wolf.';

    public static function rules(TextInput $input): TextInput
    {
        return $input->regex(ProductRules::SLUG_PATTERN)
            ->validationMessages(['regex' => self::MESSAGE]);
    }
}
