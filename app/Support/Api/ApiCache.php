<?php

namespace App\Support\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Cache respons API publik berbasis versi. Setiap perubahan model konten menaikkan versi,
 * sehingga seluruh respons lama otomatis tidak terpakai (PRD 8.12.8 & 9.16).
 */
class ApiCache
{
    private const VERSION_KEY = 'api.cache.version';

    public static function version(): string
    {
        return (string) Cache::rememberForever(self::VERSION_KEY, fn () => (string) microtime(true));
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (string) microtime(true));
    }

    public static function key(Request $request): string
    {
        $query = $request->query();
        ksort($query);

        return 'api.response.'.self::version().'.'.sha1($request->path().'?'.http_build_query($query));
    }
}
