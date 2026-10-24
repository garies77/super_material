<?php

use App\Models\Product;
use App\Models\SalesChannel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('shows the uploaded channel logo on the product page instead of the built-in one', function () {
    SalesChannel::where('slug', 'shopee')->update(['logo' => 'channels/shopee-baru.svg']);
    $product = Product::published()->whereHas('links.channel', fn ($q) => $q->where('slug', 'shopee'))->first();

    $this->get("/produk/{$product->slug}")->assertOk()
        ->assertSee('storage/channels/shopee-baru.svg', false)
        ->assertSee('aria-label="Tokopedia"', false); // kanal tanpa unggahan tetap memakai logo bawaan
});

it('uses an uploaded logo for a brand-new channel without code changes', function () {
    $channel = SalesChannel::create(['name' => 'Blibli', 'slug' => 'blibli', 'type' => 'marketplace', 'color' => '#0095da',
        'allowed_hosts' => ['blibli.com'], 'logo' => 'channels/blibli.svg', 'is_active' => true]);
    $product = Product::published()->first();
    $product->links()->create(['sales_channel_id' => $channel->id, 'url' => 'https://www.blibli.com/p/x', 'is_active' => true]);

    $this->get("/produk/{$product->slug}")->assertOk()->assertSee('storage/channels/blibli.svg', false);
});
