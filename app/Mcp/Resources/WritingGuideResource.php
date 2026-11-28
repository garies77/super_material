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

#[Description('Pedoman gaya tulis situs: pembaca, nada bahasa, istilah, dan hal yang harus dihindari. Baca sebelum menulis artikel atau deskripsi produk. Bisa diubah admin di Pengaturan situs.')]
#[Uri('supermaterial://pedoman/gaya-tulis')]
#[MimeType('text/markdown')]
class WritingGuideResource extends Resource
{
    use RequiresAbility;

    /** @var list<string> */
    protected array $abilities = ['articles:read', 'catalog:read'];

    public function handle(Request $request): Response
    {
        return Response::text(AiGuides::writing());
    }
}
