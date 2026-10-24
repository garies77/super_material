<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** @tags Merek */
class BrandController extends ApiController
{
    /**
     * Daftar merek
     *
     * Merek beserta jumlah produk terbit.
     */
    public function index(Request $request): JsonResponse
    {
        $brands = Brand::withCount(['products' => fn ($q) => $q->published()])->orderBy('name')->get();

        return $this->withLastModified(
            response()->json(['data' => BrandResource::collection($brands)->resolve($request)]),
            $brands->max('updated_at'),
        );
    }
}
