<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\V1\Admin\ImportController;
use App\Jobs\ProcessProductImport;
use App\Models\AuditLog;
use App\Models\ProductImport;
use App\Support\RemoteFile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Menjalankan impor produk massal dari berkas CSV atau Excel (.xlsx, lembar pertama) di URL https publik, mis. tautan unduh Google Sheets (File > Download > CSV). Kolom: nama, slug, kategori, merek, harga_min, harga_max, satuan, badge, shopee_url, tokopedia_url, lazada_url, tiktok_url, whatsapp_message, terbit, gambar. Produk dengan slug yang sudah ada DIPERBARUI. Diproses di latar belakang; pantau dengan get-import-status. Jelaskan dampaknya ke pengguna dan minta persetujuan sebelum menjalankan.')]
#[IsDestructive]
class StartImport extends AbilityTool
{
    protected string $ability = 'import:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $input = $this->validated($request, ['url' => ['required', 'string', 'max:2000']]);

        $file = RemoteFile::download($input['url'], (int) config('api.uploads.import_max_kb'));

        try {
            Validator::make(['url' => $file], ['url' => ['file', 'mimes:csv,txt,xlsx']], [
                'url.mimes' => 'Berkas impor harus CSV atau Excel (.xlsx).',
            ])->validate();

            $extension = $file->guessExtension() === 'xlsx' ? 'xlsx' : 'csv';
            $path = $file->storeAs('imports', 'mcp-'.Str::uuid().'.'.$extension, 'local');
        } finally {
            @unlink($file->getPathname());
        }

        $import = ProductImport::create([
            'user_id' => $request->user()->id,
            'token_id' => $request->user()->currentAccessToken()?->id,
            'file_path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'status' => 'queued',
        ]);

        AuditLog::record(request(), 'import.queued', $import, ['file' => $import->original_name], actor: $request->user(), source: 'mcp');
        ProcessProductImport::dispatch($import->id);

        return Response::structured(ImportController::summary($import->refresh()));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->required()->description('URL https berkas CSV atau .xlsx.'),
        ];
    }
}
