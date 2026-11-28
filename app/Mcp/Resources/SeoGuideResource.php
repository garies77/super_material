<?php

namespace App\Mcp\Resources;

use App\Mcp\Concerns\RequiresAbility;
use App\Support\AiGuides;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Description('Aturan SEO situs: panjang judul dan meta, struktur subjudul, jumlah kata, tautan produk, FAQ, foto, slug, kategori, dan tag. Baca sebelum membuat atau merapikan artikel dan produk. Bisa diubah admin di Pengaturan situs.')]
#[Uri('supermaterial://pedoman/seo')]
#[MimeType('text/markdown')]
class SeoGuideResource extends Resource
{
    use RequiresAbility;

    /** @var list<string> */
    protected array $abilities = ['articles:read', 'catalog:read'];

    public function handle(Request $request): Response
    {
        return Response::text(AiGuides::seo());
    }
}
