<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\ApiResource;
use App\Jobs\ProcessProductImport;
use App\Models\AuditLog;
use App\Models\ProductImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** @tags Admin: Impor */
class ImportController extends ApiController
{
    protected bool $admin = true;

    /**
     * Mengunggah berkas impor
     *
     * Ability: import:write. Berkas CSV atau Excel (.xlsx, lembar pertama) dengan kolom: nama, slug, kategori,
     * merek, harga_min, harga_max, satuan, badge, shopee_url, tokopedia_url, lazada_url, tiktok_url,
     * whatsapp_message, terbit. Diproses lewat antrean; pantau status lewat GET /admin/imports/{id}.
     * Mendukung header Idempotency-Key.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:'.config('api.uploads.import_max_kb')],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        $path = $file->storeAs('imports', 'api-'.Str::uuid().'.'.$extension, 'local');

        $import = ProductImport::create([
            'user_id' => $request->user()->id,
            'token_id' => $request->user()->currentAccessToken()?->id,
            'file_path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'status' => 'queued',
        ]);

        AuditLog::record($request, 'import.queued', $import, ['file' => $import->original_name]);
        ProcessProductImport::dispatch($import->id);

        return response()->json(['data' => $this->present($import->refresh())], 201)
            ->header('Location', route('api.v1.admin.imports.show', $import));
    }

    /**
     * Status impor
     *
     * Ability: import:write. Status (queued, processing, completed, failed), jumlah baris, dan daftar baris yang gagal beserta alasannya.
     */
    public function show(Request $request, ProductImport $import): JsonResponse
    {
        abort_unless($import->user_id === $request->user()->id || $request->user()->isAdmin(), 404);

        return response()->json(['data' => $this->present($import)]);
    }

    /** @return array<string, mixed> */
    private function present(ProductImport $import): array
    {
        return [
            'id' => $import->id,
            'status' => $import->status,
            'file_name' => $import->original_name,
            'total_rows' => $import->total_rows,
            'created_count' => $import->created_count,
            'updated_count' => $import->updated_count,
            'failed_count' => $import->failed_count,
            'failed_rows' => $import->failed_rows ?? [],
            'error' => $import->error,
            'started_at' => ApiResource::iso($import->started_at),
            'finished_at' => ApiResource::iso($import->finished_at),
            'created_at' => ApiResource::iso($import->created_at),
        ];
    }
}
