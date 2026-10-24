<?php

namespace App\Http\Resources\Api;

use App\Models\SalesChannel;
use Illuminate\Http\Request;

/** @mixin SalesChannel */
class SalesChannelResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        /** @var SalesChannel $channel */
        $channel = $this->resource;

        return [
            'slug' => $channel->slug,
            'name' => $channel->name,
            'type' => $channel->type,
            'color' => $channel->color,
        ];
    }
}
