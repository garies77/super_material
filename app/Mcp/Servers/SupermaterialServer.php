<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetArticle;
use App\Mcp\Tools\GetClickReport;
use App\Mcp\Tools\GetProduct;
use App\Mcp\Tools\GetProductReport;
use App\Mcp\Tools\GetReportSummary;
use App\Mcp\Tools\GetTrafficReport;
use App\Mcp\Tools\ListBrands;
use App\Mcp\Tools\ListCategories;
use App\Mcp\Tools\SearchArticles;
use App\Mcp\Tools\SearchProducts;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Server MCP Supermaterial: pintu bagi asisten AI (mis. Claude) ke katalog, artikel, dan laporan.
 * Memakai token API Sanctum yang sama dengan REST API admin; hak efektif = ability token ∩ peran pengguna.
 */
#[Name('Supermaterial')]
#[Version('0.2.0')]
#[Instructions('Server MCP untuk situs katalog bahan bangunan Supermaterial (supermaterial.id). Semua tool hanya membaca data. Tool yang muncul mengikuti izin token: katalog (search-products, get-product, list-categories, list-brands), artikel (search-articles, get-article), dan laporan (get-report-summary, get-product-report, get-traffic-report, get-click-report). Cari dulu, lalu ambil detail dengan slug. Tanggal laporan memakai zona WIB (Asia/Jakarta), bawaan 30 hari terakhir. Harga dalam rupiah. Jawab dalam bahasa Indonesia.')]
class SupermaterialServer extends Server
{
    /** Hanya tool yang ability-nya dimiliki token yang terdaftar (lihat AbilityTool). */
    protected array $tools = [
        SearchProducts::class,
        GetProduct::class,
        ListCategories::class,
        ListBrands::class,
        SearchArticles::class,
        GetArticle::class,
        GetReportSummary::class,
        GetProductReport::class,
        GetTrafficReport::class,
        GetClickReport::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
