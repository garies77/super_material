<?php

use App\Http\Middleware\LogApiRequest;
use App\Mcp\Servers\SupermaterialServer;
use Laravel\Mcp\Facades\Mcp;

/*
| Server MCP (Model Context Protocol) untuk asisten AI seperti Claude. Dimuat otomatis oleh laravel/mcp.
| Autentikasi: token API Sanctum di header Authorization: Bearer (dibuat di panel admin, menu Token API).
| Batas laju dan pencatatan sama dengan REST API admin.
*/

Mcp::web('/mcp', SupermaterialServer::class)
    ->middleware([LogApiRequest::class, 'auth:sanctum', 'throttle:api-admin'])
    ->name('mcp');
