<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
