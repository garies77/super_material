<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Cadangan database dalam PHP murni (tanpa mysqldump/exec, yang sering dimatikan di shared hosting).
 * Hasil: berkas .sql.gz di storage/app/backups yang bisa diimpor lewat phpMyAdmin hPanel.
 * Dipakai deploy:finish sebelum menjalankan migrasi.
 */
class DatabaseBackup
{
    public const KEEP = 5;

    /** Tabel sementara: hanya strukturnya yang dicadangkan, isinya tidak perlu dipulihkan. */
    private const STRUCTURE_ONLY = ['cache', 'cache_locks', 'sessions', 'jobs', 'failed_jobs'];

    private const ROWS_PER_INSERT = 200;

    public static function directory(): string
    {
        return storage_path('app/backups');
    }

    /** Buat cadangan lengkap; mengembalikan path berkas. Melempar pengecualian bila gagal. */
    public static function create(string $label): string
    {
        File::ensureDirectoryExists(self::directory());
        $path = self::directory().'/db-'.now()->format('Ymd-His').'-'.preg_replace('/[^A-Za-z0-9_-]/', '', $label).'.sql.gz';

        $gz = gzopen($path, 'wb6');
        if ($gz === false) {
            throw new RuntimeException("Tidak bisa menulis cadangan ke {$path}");
        }

        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            $grammar = $connection->getQueryGrammar();

            gzwrite($gz, '-- Cadangan '.config('app.name').' '.now()->toIso8601String()." ({$driver})\n");
            // Relasi antartabel dimatikan selama impor agar urutan tabel tidak menjadi masalah
            gzwrite($gz, $driver === 'sqlite' ? "PRAGMA foreign_keys=OFF;\n" : "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");

            foreach (Schema::getTableListing(schemaQualified: false) as $table) {
                $wrapped = $grammar->wrapTable($table);
                gzwrite($gz, "\n-- Tabel {$table}\nDROP TABLE IF EXISTS {$wrapped};\n".self::createStatement($table, $driver).";\n");

                if (! in_array($table, self::STRUCTURE_ONLY, true)) {
                    self::writeRows($gz, $table, $wrapped);
                }
            }

            gzwrite($gz, $driver === 'sqlite' ? "\nPRAGMA foreign_keys=ON;\n" : "\nSET FOREIGN_KEY_CHECKS=1;\n");
        } catch (\Throwable $e) {
            gzclose($gz);
            File::delete($path);

            throw $e;
        }

        gzclose($gz);
        self::prune();

        return $path;
    }

    private static function createStatement(string $table, string $driver): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => (string) ((array) DB::selectOne('SHOW CREATE TABLE '.DB::getQueryGrammar()->wrapTable($table)))['Create Table'],
            'sqlite' => (string) DB::table('sqlite_master')->where('type', 'table')->where('name', $table)->value('sql'),
            default => throw new RuntimeException("Driver database {$driver} belum didukung untuk cadangan otomatis"),
        };
    }

    /** @param  resource  $gz */
    private static function writeRows($gz, string $table, string $wrapped): void
    {
        $columns = Schema::getColumnListing($table);
        if ($columns === []) {
            return;
        }

        $query = DB::table($table);
        // lazyById membaca bertahap (hemat memori untuk tabel besar seperti click_events); tabel tanpa id dibaca biasa
        $rows = in_array('id', $columns, true) ? $query->lazyById(1000) : $query->cursor();
        $columnList = implode(', ', array_map(fn (string $column) => DB::getQueryGrammar()->wrap($column), $columns));
        $pdo = DB::connection()->getPdo();
        $batch = [];

        foreach ($rows as $row) {
            $values = array_map(fn ($value) => match (true) {
                $value === null => 'NULL',
                is_bool($value) => $value ? '1' : '0',
                is_int($value), is_float($value) => (string) $value,
                default => $pdo->quote((string) $value),
            }, array_map(fn (string $column) => $row->{$column} ?? null, $columns));
            $batch[] = '('.implode(', ', $values).')';

            if (count($batch) === self::ROWS_PER_INSERT) {
                gzwrite($gz, "INSERT INTO {$wrapped} ({$columnList}) VALUES\n".implode(",\n", $batch).";\n");
                $batch = [];
            }
        }

        if ($batch !== []) {
            gzwrite($gz, "INSERT INTO {$wrapped} ({$columnList}) VALUES\n".implode(",\n", $batch).";\n");
        }
    }

    /** Simpan hanya beberapa cadangan terbaru agar kuota hosting tidak penuh. */
    private static function prune(): void
    {
        collect(File::glob(self::directory().'/db-*.sql.gz'))
            ->sortDesc()
            ->slice(self::KEEP)
            ->each(fn (string $old) => File::delete($old));
    }
}
