<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasSeo;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'noindex' => 'boolean',
            'is_featured' => 'boolean',
            'views_count' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'article_product');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'article_category');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function relatedArticles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_related', 'article_id', 'related_article_id');
    }

    public function faqs(): MorphMany
    {
        return $this->morphMany(Faq::class, 'faqable')->orderBy('sort_order');
    }

    /** Terbit bila published_at terisi dan sudah lewat (mendukung penjadwalan). */
    public function scopePublished(Builder $q): Builder
    {
        return $q->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeFeatured(Builder $q): Builder
    {
        return $q->where('is_featured', true);
    }

    public function scopePopular(Builder $q, int $limit = 5): Builder
    {
        return $q->published()->orderByDesc('views_count')->limit($limit);
    }

    public function url(): string
    {
        return route('article.show', $this->slug);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        return asset('storage/' . ltrim($this->image, '/'));
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->content)) / 200));
    }

    /**
     * Format tanggal dan jam khas CNN Indonesia:
     * Contoh: "Selasa, 20 Okt 2026 10:04 WIB"
     */
    public function formattedPublishedAt(): string
    {
        if (! $this->published_at) {
            return '';
        }

        return $this->published_at->locale('id')->translatedFormat('l, d M Y H:i') . ' WIB';
    }

    public function formattedUpdatedAt(): ?string
    {
        if (! $this->updated_at || ! $this->published_at) {
            return null;
        }

        // Tampilkan hanya bila perbedaan pembaruan bermakna (misal beda minimal 2 jam atau beda hari)
        if ($this->updated_at->diffInHours($this->published_at) >= 2) {
            return $this->updated_at->locale('id')->translatedFormat('l, d M Y H:i') . ' WIB';
        }

        return null;
    }

    /**
     * Format inisial di akhir artikel khas media berita:
     * Contoh: (gar/ed) atau (gar)
     */
    public function initialsBlock(): ?string
    {
        $parts = array_filter([$this->author_initials, $this->editor_initials]);
        if (empty($parts)) {
            return null;
        }

        return '(' . implode('/', $parts) . ')';
    }

    /**
     * Beri id pada heading h2/h3, tambahkan dateline di paragraf pertama, dan kembalikan [html, daftar isi].
     *
     * @return array{0: string, 1: array<int, array{id: string, text: string, level: int}>}
     */
    public function contentWithToc(): array
    {
        $toc = [];
        $used = [];
        $raw = (string) $this->content;

        // Parse TOC dari h2 & h3
        $html = preg_replace_callback('/<h([23])([^>]*)>(.*?)<\/h\1>/is', function ($m) use (&$toc, &$used) {
            $text = trim(strip_tags($m[3]));
            $id = Str::slug($text) ?: 'bagian';
            $base = $id;
            for ($i = 2; in_array($id, $used, true); $i++) {
                $id = $base.'-'.$i;
            }
            $used[] = $id;
            $toc[] = ['id' => $id, 'text' => $text, 'level' => (int) $m[1]];

            return "<h{$m[1]} id=\"{$id}\"{$m[2]}>{$m[3]}</h{$m[1]}>";
        }, $raw);

        // Prepend dateline di paragraf pertama jika ada dan belum tertulis di teks
        $dateline = trim((string) ($this->dateline ?: 'Medan, Supermaterial --'));
        if ($dateline !== '') {
            $datelinePrefix = '<strong>' . e($dateline) . '</strong> ';
            // Hanya inject jika dateline belum ada di dalam html
            if (! str_contains(strip_tags($html), $dateline)) {
                $html = preg_replace('/<p([^>]*)>/i', '<p$1>' . $datelinePrefix, $html, 1);
            }
        }

        return [$html, $toc];
    }

    public function seoName(): string
    {
        return $this->title;
    }

    public function seoFallbackDescription(): ?string
    {
        return $this->summary ?: strip_tags((string) $this->content);
    }
}
