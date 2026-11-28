<?php

namespace App\Mcp\Tools;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Tag;
use App\Support\Reports\ClickReport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Riwayat perubahan (khusus Admin): siapa, kapan, dari mana (admin, api, mcp), dan apa yang berubah. Bisa untuk satu produk/artikel/kategori/merek/tag/impor (subject_type + subject), atau semua perubahan terbaru dengan filter action/source. Berguna untuk menjawab "siapa yang mengubah harga ini?" atau memeriksa hasil kerja asisten AI.')]
#[IsReadOnly]
class GetAuditLog extends AbilityTool
{
    /** @var array<string, class-string<Model>> */
    public const SUBJECT_TYPES = [
        'product' => Product::class,
        'article' => Article::class,
        'category' => Category::class,
        'brand' => Brand::class,
        'tag' => Tag::class,
        'import' => ProductImport::class,
    ];

    protected string $ability = 'audit:read';

    public function handle(Request $request): Response|ResponseFactory
    {
        $input = $this->validated($request, [
            'subject_type' => ['required_with:subject', Rule::in(array_keys(self::SUBJECT_TYPES))],
            'subject' => ['sometimes', 'string'],
            'action' => ['sometimes', 'string', 'max:100'],
            'source' => ['sometimes', Rule::in(['admin', 'api', 'mcp'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AuditLog::query()->with('user:id,name')->latest('created_at')->latest('id');

        if (isset($input['subject_type'])) {
            $modelClass = self::SUBJECT_TYPES[$input['subject_type']];
            $query->where('subject_type', (new $modelClass)->getMorphClass());

            if (isset($input['subject'])) {
                $subject = $this->findSubject($modelClass, $input['subject']);
                if (! $subject) {
                    return Response::error("Tidak ada {$input['subject_type']} dengan ID atau slug \"{$input['subject']}\".");
                }
                $query->where('subject_id', $subject->getKey());
            }
        }

        $query->when($input['action'] ?? null, fn ($q, string $action) => $q->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], $action).'%'))
            ->when($input['source'] ?? null, fn ($q, string $source) => $q->where('source', $source));

        return Response::structured([
            'entries' => $query->limit($input['limit'] ?? 20)->get()->map(fn (AuditLog $log) => [
                'at' => $log->created_at?->timezone(ClickReport::TIMEZONE)->toIso8601String(),
                'action' => $log->action,
                'source' => $log->source,
                'status' => $log->status,
                'user' => $log->user?->name,
                'subject_type' => array_search($log->subject_type, array_map(fn (string $class) => (new $class)->getMorphClass(), self::SUBJECT_TYPES), true) ?: $log->subject_type,
                'subject_id' => $log->subject_id,
                'changes' => $log->changes,
            ])->all(),
        ]);
    }

    /** @param  class-string<Model>  $modelClass */
    private function findSubject(string $modelClass, string $key): ?Model
    {
        $query = $modelClass::query();
        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        return ctype_digit($key) || $modelClass === ProductImport::class
            ? $query->whereKey((int) $key)->first()
            : $query->where('slug', $key)->first();
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'subject_type' => $schema->string()->enum(array_keys(self::SUBJECT_TYPES))->description('Jenis data; wajib bila subject diisi.'),
            'subject' => $schema->string()->description('ID atau slug data (impor: ID). Produk/artikel arsip ikut dicari.'),
            'action' => $schema->string()->description('Awalan aksi, mis. "product.updated", "product." atau "article.published".'),
            'source' => $schema->string()->enum(['admin', 'api', 'mcp'])->description('Asal perubahan.'),
            'limit' => $schema->integer()->min(1)->max(100)->description('Jumlah entri terbaru, bawaan 20.'),
        ];
    }
}
