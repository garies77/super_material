<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\BrandResource;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Support\Catalog\TaxonomyRules;
use App\Support\Catalog\TaxonomyWriter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Pengelolaan merek lewat token, dengan aturan slug yang sama dengan panel admin.
 *
 * @tags Admin: Merek
 */
class BrandController extends ApiController
{
    protected bool $admin = true;

    /**
     * Membuat merek
     *
     * Ability: taxonomy:write. Mendukung header Idempotency-Key.
     */
    public function store(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), TaxonomyRules::brand(), TaxonomyRules::messages())->validate();
        $brand = new Brand;
        TaxonomyWriter::saveBrand($brand, $data);

        AuditLog::record($request, 'brand.created', $brand, ['fields' => array_keys($data)]);

        return BrandResource::make($brand->refresh()->loadCount('products'))->response()->setStatusCode(201);
    }

    /**
     * Mengubah merek
     *
     * Ability: taxonomy:write. Hanya kolom yang dikirim yang berubah. {brand} berupa ID atau slug.
     */
    public function update(Request $request, string $brand): BrandResource
    {
        $model = ctype_digit($brand) ? Brand::find((int) $brand) : Brand::firstWhere('slug', $brand);
        if (! $model) {
            throw (new ModelNotFoundException)->setModel(Brand::class);
        }

        $data = Validator::make($request->all(), TaxonomyRules::brand($model), TaxonomyRules::messages())->validate();
        $changes = TaxonomyWriter::saveBrand($model, $data);

        if ($changes) {
            AuditLog::record($request, 'brand.updated', $model, $changes);
        }

        return BrandResource::make($model->refresh()->loadCount('products'));
    }
}
