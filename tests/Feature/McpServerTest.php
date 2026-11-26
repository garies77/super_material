<?php

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/** @return array{jsonrpc: string, id: int, method: string, params: array<string, mixed>} */
function mcpInitializeMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'pest', 'version' => '1.0'],
        ],
    ];
}

it('lets a valid API token complete the MCP handshake', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $token = $user->createToken('claude', ['catalog:read'], now()->addDays(90))->plainTextToken;

    $this->withToken($token)->postJson('/mcp', mcpInitializeMessage())
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Supermaterial')
        ->assertJsonPath('result.protocolVersion', '2025-06-18');
});

it('rejects MCP requests without a valid token', function () {
    $this->postJson('/mcp', mcpInitializeMessage())
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", error="invalid_token"');

    $this->withToken('1|salah')->postJson('/mcp', mcpInitializeMessage())->assertUnauthorized();

    // Tanpa header Accept JSON tetap 401, bukan redirect ke rute login yang tidak ada (500)
    $this->call('POST', '/mcp', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(mcpInitializeMessage()))
        ->assertUnauthorized();
});

it('rejects expired tokens and tokens of deactivated users', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $expired = $user->createToken('lama', ['catalog:read'], now()->subMinute())->plainTextToken;

    $inactive = User::factory()->create(['role' => 'admin', 'is_active' => false]);
    $inactiveToken = $inactive->createToken('claude', ['catalog:read'], now()->addDays(90))->plainTextToken;

    $this->withToken($expired)->postJson('/mcp', mcpInitializeMessage())->assertUnauthorized();
    $this->withToken($inactiveToken)->postJson('/mcp', mcpInitializeMessage())->assertUnauthorized();
});

it('lists over HTTP only the tools the token may use', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $token = $user->createToken('claude', ['catalog:read'], now()->addDays(90))->plainTextToken;

    $tools = $this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []])
        ->assertOk()
        ->json('result.tools.*.name');

    expect($tools)->toContain('search-products', 'get-product')
        ->not->toContain('get-report-summary', 'search-articles');
});

it('scopes tool calls over HTTP to the token owner', function () {
    $author = User::factory()->create(['role' => 'author']);
    $token = $author->createToken('claude', ['articles:read'], now()->addDays(90))->plainTextToken;
    Article::create(['title' => 'Artikel orang lain', 'slug' => 'artikel-orang-lain', 'content' => '<p>Isi</p>']);
    Article::create(['title' => 'Artikel saya', 'slug' => 'artikel-saya', 'content' => '<p>Isi</p>', 'author_id' => $author->authorProfile()->id]);

    $this->withToken($token)->postJson('/mcp', [
        'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
        'params' => ['name' => 'search-articles', 'arguments' => ['status' => '']],
    ])
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.total', 1)
        ->assertJsonPath('result.structuredContent.articles.0.slug', 'artikel-saya');
});

it('logs the MCP method and tool name for monitoring', function () {
    $path = storage_path('logs/api-mcp-test.log');
    File::delete($path);
    config(['logging.channels.api' => ['driver' => 'single', 'path' => $path, 'level' => 'info']]);
    $user = User::factory()->create(['role' => 'admin']);
    $token = $user->createToken('claude', ['catalog:read'], now()->addDays(90))->plainTextToken;

    $this->withToken($token)->postJson('/mcp', [
        'jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call',
        'params' => ['name' => 'list-brands', 'arguments' => []],
    ])->assertOk();

    expect(File::get($path))->toContain('"mcp_method":"tools/call"')->toContain('"mcp_tool":"list-brands"');
    File::delete($path);
});

it('keeps working from the route cache that deploy:finish builds in production', function () {
    $router = app('router');
    $routes = $router->getRoutes();
    foreach ($routes as $route) {
        $route->prepareForSerialization();
    }
    $router->setCompiledRoutes($routes->compile());

    $user = User::factory()->create(['role' => 'admin']);
    $token = $user->createToken('claude', ['catalog:read'], now()->addDays(90))->plainTextToken;

    // Tanpa token dulu: header dari withToken() terbawa ke permintaan berikutnya dalam tes yang sama
    $this->postJson('/mcp', mcpInitializeMessage())->assertUnauthorized();
    $this->withToken($token)->postJson('/mcp', mcpInitializeMessage())
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Supermaterial');
});
