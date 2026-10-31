<?php

use App\Console\Commands\FinishDeploy;
use App\Models\Product;
use App\Support\DatabaseBackup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Status, log, dan cadangan ditulis ke folder sementara, bukan storage/ proyek
    $this->storage = sys_get_temp_dir().'/sm-deploy-'.uniqid();
    File::ensureDirectoryExists($this->storage);
    $this->app->useStoragePath($this->storage);

    $this->releaseFile = base_path('RELEASE');
    File::delete($this->releaseFile);

    // Catat perintah artisan tanpa benar-benar menjalankannya (optimize akan menulis cache di mesin ini)
    $this->ran = collect();
    $this->failOn = null;
    FinishDeploy::$runner = function (string $command) {
        $this->ran->push($command);

        return $command === $this->failOn ? [1, 'SQLSTATE: kolom sudah ada'] : [0, 'OK'];
    };
});

afterEach(function () {
    FinishDeploy::$runner = null;
    File::delete($this->releaseFile);
    File::deleteDirectory($this->storage);
});

function commandsRun(): array
{
    return test()->ran->reject(fn (string $command) => $command === 'storage:link')->values()->all();
}

it('only writes the cron heartbeat on machines without a RELEASE file', function () {
    $this->freezeTime();
    $this->artisan('deploy:finish')->assertSuccessful();

    expect($this->ran)->toBeEmpty()
        ->and(FinishDeploy::status())->toBe([])
        ->and(File::get(FinishDeploy::heartbeatPath()))->toBe(now()->toIso8601String());
});

it('migrates, refreshes caches and lifts maintenance mode once per release', function () {
    Artisan::call('migrate');
    File::put($this->releaseFile, "abc1234\n");

    $this->artisan('deploy:finish')->assertSuccessful();
    $this->artisan('deploy:finish')->assertSuccessful();

    expect(commandsRun())->toBe(['optimize:clear', 'migrate', 'optimize', 'up', 'media:sync'])
        ->and(FinishDeploy::status())->toMatchArray(['release' => 'abc1234', 'status' => 'done'])
        ->and(File::glob(DatabaseBackup::directory().'/*.sql.gz'))->toBeEmpty();
});

it('keeps the deploy successful when syncing the media library fails', function () {
    Artisan::call('migrate');
    File::put($this->releaseFile, 'med4567');
    $this->failOn = 'media:sync';

    $this->artisan('deploy:finish')->assertSuccessful();

    expect(commandsRun())->toBe(['optimize:clear', 'migrate', 'optimize', 'up', 'media:sync'])
        ->and(FinishDeploy::status())->toMatchArray(['release' => 'med4567', 'status' => 'done'])
        ->and(File::get(storage_path('logs/deploy.log')))->toContain('media:sync gagal (deploy tetap selesai)');
});

it('backs up the database before running new migrations', function () {
    File::put($this->releaseFile, 'mig0001');

    $this->artisan('deploy:finish')->assertSuccessful();

    expect(File::glob(DatabaseBackup::directory().'/db-*-mig0001.sql.gz'))->toHaveCount(1)
        ->and(FinishDeploy::status()['message'])->toContain('cadangan database: db-');
});

it('records a failed release, keeps maintenance on and does not retry it every minute', function () {
    Artisan::call('migrate');
    File::put($this->releaseFile, 'def5678');
    $this->failOn = 'migrate';

    $this->artisan('deploy:finish')->assertFailed();
    $this->artisan('deploy:finish')->assertSuccessful();

    expect(commandsRun())->toBe(['optimize:clear', 'migrate'])
        ->and(FinishDeploy::status())->toMatchArray(['release' => 'def5678', 'status' => 'failed'])
        ->and(FinishDeploy::status()['message'])->toContain('migrate gagal');

    // Versi berikutnya (perbaikan atau rollback) diproses lagi
    File::put($this->releaseFile, 'fix9999');
    $this->failOn = null;
    $this->artisan('deploy:finish')->assertSuccessful();
    expect(FinishDeploy::status())->toMatchArray(['release' => 'fix9999', 'status' => 'done']);
});

it('retries a release whose run was killed midway, but not one still running', function () {
    Artisan::call('migrate');
    File::put($this->releaseFile, 'kill123');
    File::ensureDirectoryExists(dirname(FinishDeploy::statusPath()));
    $running = fn (int $minutesAgo) => File::put(FinishDeploy::statusPath(), json_encode([
        'release' => 'kill123', 'status' => 'running', 'at' => now()->subMinutes($minutesAgo)->toIso8601String(),
    ]));

    $running(2);
    $this->artisan('deploy:finish')->assertSuccessful();
    expect($this->ran)->toBeEmpty();

    $running(FinishDeploy::STALE_MINUTES + 1);
    $this->artisan('deploy:finish')->assertSuccessful();
    expect(FinishDeploy::status())->toMatchArray(['release' => 'kill123', 'status' => 'done']);
});

// Juga dijalankan GitHub Actions terhadap MySQL sungguhan (database produksi), lihat .github/workflows/deploy.yml
it('writes a gzip SQL backup that restores the data and keeps only the newest ones', function () {
    Artisan::call('migrate:fresh');
    Product::create(['name' => "Semen O'Brien \\ 40kg", 'slug' => 'semen-obrien', 'is_published' => true]);
    DB::table('sessions')->insert(['id' => 'sesi-uji', 'payload' => 'x', 'last_activity' => time()]);

    foreach (range(1, DatabaseBackup::KEEP + 2) as $i) {
        $this->travel(1)->seconds();
        $path = DatabaseBackup::create("r{$i}");
    }
    $sql = gzdecode(File::get($path));
    $products = DB::getQueryGrammar()->wrapTable('products');

    expect(File::glob(DatabaseBackup::directory().'/db-*.sql.gz'))->toHaveCount(DatabaseBackup::KEEP)
        ->and($sql)->toContain("CREATE TABLE {$products}")
        ->and($sql)->toContain("INSERT INTO {$products}")
        ->and($sql)->not->toContain('sesi-uji');

    // Pulihkan: data yang terhapus setelah pencadangan kembali persis
    Product::query()->delete();
    DB::unprepared($sql);

    expect(Product::sole()->name)->toBe("Semen O'Brien \\ 40kg")
        ->and(DB::table('migrations')->count())->toBeGreaterThan(0);
});

it('runs every minute even while the site is in maintenance mode', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'deploy:finish'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->evenInMaintenanceMode)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue();
});
