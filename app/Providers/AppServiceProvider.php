<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAdminSession;
use App\Models\ApiToken;
use App\Models\Article;
use App\Models\Author;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductLink;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use App\Models\Setting;
use App\Models\Tag;
use App\Support\Api\ApiCache;
use App\Support\Api\ApiError;
use App\Support\Api\ClientHash;
use App\Support\SvgSanitizer;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Filament\Forms\Components\FileUpload;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Model konten yang tampil di API publik; perubahan apa pun membersihkan cache respons API.
     */
    private const API_CACHED_MODELS = [
        Product::class, ProductVariant::class, ProductLink::class, ProductMedia::class,
        Category::class, Brand::class, SalesChannel::class, Article::class, Tag::class,
        Author::class, Faq::class, Setting::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureHttps();
        $this->configureApiTokens();
        $this->configureRateLimits();
        $this->configureApiCache();
        $this->configureApiDocs();

        // Password pengguna: lebih dari 8 karakter dengan huruf, angka, dan simbol (dipakai form Pengguna dan Profil)
        Password::defaults(fn () => Password::min(9)->letters()->numbers()->symbols());

        // Setiap unggahan Filament: SVG dibersihkan dulu, lalu disimpan lewat jalur bawaan
        FileUpload::configureUsing(fn (FileUpload $upload) => $upload->saveUploadedFileUsing(
            function (FileUpload $component, TemporaryUploadedFile $file): ?string {
                SvgSanitizer::cleanInPlace($file, $component->getStatePath());

                return $component->saveUploadedFile($file);
            }
        ));
    }

    /**
     * Di balik proxy/CDN: percayai X-Forwarded-* dari proxy yang dikonfigurasi (TRUSTED_PROXIES).
     * Di produksi: semua URL yang dibuat aplikasi (canonical, sitemap, email antrean) memakai https.
     */
    public function configureHttps(): void
    {
        if (filled($proxies = config('security.trusted_proxies'))) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        if ($this->app->isProduction() && config('security.force_https')) {
            URL::forceHttps();
        }
    }

    private function configureApiTokens(): void
    {
        Sanctum::usePersonalAccessTokenModel(ApiToken::class);

        // Pengguna nonaktif langsung kehilangan akses token miliknya (PRD 8.12.3)
        Sanctum::authenticateAccessTokensUsing(
            fn (ApiToken $token, bool $isValid) => $isValid && (bool) $token->tokenable?->is_active,
        );
    }

    private function configureRateLimits(): void
    {
        $tooMany = fn (Request $request, array $headers) => ApiError::response(429, null, null, [], $headers);

        // Formulir publik: 5 kiriman per menit dan 30 per jam per alamat (hash harian, bukan IP mentah)
        RateLimiter::for('form-submit', fn (Request $request) => [
            Limit::perMinute(5)->by('form|'.ClientHash::forRequest($request)),
            Limit::perHour(30)->by('form-hour|'.ClientHash::forRequest($request)),
        ]);

        RateLimiter::for('api-public', fn (Request $request) => Limit::perMinute(config('api.rate_limits.public', 60))
            ->by('public|'.ClientHash::forRequest($request))
            ->response($tooMany));

        RateLimiter::for('api-admin', fn (Request $request) => Limit::perMinute(config('api.rate_limits.admin', 120))
            ->by('admin|'.($request->user()?->currentAccessToken()?->id ?? ClientHash::forRequest($request)))
            ->response($tooMany));
    }

    private function configureApiCache(): void
    {
        foreach (self::API_CACHED_MODELS as $model) {
            $model::saved(fn () => ApiCache::flush());
            $model::deleted(fn () => ApiCache::flush());
        }
    }

    /** OpenAPI 3.1 di /api/docs; endpoint admin terpisah di /api/docs/admin khusus Admin (PRD 8.12.9). */
    private function configureApiDocs(): void
    {
        Scramble::configure()
            ->routes(fn (Route $route) => Str::startsWith($route->uri, 'api/v1/') && ! Str::startsWith($route->uri, 'api/v1/admin'))
            ->expose(
                ui: fn ($router, $action) => $router->get('api/docs', $action)->name('api.docs'),
                document: fn ($router, $action) => $router->get('api/docs.json', $action)->name('api.docs.json'),
            );

        Scramble::registerApi('admin', [
            'info' => [
                'version' => config('scramble.info.version'),
                'description' => 'Endpoint admin REST API v1. Kirim token lewat header Authorization: Bearer {token}. Hak efektif adalah irisan ability token dan peran pengguna.',
            ],
            'ui' => ['title' => 'Supermaterial API v1 (Admin)'],
            'middleware' => ['web', EnsureAdminSession::class],
        ])
            ->routes(fn (Route $route) => Str::startsWith($route->uri, 'api/v1/admin'))
            ->withDocumentTransformers(fn (OpenApi $openApi) => $openApi->secure(SecurityScheme::http('bearer')))
            ->expose(
                ui: fn ($router, $action) => $router->get('api/docs/admin', $action)->name('api.docs.admin'),
                document: fn ($router, $action) => $router->get('api/docs/admin.json', $action)->name('api.docs.admin.json'),
            );
    }
}
