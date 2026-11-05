<?php

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\Product;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\DatabaseSeeder;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->article = Article::where('slug', 'cara-memilih-semen-untuk-rumah')->firstOrFail();
});

function articleLd(TestResponse $response): array
{
    return collect(jsonLdBlocks($response))->firstWhere('headline');
}

it('marks up the primary article video with clips and shows it in place of the hero image', function () {
    $video = Video::factory()->for($this->article, 'videoable')->primary()->withClips()->create([
        'url' => 'https://www.youtube.com/watch?v=abcDEF12345',
        'title' => 'Cara memilih semen',
        'upload_date' => '2026-09-14 03:00:00',
    ]);

    $response = $this->get($this->article->url())->assertOk()
        ->assertSee('https://www.youtube-nocookie.com/embed/abcDEF12345', false)
        ->assertSee('data-video-seek="90"', false)
        ->assertDontSee('fetchpriority="high"', false);

    $ld = articleLd($response)['video'];
    expect($ld)->toMatchArray([
        '@type' => 'VideoObject',
        'name' => 'Cara memilih semen',
        'description' => $video->description,
        'thumbnailUrl' => ['https://i.ytimg.com/vi/abcDEF12345/hqdefault.jpg'],
        'uploadDate' => $video->upload_date->toIso8601String(),
        'duration' => 'PT5M',
        'embedUrl' => 'https://www.youtube-nocookie.com/embed/abcDEF12345',
    ])->not->toHaveKey('contentUrl');

    expect($ld['hasPart'])->toHaveCount(3)
        ->and($ld['hasPart'][1])->toBe([
            '@type' => 'Clip',
            'name' => 'Mengaduk semen',
            'startOffset' => 90,
            'endOffset' => 210,
            'url' => $this->article->url().'?t=90',
        ])
        ->and($ld['hasPart'][2]['endOffset'])->toBe(300);
});

it('lists several videos with the primary one first', function () {
    Video::factory()->for($this->article, 'videoable')->create(['title' => 'Video tambahan', 'sort_order' => 0]);
    Video::factory()->for($this->article, 'videoable')->primary()->create(['title' => 'Video utama', 'sort_order' => 1]);

    $response = $this->get($this->article->url())->assertOk()->assertSee('Video Terkait');

    expect(array_column(articleLd($response)['video'], 'name'))->toBe(['Video utama', 'Video tambahan']);
});

it('uses the time the video was added when no upload date is filled in', function () {
    $this->travelTo('2026-10-01 02:30:00');
    Video::factory()->for($this->article, 'videoable')->primary()->withoutUploadDate()->create();
    $this->travelBack();

    expect(articleLd($this->get($this->article->url()))['video']['uploadDate'])->toBe('2026-10-01T02:30:00+00:00');
});

it('keeps a single primary video per article', function () {
    $first = Video::factory()->for($this->article, 'videoable')->primary()->create();
    $second = Video::factory()->for($this->article, 'videoable')->primary()->create();

    expect($first->fresh()->is_primary)->toBeFalse()
        ->and($second->fresh()->is_primary)->toBeTrue();
});

it('drops clips that start after the video ends', function () {
    Video::factory()->for($this->article, 'videoable')->primary()->create([
        'duration_seconds' => 120,
        'clips' => [['name' => 'Awal', 'start' => 0, 'end' => null], ['name' => 'Terlalu jauh', 'start' => 500, 'end' => null]],
    ]);

    $clips = articleLd($this->get($this->article->url()))['video']['hasPart'];

    expect($clips)->toHaveCount(1)->and($clips[0]['endOffset'])->toBe(120);
});

it('leaves out key moments for videos shorter than 30 seconds', function () {
    Video::factory()->for($this->article, 'videoable')->primary()->withClips()->create(['duration_seconds' => 25]);

    $response = $this->get($this->article->url())->assertDontSee('data-video-seek', false);

    expect(articleLd($response)['video'])->not->toHaveKey('hasPart');
});

it('credits the YouTube channel as creator only when it is known', function () {
    Video::factory()->for($this->article, 'videoable')->primary()->create([
        'title' => 'Dengan channel', 'creator_name' => 'Supermaterial TV', 'creator_url' => 'https://www.youtube.com/@supermaterial',
    ]);
    Video::factory()->for($this->article, 'videoable')->create(['title' => 'Tanpa channel']);

    [$withChannel, $withoutChannel] = articleLd($this->get($this->article->url()))['video'];

    expect($withChannel['creator'])->toBe(['@type' => 'Organization', 'name' => 'Supermaterial TV', 'url' => 'https://www.youtube.com/@supermaterial'])
        ->and($withoutChannel)->not->toHaveKey('creator');
});

it('gives each video without its own description a unique one', function () {
    Video::factory()->for($this->article, 'videoable')->primary()->create(['title' => 'Video pertama', 'description' => null]);
    Video::factory()->for($this->article, 'videoable')->create(['title' => 'Video kedua', 'description' => null]);

    $descriptions = array_column(articleLd($this->get($this->article->url()))['video'], 'description');

    expect($descriptions[0])->toStartWith('Video pertama. ')
        ->and($descriptions[1])->toStartWith('Video kedua. ')
        ->and($descriptions[0])->not->toBe($descriptions[1]);
});

it('marks up every product video with its own title and upload date, without a YouTube contentUrl', function () {
    $product = Product::where('slug', 'semen-tiga-roda-50-kg')->firstOrFail();
    $product->media()->where('type', 'video')->update(['upload_date' => '2026-08-01 02:00:00', 'duration_seconds' => 95]);
    $product->media()->create(['type' => 'video', 'provider' => 'youtube', 'path' => 'https://youtu.be/zyxWVU98765', 'alt_text' => 'Cara aplikasi kedua', 'sort_order' => 9]);

    $productLd = collect(jsonLdBlocks($this->get($product->url())->assertOk()))
        ->first(fn (array $block) => in_array($block['@type'] ?? null, ['Product', 'ProductGroup'], true));

    expect($productLd['video'])->toHaveCount(2)
        ->and($productLd['video'][0])->toMatchArray(['name' => 'Video Panduan Aplikasi Semen', 'duration' => 'PT1M35S'])
        ->and($productLd['video'][0]['uploadDate'])->toStartWith('2026-08-01')
        ->and($productLd['video'][0])->not->toHaveKey('contentUrl')
        ->and($productLd['video'][1]['name'])->toBe('Cara aplikasi kedua');
});

it('publishes complete videos in the video sitemap', function () {
    Video::factory()->for($this->article, 'videoable')->primary()->create(['title' => 'Video lengkap', 'url' => 'https://youtu.be/abcDEF12345', 'duration_seconds' => 272]);
    $draft = Article::where('slug', '!=', $this->article->slug)->firstOrFail();
    $draft->update(['published_at' => null]);
    Video::factory()->for($draft, 'videoable')->create(['title' => 'Video draf']);

    $this->get('/sitemap.xml')->assertOk()->assertSee('sitemap-videos.xml');

    $this->get('/sitemap-videos.xml')->assertOk()
        ->assertSee('xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"', false)
        ->assertSee('<loc>'.e($this->article->url()).'</loc>', false)
        ->assertSee('<video:title>Video lengkap</video:title>', false)
        ->assertSee('<video:player_loc>https://www.youtube-nocookie.com/embed/abcDEF12345</video:player_loc>', false)
        ->assertSee('<video:duration>272</video:duration>', false)
        ->assertDontSee('Video draf');
});

it('saves article videos from the admin form, converting durations and clips', function () {
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
    $undoRepeaterFake = Repeater::fake();

    Livewire::test(EditArticle::class, ['record' => $this->article->id])
        ->set('data.videos', [[
            'url' => 'https://www.youtube.com/watch?v=abcDEF12345',
            'title' => 'Tutorial semen',
            'description' => null,
            'upload_date' => '2026-09-14 10:00',
            'duration_seconds' => '4:32',
            'thumbnail_path' => null,
            'is_primary' => true,
            'clips' => [['name' => 'Mulai', 'start' => '0:00', 'end' => null], ['name' => 'Aduk', 'start' => '1:30', 'end' => '']],
        ]])
        ->call('save')
        ->assertHasNoFormErrors();

    $undoRepeaterFake();

    $video = $this->article->videos()->sole();
    expect($video)
        ->provider->toBe('youtube')
        ->duration_seconds->toBe(272)
        ->is_primary->toBeTrue()
        ->clips->toBe([['name' => 'Mulai', 'start' => 0, 'end' => null], ['name' => 'Aduk', 'start' => 90, 'end' => null]]);
});

it('rejects an invalid duration in the admin form', function () {
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
    $undoRepeaterFake = Repeater::fake();

    Livewire::test(EditArticle::class, ['record' => $this->article->id])
        ->set('data.videos', [['url' => 'https://youtu.be/abcDEF12345', 'title' => 'Video', 'duration_seconds' => 'lima menit', 'clips' => []]])
        ->call('save')
        ->assertHasFormErrors(['videos.0.duration_seconds']);

    $undoRepeaterFake();
});

it('fetches the video title and channel from YouTube oEmbed without overwriting a filled-in title', function (?string $currentTitle, string $expectedTitle) {
    Http::preventStrayRequests();
    Http::fake(['https://www.youtube.com/oembed*' => Http::response([
        'title' => 'Judul dari YouTube',
        'author_name' => 'Supermaterial TV',
        'author_url' => 'https://www.youtube.com/@supermaterial',
    ])]);

    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
    $undoRepeaterFake = Repeater::fake();

    $component = Livewire::test(EditArticle::class, ['record' => $this->article->id])
        ->set('data.videos', [['url' => 'https://youtu.be/abcDEF12345', 'title' => $currentTitle, 'clips' => []]])
        ->callFormComponentAction('videos.0.url', 'fetchYouTubeMetadata');

    $undoRepeaterFake();

    expect($component->get('data.videos.0'))
        ->title->toBe($expectedTitle)
        ->creator_name->toBe('Supermaterial TV')
        ->creator_url->toBe('https://www.youtube.com/@supermaterial');
})->with([
    'judul kosong' => [null, 'Judul dari YouTube'],
    'judul sudah diisi' => ['Judul saya', 'Judul saya'],
]);
