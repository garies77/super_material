<?php

use App\Models\ClickEvent;
use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('renders single product page with Shopee-style layout and components', function () {
    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->first();

    $response = $this->get('/produk/' . $product->slug);

    $response->assertOk()
        ->assertSee($product->name)
        ->assertSee('Resmi') // Badge
        ->assertSee('4.9') // Rating value
        ->assertSee('Penilaian') // Rating count text
        ->assertSee('Pengiriman:')
        ->assertSee('Jaminan:')
        ->assertSee('Spesifikasi Produk')
        ->assertSee('Deskripsi Produk')
        ->assertSee('Pertanyaan Umum (FAQ)')
        ->assertSee('Produk Terkait')
        ->assertSee('Chat WhatsApp')
        ->assertSee('Beli Sekarang')
        ->assertSee('Sak 40 kg') // Variant 1
        ->assertSee('Sak 50 kg') // Variant 2
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type":"Product"', false)
        ->assertSee('"@type":"AggregateRating"', false)
        ->assertSee('"@type":"VideoObject"', false);
});

it('supports variant query parameter in single product URL', function () {
    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->first();
    $variant = $product->variants->firstWhere('slug', 'sak-40-kg');

    $response = $this->get('/produk/' . $product->slug . '?varian=' . $variant->slug);

    $response->assertOk()
        ->assertSee('Sak 40 kg')
        ->assertSee('initialVariantId: ' . $variant->id, false);
});

it('records variant and redirects with quantity via /go route', function () {
    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->first();
    $variant = $product->variants->first();

    $response = $this->get("/go/{$product->slug}/shopee?variant={$variant->id}&qty=5");

    $response->assertRedirect()->assertStatus(302);

    expect(ClickEvent::count())->toBe(1);
    $event = ClickEvent::first();
    expect($event->product_id)->toBe($product->id)
        ->and($event->product_variant_id)->toBe($variant->id);
});

it('includes variant and quantity in WhatsApp redirection message', function () {
    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->first();
    $variant = $product->variants->first();

    $response = $this->get("/go/{$product->slug}/whatsapp?variant={$variant->id}&qty=10");

    $response->assertStatus(302);
    $location = urldecode($response->headers->get('Location'));
    expect($location)->toContain('Varian: ' . $variant->name)
        ->and($location)->toContain('Jumlah: 10 sak');
});
