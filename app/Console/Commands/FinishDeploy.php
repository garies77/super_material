<?php

namespace App\Console\Commands;

use App\Support\DatabaseBackup;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Penyelesai deploy untuk hosting tanpa SSH. GitHub Actions meng-upload kode lewat FTP lalu file RELEASE
 * (berisi kode versi); perintah ini dijalankan cron (schedule:run tiap menit) dan, bila versi berubah,
 * mencadangkan database (bila ada migrasi baru), menjalankan migrasi, menyegarkan cache, lalu membuka situs
 * dari mode pemeliharaan.
 *
 * Hasil ditulis ke storage/app/deploy/status.json (dibaca GitHub Actions lewat FTP, tidak publik)
 * dan storage/logs/deploy.log. Versi yang gagal tidak diulang terus; situs tetap dalam pemeliharaan
 * agar data tidak rusak, sampai versi baru (atau rollback) di-deploy.
 */
class FinishDeploy extends Command
{
    protected $signature = 'deploy:finish {--force : Jalankan meski versi ini sudah pernah diproses}';

    protected $description = 'Selesaikan deploy dari GitHub Actions: migrasi, cache, dan buka mode pemeliharaan';

    /**
     * Pengganti Artisan::call untuk tes, agar optimize/migrate sungguhan tidak menulis cache di mesin pengembang.
     *
     * @var (Closure(string, array<string, mixed>): array{0: int, 1: string})|null
     */
    public static ?Closure $runner = null;

    /** Status "running" lebih lama dari ini dianggap terhenti dan diproses ulang (sama dengan batas withoutOverlapping). */
    public const STALE_MINUTES = 15;

    public function handle(): int
    {
        // Detak cron: GitHub Actions memeriksa berkas ini sebelum memasang mode pemeliharaan
        File::ensureDirectoryExists(dirname(self::heartbeatPath()));
        File::put(self::heartbeatPath(), now()->toIso8601String());

        $release = self::release();

        if ($release === null) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && self::alreadyHandled($release)) {
            return self::SUCCESS;
        }

        $this->record($release, 'running', 'Menyelesaikan deploy');
        $backup = null;

        try {
            // Cache lama (config/route/view) bisa menunjuk ke kode yang sudah berubah: bersihkan dulu
            $this->step('optimize:clear');

            // Cadangan database sebelum skema berubah; gagal mencadangkan = migrasi tidak dijalankan
            if ($pending = self::pendingMigrations()) {
                $backup = DatabaseBackup::create($release);
                $this->log('Cadangan database sebelum '.count($pending).' migrasi: '.$backup);
            }

            $this->step('migrate', ['--force' => true]);

            if (! file_exists(public_path('storage'))) {
                $this->linkStorage();
            }

            $this->step('optimize');
            $this->step('up');
        } catch (Throwable $e) {
            $this->record($release, 'failed', $e->getMessage());
            $this->error('Deploy '.$release.' gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->record($release, 'done', 'Deploy selesai'.($backup ? '; cadangan database: '.basename($backup) : ''));
        $this->info("Deploy {$release} selesai.");

        return self::SUCCESS;
    }

    /**
     * Versi ini sudah selesai/gagal diproses, atau sedang diproses proses lain. Status "running" yang lebih lama
     * dari STALE_MINUTES berarti prosesnya terhenti (mis. dimatikan hosting), jadi dicoba lagi.
     */
    private static function alreadyHandled(string $release): bool
    {
        $status = self::status();

        if (($status['release'] ?? null) !== $release) {
            return false;
        }

        return ($status['status'] ?? null) !== 'running'
            || now()->subMinutes(self::STALE_MINUTES)->lt($status['at'] ?? now());
    }

    /** @return list<string> nama migrasi yang belum dijalankan */
    private static function pendingMigrations(): array
    {
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];

        return array_values(array_diff(array_keys($files), $ran));
    }

    /** Kode versi dari file RELEASE di root proyek (hanya ada di server hasil deploy). */
    public static function release(): ?string
    {
        $path = base_path('RELEASE');

        return file_exists($path) ? (trim((string) file_get_contents($path)) ?: null) : null;
    }

    /** @return array{release?: string, status?: string, message?: string, at?: string} */
    public static function status(): array
    {
        $path = self::statusPath();

        return file_exists($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    }

    public static function statusPath(): string
    {
        return storage_path('app/deploy/status.json');
    }

    public static function heartbeatPath(): string
    {
        return storage_path('app/deploy/heartbeat');
    }

    /** @param  array<string, mixed>  $parameters */
    private function step(string $command, array $parameters = []): void
    {
        [$exitCode, $output] = self::$runner
            ? (self::$runner)($command, $parameters)
            : [Artisan::call($command, $parameters), Artisan::output()];
        $this->log("{$command}: ".trim($output));

        if ($exitCode !== 0) {
            throw new \RuntimeException("Perintah {$command} gagal (kode {$exitCode}). Lihat storage/logs/deploy.log.");
        }
    }

    /** Beberapa shared hosting melarang symlink: gagal di sini tidak menghentikan deploy, cukup dicatat. */
    private function linkStorage(): void
    {
        try {
            $this->step('storage:link');
        } catch (Throwable $e) {
            $this->log('storage:link dilewati: '.$e->getMessage());
        }
    }

    private function record(string $release, string $status, string $message): void
    {
        File::ensureDirectoryExists(dirname(self::statusPath()));
        File::put(self::statusPath(), json_encode([
            'release' => $release,
            'status' => $status,
            'message' => $message,
            'at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->log("[{$release}] {$status}: {$message}");
    }

    private function log(string $line): void
    {
        File::ensureDirectoryExists(storage_path('logs'));
        File::append(storage_path('logs/deploy.log'), '['.now()->toDateTimeString().'] '.$line.PHP_EOL);
    }
}
