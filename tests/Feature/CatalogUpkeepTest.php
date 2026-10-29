<?php

use App\Filament\Reports\Widgets\ZeroResultSearchesTable;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\SearchSynonyms\Pages\ManageSearchSynonyms;
use App\Filament\Resources\SearchSynonyms\SearchSynonymResource;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SearchStat;
use App\Models\SearchSynonym;
use App\Models\User;
use App\Support\AttentionBoard;
use App\Support\Search;
use Filament\Pages\Dashboard as AdminDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-10-20 12:00', 'Asia/Jakarta')));

afterEach(fn () => Carbon::setTestNow());

function editor(): User
{
    return User::factory()->create(['role' => 'editor', 'is_active' => true]);
}

it('extends price validity and changes category or brand for many products at once', function () {
    $this->actingAs(editor());
    $expired = collect(['Semen', 'Bata'])->map(fn (string $name) => Product::create([
        'name' => $name, 'slug' => strtolower($name), 'is_published' => true,
        'price_valid_until' => now()->subDay(), 'price_review_needed' => true,
    ]));
    $category = Category::create(['name' => 'Bahan Dinding', 'slug' => 'bahan-dinding']);
    $brand = Brand::create(['name' => 'Citicon', 'slug' => 'citicon']);

    Livewire::test(ListProducts::class)
        ->callTableBulkAction('extendPriceValidity', $expired, data: ['price_valid_until' => '2027-01-31'])
        ->assertNotified('2 produk: masa harga diperpanjang')
        ->callTableBulkAction('setCategory', $expired, data: ['category_id' => $category->id])
        ->callTableBulkAction('setBrand', $expired, data: ['brand_id' => $brand->id]);

    $expired->each(fn (Product $product) => expect($product->fresh())
        ->price_valid_until->toDateString()->toBe('2027-01-31')
        ->price_review_needed->toBeFalse()
        ->category_id->toBe($category->id)
        ->brand_id->toBe($brand->id));
    expect(AuditLog::where('action', 'product.bulk_updated')->count())->toBe(3);
});

it('hides bulk editing from contributors', function () {
    $this->actingAs(User::factory()->create(['role' => 'contributor', 'is_active' => true]));

    Livewire::test(ListProducts::class)
        ->assertTableBulkActionHidden('extendPriceValidity')
        ->assertTableBulkActionHidden('setCategory')
        ->assertTableBulkActionHidden('setBrand');
});

it('finds products through search synonyms, also inside longer queries', function () {
    Product::create(['name' => 'Bata Ringan Citicon 10 cm', 'slug' => 'bata-ringan', 'is_published' => true]);

    $this->get('/cari?q=hebel')->assertOk()->assertDontSee('Bata Ringan Citicon');
    expect(Search::variants('Hebel 10 cm'))->toBe(['Hebel 10 cm']);

    $synonym = SearchSynonym::create(['term' => '  Hebel ', 'synonyms' => ['Bata  Ringan', 'hebel', '']]);

    expect($synonym->fresh())->term->toBe('hebel')->synonyms->toBe(['bata ringan'])
        ->and(Search::variants('Hebel 10 cm'))->toBe(['Hebel 10 cm', 'bata ringan 10 cm'])
        ->and(Search::variants('hebelan'))->toBe(['hebelan']);

    $this->flushSession()->get('/cari?q=hebel')->assertOk()->assertSee('Bata Ringan Citicon');
    $this->getJson('/cari/live?q=hebel')->assertOk()->assertJsonPath('products.0.title', 'Bata Ringan Citicon 10 cm');
    expect(SearchStat::where('term', 'hebel')->latest('id')->value('results'))->toBe(1);
});

it('lets editors manage synonyms and add one straight from searches without results', function () {
    $this->actingAs(User::factory()->create(['role' => 'contributor', 'is_active' => true]))
        ->get(SearchSynonymResource::getUrl())->assertForbidden();

    $this->actingAs(editor());
    Livewire::test(ManageSearchSynonyms::class)
        ->callAction('create', data: ['term' => 'galvalum', 'synonyms' => ['baja ringan']])
        ->assertHasNoActionErrors();
    expect(SearchSynonym::where('term', 'galvalum')->value('synonyms'))->toBe(['baja ringan']);

    SearchStat::insert(['date' => '2026-10-19', 'term' => 'hebel', 'searches' => 4, 'results' => 0]);
    Livewire::test(ZeroResultSearchesTable::class, ['pageFilters' => ['period' => '7d']])
        ->callTableAction('addSynonym', 's'.md5('hebel'), data: ['synonyms' => ['bata ringan']])
        ->assertNotified('Sinonim disimpan');

    expect(SearchSynonym::map())->toMatchArray(['hebel' => ['bata ringan'], 'galvalum' => ['baja ringan']]);
});

it('shows editors what needs attention on the dashboard', function () {
    $editor = editor();
    $contributor = User::factory()->create(['role' => 'contributor', 'is_active' => true]);
    Product::create(['name' => 'Kedaluwarsa', 'slug' => 'a', 'is_published' => true, 'price_review_needed' => true]);
    Product::create(['name' => 'Segera habis', 'slug' => 'b', 'is_published' => true, 'price_valid_until' => now()->addDays(3)]);
    Product::create(['name' => 'Menunggu lama', 'slug' => 'c', 'review_status' => 'pending', 'submitted_by' => $contributor->id, 'submitted_at' => now()->subDays(3)]);
    SearchStat::insert([
        ['date' => '2026-10-19', 'term' => 'hebel', 'searches' => 4, 'results' => 0],
        ['date' => '2026-10-19', 'term' => 'galvalum', 'searches' => 2, 'results' => 0],
    ]);
    SearchSynonym::create(['term' => 'galvalum', 'synonyms' => ['baja ringan']]);

    expect(collect(AttentionBoard::for($editor))->pluck('count', 'title')->all())->toBe([
        'Harga produk kedaluwarsa' => 1,
        'Masa harga habis dalam 7 hari' => 1,
        'Kiriman menunggu lebih dari 2 hari' => 1,
        'Pencarian tanpa hasil minggu ini' => 1,
    ])->and(AttentionBoard::for($contributor))->toBe([]);

    $this->actingAs($editor)->get(AdminDashboard::getUrl())->assertOk()
        ->assertSee('Perlu perhatian')->assertSee('Harga produk kedaluwarsa')
        ->assertSee('price_review_needed', false);
});
