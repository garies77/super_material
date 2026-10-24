<?php

namespace App\Support\Catalog;

use Generator;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Pembaca baris CSV dan Excel (.xlsx, lembar pertama) tanpa dependensi tambahan.
 * Menghasilkan baris berupa array asosiatif dengan kunci header huruf kecil.
 */
class SpreadsheetReader
{
    /** @return Generator<int, array<string, string>> nomor baris (header = 1) → data */
    public static function rows(string $path, ?string $extension = null): Generator
    {
        $extension = strtolower($extension ?: pathinfo($path, PATHINFO_EXTENSION));

        $raw = $extension === 'xlsx' ? self::xlsx($path) : self::csv($path);
        $header = null;

        foreach ($raw as $line => $cells) {
            if ($header === null) {
                $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $cells);

                continue;
            }

            if (! array_filter($cells, fn ($c) => trim((string) $c) !== '')) {
                continue;
            }

            $row = [];
            foreach ($header as $i => $key) {
                if ($key !== '') {
                    $row[$key] = trim((string) ($cells[$i] ?? ''));
                }
            }

            yield $line => $row;
        }
    }

    /** @return Generator<int, array<int, string>> */
    private static function csv(string $path): Generator
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('Berkas tidak dapat dibuka.');
        }

        // Deteksi pemisah ; (Excel lokal Indonesia) atau ,
        $first = (string) fgets($handle);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($handle);

        $line = 0;
        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $line++;
            yield $line => array_map(fn ($c) => (string) $c, $cells);
        }

        fclose($handle);
    }

    /** @return Generator<int, array<int, string>> */
    private static function xlsx(string $path): Generator
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Berkas Excel tidak valid.');
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach (self::xml($xml)->si as $si) {
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r, false)));
            }
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) {
            throw new RuntimeException('Lembar pertama Excel tidak ditemukan.');
        }

        foreach (self::xml($sheet)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $index = self::columnIndex((string) $c['r']);
                $type = (string) $c['t'];
                $value = match ($type) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    default => (string) $c->v,
                };
                $cells[$index] = $value;
            }

            if ($cells) {
                $max = max(array_keys($cells));
                $cells = array_replace(array_fill(0, $max + 1, ''), $cells);
            }

            yield (int) $row['r'] => $cells;
        }
    }

    private static function xml(string $xml): SimpleXMLElement
    {
        $element = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        if ($element === false) {
            throw new RuntimeException('Isi berkas Excel tidak dapat dibaca.');
        }

        return $element;
    }

    private static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/\d+/', '', strtoupper($reference));
        $index = 0;
        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return max(0, $index - 1);
    }
}
