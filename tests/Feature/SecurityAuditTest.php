<?php

use App\Filament\Resources\Media\MediaResource;
use App\Models\Ad;
use App\Models\Media;
use App\Models\SearchSynonym;
use App\Models\User;
use App\Support\CsvCell;
use App\Support\Reports\CsvExport;
use App\Support\Search;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('neutralises spreadsheet formulas in CSV cells but keeps numbers', function () {
    expect(CsvCell::row(['=HYPERLINK("https://jahat.example")', ' +1+1', '-2', '@SUM(A1)', "\tx", 'Semen', -12.5, 7, null]))->toBe([
        '\'=HYPERLINK("https://jahat.example")', "' +1+1", "'-2", "'@SUM(A1)", "'\tx", 'Semen', -12.5, 7, null,
    ]);

    $response = CsvExport::download('uji', ['term' => 'Kata kunci', 'searches' => 'Pencarian'], [['term' => '=1+1', 'searches' => 3]]);
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain("'=1+1,3")->not->toContain("\n=1+1");
});

it('treats synonym text literally, never as a regex backreference', function () {
    SearchSynonym::create(['term' => 'paralon', 'synonyms' => ['pipa $1 \\1']]);

    expect(Search::variants('paralon 3 inch'))->toBe(['paralon 3 inch', 'pipa $1 \\1 3 inch']);
});

it('rate limits the search page like live search', function () {
    expect(Route::getRoutes()->getByName('search')->gatherMiddleware())->toContain('throttle:120,1');
});

it('blocks campaign previews for deactivated editors', function () {
    $ad = Ad::create(['name' => 'Uji', 'slot' => 'top', 'format' => 'text', 'title' => 'Promo', 'target_url' => 'https://mitra.example.com', 'is_active' => true]);

    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => false]))
        ->get(route('ad.preview', $ad))->assertForbidden();
});

it('lets contributors edit only the media they uploaded', function () {
    $contributor = User::factory()->create(['role' => 'contributor', 'is_active' => true]);
    $own = Media::factory()->create(['uploaded_by' => $contributor->id]);
    $others = Media::factory()->create(['uploaded_by' => null]);

    $this->actingAs($contributor);
    expect(MediaResource::canEdit($own))->toBeTrue()
        ->and(MediaResource::canEdit($others))->toBeFalse();

    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]));
    expect(MediaResource::canEdit($others))->toBeTrue();
});
