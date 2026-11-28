<?php

use App\Filament\Resources\Webhooks\Pages\ManageWebhooks;
use App\Jobs\SendWebhook;
use App\Mcp\Servers\SupermaterialServer;
use App\Mcp\Tools\GetContentOpportunities;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Models\SearchStat;
use App\Models\User;
use App\Models\Webhook;
use App\Support\WebhookEvents;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** @return list<string> event yang diantrekan */
function queuedWebhookEvents(): array
{
    return Queue::pushed(SendWebhook::class)->map(fn (SendWebhook $job) => $job->payload['event'])->values()->all();
}

it('queues product events only for published products and subscribed active webhooks', function () {
    Queue::fake();
    Webhook::factory()->create(['events' => ['product.published', 'product.price_changed']]);
    Webhook::factory()->inactive()->create();
    Webhook::factory()->create(['events' => ['article.published']]);

    $draft = Product::create(['name' => 'Draf', 'slug' => 'draf', 'is_published' => false, 'price_min' => 10000]);
    expect(queuedWebhookEvents())->toBe([]);

    $draft->update(['is_published' => true]);
    expect(queuedWebhookEvents())->toBe(['product.published']);

    $draft->update(['price_min' => 12000]);
    Queue::assertPushed(SendWebhook::class, fn (SendWebhook $job) => $job->payload['event'] === 'product.price_changed'
        && $job->payload['data']['previous']['price_min'] === 10000 && $job->payload['data']['price_min'] === 12000);

    $draft->update(['badge' => 'Baru']);
    expect(queuedWebhookEvents())->toHaveCount(2);
});

it('announces published articles once, and scheduled articles when their time comes', function () {
    Queue::fake();
    Webhook::factory()->create(['events' => ['article.published']]);

    $now = Article::create(['title' => 'Langsung', 'slug' => 'langsung', 'content' => '<p>x</p>', 'published_at' => now()->subMinute()]);
    $scheduled = Article::create(['title' => 'Terjadwal', 'slug' => 'terjadwal', 'content' => '<p>x</p>', 'published_at' => now()->addHour()]);
    Article::create(['title' => 'Draf', 'slug' => 'draf', 'content' => '<p>x</p>']);

    expect(queuedWebhookEvents())->toBe(['article.published'])
        ->and($now->fresh()->announced_at)->not->toBeNull();

    $now->update(['title' => 'Langsung (revisi)']);
    $this->artisan('webhooks:announce-scheduled')->assertSuccessful();
    expect(queuedWebhookEvents())->toHaveCount(1);

    $this->travel(2)->hours();
    $this->artisan('webhooks:announce-scheduled')->expectsOutput('1 artikel diumumkan.')->assertSuccessful();
    $this->artisan('webhooks:announce-scheduled')->expectsOutput('0 artikel diumumkan.');

    Queue::assertPushed(SendWebhook::class, fn (SendWebhook $job) => $job->payload['data']['slug'] === 'terjadwal');
    expect(queuedWebhookEvents())->toHaveCount(2)
        ->and($scheduled->fresh()->announced_at)->not->toBeNull();
});

it('signs the payload with HMAC and records the delivery result', function () {
    Http::fake([
        'https://ok.example.com/*' => Http::response('ok'),
        'https://down.example.com/*' => Http::response('rusak', 500),
    ]);
    $webhook = Webhook::factory()->create(['url' => 'https://ok.example.com/hook', 'secret' => 'whsec_uji']);
    $payload = WebhookEvents::payload('product.published', ['slug' => 'palu']);

    (new SendWebhook($webhook->id, $payload))->handle();

    Http::assertSent(function (HttpRequest $request) {
        $expected = 'sha256='.hash_hmac('sha256', $request->header('X-Supermaterial-Timestamp')[0].'.'.$request->body(), 'whsec_uji');

        return $request->header('X-Supermaterial-Signature')[0] === $expected
            && $request->header('X-Supermaterial-Event')[0] === 'product.published'
            && $request['data']['slug'] === 'palu';
    });
    expect($webhook->fresh()->last_status)->toBe(200)->and($webhook->fresh()->last_error)->toBeNull();

    $failing = Webhook::factory()->create(['url' => 'https://down.example.com/hook']);
    expect(fn () => (new SendWebhook($failing->id, $payload))->handle())->toThrow(RuntimeException::class);
    expect($failing->fresh()->last_status)->toBe(500)->and($failing->fresh()->last_error)->toContain('rusak');
});

it('lets only admins manage webhooks and send a test from the panel', function () {
    Http::fake(['*' => Http::response('ok')]);

    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]));
    $this->get(ManageWebhooks::getUrl())->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    Livewire::test(ManageWebhooks::class)
        ->callAction('create', ['name' => 'n8n', 'url' => 'https://n8n.example.com/webhook/abc', 'events' => ['article.published'], 'is_active' => true])
        ->assertHasNoActionErrors();

    $webhook = Webhook::sole();
    expect($webhook->secret)->toStartWith('whsec_')->and($webhook->events)->toBe(['article.published']);

    Livewire::test(ManageWebhooks::class)->callAction(TestAction::make('sendTest')->table($webhook));
    Http::assertSent(fn (HttpRequest $request) => $request->header('X-Supermaterial-Event')[0] === Webhook::PING_EVENT);
    expect($webhook->fresh()->last_status)->toBe(200);
});

it('ranks content ideas from searches without results and categories without articles', function () {
    SearchStat::create(['date' => now()->subDay()->toDateString(), 'term' => 'cat waterproof', 'searches' => 7, 'results' => 0]);
    $category = Category::create(['name' => 'Cetok', 'slug' => 'cetok', 'is_active' => true]);
    foreach (['a', 'b', 'c'] as $suffix) {
        Product::create(['name' => "Cetok {$suffix}", 'slug' => "cetok-{$suffix}", 'category_id' => $category->id, 'is_published' => true]);
    }
    $user = User::factory()->create(['role' => 'admin']);
    $user->withAccessToken($user->createToken('claude', ['reports:read'], now()->addDays(90))->accessToken);

    SupermaterialServer::actingAs($user)->tool(GetContentOpportunities::class, [])
        ->assertOk()
        ->assertStructuredContentPath('opportunities.0.type', 'search_no_result')
        ->assertStructuredContentPath('opportunities.0.data.term', 'cat waterproof')
        ->assertStructuredContentPath('opportunities.1.type', 'category_without_article')
        ->assertStructuredContentPath('opportunities.1.data.slug', 'cetok');
});
