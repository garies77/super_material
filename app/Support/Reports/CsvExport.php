<?php

namespace App\Support\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Ekspor CSV laporan; BOM UTF-8 agar Excel membaca huruf & simbol dengan benar. */
class CsvExport
{
    /**
     * @param  array<string, string>  $columns  kunci baris => judul kolom
     * @param  iterable<array<string, mixed>>  $rows
     */
    public static function download(string $name, array $columns, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($columns));

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn (string $key) => $row[$key] ?? '', array_keys($columns)));
            }

            fclose($out);
        }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
