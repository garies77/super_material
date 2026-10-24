<?php

namespace Database\Factories;

use App\Models\CustomForm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomForm>
 */
class CustomFormFactory extends Factory
{
    public function definition(): array
    {
        $title = 'Konsultasi '.fake()->unique()->words(2, true);

        return [
            'name' => $title,
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => 'Isi data Anda, tim kami akan menghubungi.',
            'display_mode' => 'both',
            'fields' => [
                ['label' => 'Nama lengkap', 'type' => 'text', 'required' => true],
                ['label' => 'Email', 'type' => 'email', 'required' => true],
                ['label' => 'Nomor WhatsApp', 'type' => 'phone'],
                ['label' => 'Kebutuhan', 'type' => 'select', 'options' => ['Semen', 'Besi', 'Keramik'], 'required' => true],
                ['label' => 'Material diminati', 'type' => 'checkbox', 'options' => ['Cat', 'Atap']],
                ['label' => 'Pesan', 'type' => 'textarea'],
            ],
            'success_message' => 'Terima kasih, data Anda sudah kami terima.',
            'is_active' => true,
        ];
    }
}
