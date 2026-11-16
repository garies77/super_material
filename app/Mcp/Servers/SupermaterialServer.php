<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Server MCP Supermaterial: pintu bagi asisten AI (mis. Claude) ke katalog, artikel, dan laporan.
 * Memakai token API Sanctum yang sama dengan REST API admin; hak efektif = ability token ∩ peran pengguna.
 */
#[Name('Supermaterial')]
#[Version('0.1.0')]
#[Instructions('Server MCP untuk situs katalog bahan bangunan Supermaterial (supermaterial.id). Gunakan tool yang tersedia untuk membaca katalog produk, artikel, dan laporan sesuai izin token. Jawab dalam bahasa Indonesia. Harga dalam rupiah.')]
class SupermaterialServer extends Server
{
    protected array $tools = [
        //
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
