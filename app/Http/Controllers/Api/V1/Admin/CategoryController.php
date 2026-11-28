<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\CategoryResource;
use App\Models\AuditLog;
use App\Models\Category;
use App\Support\Catalog\TaxonomyRules;
use App\Support\Catalog\TaxonomyWriter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Pengelolaan kategori lewat token. Validasi sama dengan panel admin (slug, maksimal tiga level);
 * perubahan slug membuat redirect 301 dari URL lama.
 *
 * @tags Admin: Kategori
 */
class CategoryController extends ApiController
{
    protected bool $admin = true;

    /**
     * Membuat kategori
     *
     * Ability: taxonomy:write. Kategori langsung aktif kecuali is_active=false. Induk memakai slug (parent).
     * Mendukung header Idempotency-Key.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateCategory($request->all());
        $category = new Category(['is_active' => true, 'sort_order' => 0]);
        TaxonomyWriter::saveCategory($category, $data);

        AuditLog::record($request, 'category.created', $category, ['fields' => array_keys($data)]);

        return CategoryResource::make($category->refresh()->load('parent'))->response()->setStatusCode(201);
    }

    /**
     * Mengubah kategori
     *
     * Ability: taxonomy:write. Hanya kolom yang dikirim yang berubah. {category} berupa ID atau slug.
     */
    public function update(Request $request, string $category): CategoryResource
    {
        $model = $this->findCategory($category);
        $data = $this->validateCategory($request->all(), $model);
        $changes = TaxonomyWriter::saveCategory($model, $data);

        if ($changes) {
            AuditLog::record($request, 'category.updated', $model, $changes);
        }

        return CategoryResource::make($model->refresh()->load('parent'));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateCategory(array $input, ?Category $category = null): array
    {
        return Validator::make($input, TaxonomyRules::category($category), TaxonomyRules::messages())
            ->after(fn ($validator) => TaxonomyRules::categoryAfter($validator, $category))
            ->validate();
    }

    private function findCategory(string $key): Category
    {
        $category = ctype_digit($key) ? Category::find((int) $key) : Category::firstWhere('slug', $key);

        return $category ?? throw (new ModelNotFoundException)->setModel(Category::class);
    }
}
