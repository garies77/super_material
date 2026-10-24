<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\SalesChannelResource;
use App\Models\SalesChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** @tags Kanal pembelian */
class SalesChannelController extends ApiController
{
    /**
     * Kanal pembelian aktif
     */
    public function index(Request $request): JsonResponse
    {
        $channels = SalesChannel::where('is_active', true)->orderBy('sort_order')->get();

        return response()->json(['data' => SalesChannelResource::collection($channels)->resolve($request)]);
    }
}
