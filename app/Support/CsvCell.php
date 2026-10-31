<?php

namespace App\Support;

/**
 * Sel CSV yang aman dibuka di Excel/Google Sheets (cegah CSV/formula injection): teks yang diawali
 * = + - @ (juga setelah spasi) atau tab/CR diberi awalan ' agar dibaca sebagai teks, bukan formula.
 * Angka (int/float) dibiarkan apa adanya.
 */
class CsvCell
{
    /**
     * @param  array<int, mixed>  $cells
     * @return array<int, mixed>
     */
    public static function row(array $cells): array
    {
        return array_map(self::safe(...), $cells);
    }

    public static function safe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^(\s*[=+\-@]|[\t\r])/', $value) ? "'".$value : $value;
    }
}
