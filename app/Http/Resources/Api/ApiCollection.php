<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Respons daftar: {"data": [], "links": {"next", "prev"}, "meta": {"per_page", "total"}} (PRD 8.12.4).
 * Kolom terbatas lewat fields=a,b berlaku untuk tiap item daftar.
 */
class ApiCollection extends AnonymousResourceCollection
{
    protected ?int $total = null;

    public function withTotal(int $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function toArray(Request $request)
    {
        $items = parent::toArray($request);
        $fields = $this->requestedFields($request);

        if (! $fields) {
            return $items;
        }

        return array_map(fn ($item) => is_array($item) ? array_intersect_key($item, array_flip($fields)) : $item, $items);
    }

    /**
     * @return array{links: array{next: ?string, prev: ?string}, meta: array{per_page: int, total: ?int}}
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'links' => [
                'next' => $paginated['next_page_url'] ?? null,
                'prev' => $paginated['prev_page_url'] ?? null,
            ],
            'meta' => [
                'per_page' => (int) ($paginated['per_page'] ?? 0),
                'total' => $this->total,
            ],
        ];
    }

    /** @return array<int, string> */
    private function requestedFields(Request $request): array
    {
        $raw = $request->query('fields');
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
