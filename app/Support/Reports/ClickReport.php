<?php

namespace App\Support\Reports;

use App\Models\Article;
use App\Models\Category;
use App\Models\ClickEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ringkasan klik agregat dari click_events (PRD 8.12.6). Tidak memuat data pribadi:
 * hanya jumlah klik per produk, kategori, artikel asal, kanal, atau hari.
 */
class ClickReport
{
    public const GROUPS = ['product', 'category', 'article', 'channel', 'day'];

    public const TIMEZONE = 'Asia/Jakarta';

    public function __construct(
        public Carbon $from,
        public Carbon $to,
        public string $groupBy = 'product',
        public ?string $channel = null,
        public ?string $category = null,
        public bool $includeBots = false,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function rows(): Collection
    {
        return match ($this->groupBy) {
            'product' => $this->base()
                ->join('products', 'products.id', '=', 'click_events.product_id')
                ->groupBy('products.id', 'products.slug', 'products.name')
                ->selectRaw('products.id as product_id, products.slug, products.name, count(click_events.id) as clicks')
                ->orderByDesc('clicks')->toBase()->get()
                ->map(fn ($r) => ['product_id' => (int) $r->product_id, 'slug' => $r->slug, 'name' => $r->name, 'clicks' => (int) $r->clicks]),
            'category' => $this->base()
                ->join('products', 'products.id', '=', 'click_events.product_id')
                ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->groupBy('categories.id', 'categories.slug', 'categories.name')
                ->selectRaw('categories.slug, categories.name, count(click_events.id) as clicks')
                ->orderByDesc('clicks')->toBase()->get()
                ->map(fn ($r) => ['slug' => $r->slug, 'name' => $r->name ?? 'Tanpa kategori', 'clicks' => (int) $r->clicks]),
            'channel' => $this->base()
                ->join('sales_channels', 'sales_channels.id', '=', 'click_events.sales_channel_id')
                ->groupBy('sales_channels.id', 'sales_channels.slug', 'sales_channels.name')
                ->selectRaw('sales_channels.slug, sales_channels.name, count(click_events.id) as clicks')
                ->orderByDesc('clicks')->toBase()->get()
                ->map(fn ($r) => ['slug' => $r->slug, 'name' => $r->name, 'clicks' => (int) $r->clicks]),
            'day' => $this->byDay(),
            'article' => $this->byArticle(),
        };
    }

    public function totalClicks(): int
    {
        return $this->base()->count();
    }

    private function base(): Builder
    {
        return ClickEvent::query()
            ->whereBetween('click_events.created_at', [$this->from->copy()->utc(), $this->to->copy()->utc()])
            ->when(! $this->includeBots, fn ($q) => $q->where('click_events.is_bot', false))
            ->when($this->channel, fn ($q) => $q->whereHas('channel', fn ($c) => $c->where('slug', $this->channel)))
            ->when($this->category, function ($q) {
                $category = Category::where('slug', $this->category)->first();
                $q->whereHas('product', fn ($p) => $p->whereIn('category_id', $category ? $category->descendantIds() : [0]));
            });
    }

    /** Dikelompokkan per hari kalender WIB. */
    private function byDay(): Collection
    {
        $counts = [];
        $this->base()->select('click_events.created_at')->orderBy('click_events.id')
            ->chunk(5000, function ($events) use (&$counts) {
                foreach ($events as $event) {
                    $day = $event->created_at->setTimezone(self::TIMEZONE)->toDateString();
                    $counts[$day] = ($counts[$day] ?? 0) + 1;
                }
            });
        ksort($counts);

        return collect($counts)->map(fn ($clicks, $date) => ['date' => $date, 'clicks' => $clicks])->values();
    }

    /** Artikel asal dibaca dari page_url (/artikel/{slug}) yang tercatat saat klik. */
    private function byArticle(): Collection
    {
        $prefix = rtrim(parse_url(route('article.show', 'x'), PHP_URL_PATH), 'x');
        $counts = $this->base()->whereNotNull('page_url')
            ->groupBy('page_url')->selectRaw('page_url, count(*) as clicks')->toBase()->get()
            ->reduce(function (array $carry, $row) use ($prefix) {
                $path = (string) parse_url((string) $row->page_url, PHP_URL_PATH);
                if (str_starts_with($path, $prefix)) {
                    $slug = trim(substr($path, strlen($prefix)), '/');
                    if ($slug !== '' && ! str_contains($slug, '/')) {
                        $carry[$slug] = ($carry[$slug] ?? 0) + (int) $row->clicks;
                    }
                }

                return $carry;
            }, []);

        $titles = Article::whereIn('slug', array_keys($counts))->pluck('title', 'slug');
        arsort($counts);

        return collect($counts)->map(fn ($clicks, $slug) => [
            'slug' => $slug,
            'title' => $titles[$slug] ?? null,
            'clicks' => $clicks,
        ])->values();
    }
}
