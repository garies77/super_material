<?php

namespace Database\Factories;

use App\Models\LinkItem;
use App\Models\LinkPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkItem>
 */
class LinkItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'link_page_id' => LinkPage::factory(),
            'type' => 'link',
            'label' => 'Lihat '.fake()->words(2, true),
            'link_type' => 'external',
            'url' => 'https://mitra.example.com/'.fake()->slug(2),
            'new_tab' => true,
            'is_active' => true,
            'sort' => 0,
        ];
    }

    public function heading(): static
    {
        return $this->state(['type' => 'heading', 'label' => 'Layanan kami', 'link_type' => null, 'url' => null]);
    }

    public function whatsapp(string $number = '081234567890'): static
    {
        return $this->state(['type' => 'whatsapp', 'label' => 'Konsultasi via WhatsApp', 'link_type' => null, 'url' => null,
            'whatsapp_number' => $number, 'whatsapp_message' => 'Halo, saya mau tanya']);
    }
}
