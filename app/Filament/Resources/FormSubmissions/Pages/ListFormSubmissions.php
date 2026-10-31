<?php

namespace App\Filament\Resources\FormSubmissions\Pages;

use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Models\AuditLog;
use App\Models\FormSubmission;
use App\Support\CsvCell;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListFormSubmissions extends ListRecords
{
    protected static string $resource = FormSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')->label('Ekspor CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->tooltip('Mengikuti filter dan pencarian yang sedang aktif. Bisa dibuka di Excel dan diimpor ke Brevo, Mailchimp, dll.')
                ->action(fn () => $this->exportCsv()),
        ];
    }

    /**
     * CSV dari hasil filter tabel saat ini: kolom kontak dulu (siap untuk email marketing), lalu semua jawaban.
     * BOM UTF-8 agar Excel membaca huruf non-ASCII dengan benar.
     */
    public function exportCsv(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery()->with('form');
        AuditLog::record(request(), 'form_submissions.export', null, ['rows' => (clone $query)->count()]);

        $labels = $query->clone()->get()->flatMap(fn (FormSubmission $s) => array_keys($s->answers()))->unique()->values()->all();

        return response()->streamDownload(function () use ($query, $labels) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Tanggal (WIB)', 'Formulir', 'Nama', 'Email', 'Telepon', 'Status', 'Dikirim dari', ...$labels]);
            foreach ($query->lazy() as $submission) {
                $answers = $submission->answers();
                // Semua isian berasal dari pengunjung: cegah formula injection saat dibuka di Excel
                fputcsv($out, CsvCell::row([
                    $submission->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i'),
                    $submission->form?->name,
                    $submission->name,
                    $submission->email,
                    $submission->phone,
                    FormSubmission::STATUSES[$submission->status] ?? $submission->status,
                    $submission->source_url,
                    ...array_map(fn ($label) => (string) ($answers[$label] ?? ''), $labels),
                ]));
            }
            fclose($out);
        }, 'jawaban-formulir-'.now('Asia/Jakarta')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
