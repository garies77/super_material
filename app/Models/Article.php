<?php

namespace App\Models;

use App\Models\Concerns\GuardsCustomCode;
use App\Models\Concerns\HasReviewWorkflow;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\TracksPublicUrl;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Article extends Model
{
    use GuardsCustomCode, HasReviewWorkflow, HasSeo, TracksPublicUrl;

    public const PUBLIC_ROUTE = 'article.show';

    public const SCHEMA_TYPES = [
        'BlogPosting' => 'BlogPosting (bawaan)',
        'Article' => 'Article',
        'NewsArticle' => 'NewsArticle',
    ];

    /** Kolom yang perubahannya dianggap pembaruan konten bermakna (PRD 8.3 & 9.11.6). */
    public const MEANINGFUL_FIELDS = ['title', 'summary', 'content', 'image'];

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (Article $article) {
            // Penulis hanya boleh menerbitkan atas nama profilnya sendiri, apa pun yang dikirim form
            if (auth()->user()?->role === 'author') {
                $article->author_id = auth()->user()->authorProfile()->id;
            }

            if ($article->exists && $article->isDirty(self::MEANINGFUL_FIELDS)) {
                $article->content_updated_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'content_updated_at' => 'datetime',
            'noindex' => 'boolean',
            'nofollow' => 'boolean',
            'is_featured' => 'boolean',
            'views_count' => 'integer',
        ];
    }

    /** Terbit atau terjadwal: keduanya keputusan Admin/Editor. */
    public function isLive(): bool
    {
        return $this->published_at !== null;
    }

    public function wasLive(): bool
    {
        return $this->getOriginal('published_at') !== null;
    }

    public function markAsDraft(): void
    {
        $this->published_at = null;
        $this->is_featured = false;
    }

    /** Jadwal terbit yang sudah diisi Editor dipertahankan; selain itu terbit sekarang. */
    public function markAsLive(): void
    {
        $this->published_at ??= now();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id');
    }

    public function user(): BelongsTo
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
        return $q->published()->with('categories')->orderByDesc('views_count')->limit($limit); // widget menampilkan kategori
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

        return asset('storage/'.ltrim($this->image, '/'));
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->content)) / 200));
    }

    public function readingTime(): string
    {
        return $this->readingMinutes().' menit';
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

        return $this->published_at->copy()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('l, d M Y H:i').' WIB';
    }

    /**
     * Tanggal pembaruan bermakna. Bukan updated_at, karena updated_at ikut berubah
     * saat penghitung views bertambah atau kolom non-konten disunting.
     */
    public function modifiedAt(): ?Carbon
    {
        $published = $this->published_at ?? $this->created_at;

        if ($this->content_updated_at && $published && $this->content_updated_at->gt($published)) {
            return $this->content_updated_at;
        }

        return $published;
    }

    public function schemaType(): string
    {
        return array_key_exists((string) $this->schema_type, self::SCHEMA_TYPES) ? $this->schema_type : 'BlogPosting';
    }

    public function formattedUpdatedAt(): ?string
    {
        $modified = $this->modifiedAt();
        if (! $modified || ! $this->published_at) {
            return null;
        }

        // Tampilkan hanya bila perbedaan pembaruan bermakna (misal beda minimal 2 jam atau beda hari)
        if ($modified->diffInHours($this->published_at) >= 2) {
            return $modified->copy()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('l, d M Y H:i').' WIB';
        }

        return null;
    }

    /**
     * Inisial redaksi gaya cetak "(gar/ed)" tidak lagi ditampilkan; kredibilitas dibaca dari profil penulis
     * dan JSON-LD Person. Kolom lama tetap ada di database dan diabaikan.
     */
    public function initialsBlock(): ?string
    {
        return null;
    }

    /**
     * Beri id pada heading h2/h3 dan kembalikan [html, daftar isi]. Paragraf pembuka dibiarkan apa adanya
     * (tanpa dateline otomatis).
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
            $level = (int) $m[1];
            $attrs = $m[2];
            $inner = $m[3];
            $text = trim(strip_tags($inner));
            $id = Str::slug($text) ?: 'bagian';
            $base = $id;
            for ($i = 2; in_array($id, $used, true); $i++) {
                $id = $base.'-'.$i;
            }
            $used[] = $id;
            $toc[] = ['id' => $id, 'text' => $text, 'level' => $level];

            // Tambahkan scroll-mt-24 agar heading tidak tertutup sticky header saat di-scroll
            if (preg_match('/class="([^"]*)"/i', $attrs, $classMatch)) {
                $attrs = str_replace($classMatch[0], 'class="'.trim($classMatch[1].' scroll-mt-24').'"', $attrs);
            } else {
                $attrs = ' class="scroll-mt-24"'.$attrs;
            }

            return "<h{$level} id=\"{$id}\"{$attrs}>{$inner}</h{$level}>";
        }, $raw);

        return [(string) $html, $toc];
    }

    public function seoName(): string
    {
        return $this->title;
    }

    public function seoFallbackDescription(): ?string
    {
        return $this->summary ?: strip_tags((string) $this->content);
    }

    public function primaryImageUrl(): ?string
    {
        if ($this->og_image) {
            return $this->seoImage();
        }

        return $this->imageUrl();
    }
}
