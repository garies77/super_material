<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\V1\Admin\ImportController;
use App\Models\ProductImport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Status impor dari start-import: queued, processing, completed, atau failed, jumlah produk dibuat/diperbarui/gagal, dan daftar baris yang gagal beserta alasannya.')]
#[IsReadOnly]
class GetImportStatus extends AbilityTool
{
    protected string $ability = 'import:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $input = $this->validated($request, ['id' => ['required', 'integer']]);
        $import = ProductImport::find($input['id']);
        $user = $request->user();

        if (! $import || ($import->user_id !== $user->id && ! $user->isAdmin())) {
            return Response::error('Impor tidak ditemukan.');
        }

        return Response::structured(ImportController::summary($import));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('ID impor dari hasil start-import.'),
        ];
    }
}
