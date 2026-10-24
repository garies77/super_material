<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\ProductImport;
use App\Support\Catalog\ProductImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Impor CSV/Excel diproses lewat antrean (PRD 8.12.6). */
class ProcessProductImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $importId) {}

    public function handle(): void
    {
        $import = ProductImport::find($this->importId);
        if (! $import || $import->status !== 'queued') {
            return;
        }

        $import->update(['status' => 'processing', 'started_at' => now()]);
        $path = Storage::disk('local')->path($import->file_path);

        try {
            $result = ProductImporter::import($path, pathinfo($import->file_path, PATHINFO_EXTENSION));

            $import->update([
                'status' => 'completed',
                'total_rows' => $result['total'],
                'created_count' => $result['created'],
                'updated_count' => $result['updated'],
                'failed_count' => count($result['failed']),
                'failed_rows' => $result['failed'],
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $import->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
            report($e);
        } finally {
            Storage::disk('local')->delete($import->file_path);
        }

        AuditLog::create([
            'user_id' => $import->user_id,
            'token_id' => $import->token_id,
            'source' => 'api',
            'action' => 'import.'.$import->status,
            'subject_type' => $import->getMorphClass(),
            'subject_id' => $import->id,
            'changes' => ['created' => $import->created_count, 'updated' => $import->updated_count, 'failed' => $import->failed_count],
            'created_at' => now(),
        ]);
    }
}
