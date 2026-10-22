@php
    $siteBrand = \App\Models\Setting::brandName();
    $authorName = $author->displayName();
    $pureName = $author->name;
    $canonical = route('author.show', $author->slug);
    $page = $articles->currentPage();
    $pageCanonical = $page > 1 ? $canonical.'?page='.$page : $canonical;
    $pageSuffix = $page > 1 ? ' — Halaman '.$page : '';
    $avatar = $author->avatarUrl() ?: \App\Models\Setting::defaultOgImage();

    $crumbs = [
        ['Artikel', route('article.index')],
        ['Penulis: ' . $pureName, null],
    ];

    // ProfilePage Structured Data (PRD 9.13.2 & SEO-02)
    $profileLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'ProfilePage',
        'dateCreated' => $author->created_at->setTimezone('Asia/Jakarta')->toIso8601String(),
        'dateModified' => $author->updated_at->setTimezone('Asia/Jakarta')->toIso8601String(),
        'mainEntity' => array_filter([
            '@type' => 'Person',
            'name' => $pureName,
            'jobTitle' => $author->job_title ?: null,
            'honorificPrefix' => $author->honorific_prefix ?: null,
            'honorificSuffix' => $author->honorific_suffix ?: null,
            'url' => $canonical,
            'image' => $author->avatarUrl(),
            'description' => $author->bio,
            'sameAs' => ! empty($author->same_as) ? (array) $author->same_as : null,
        ]),
    ]);
@endphp

<x-layouts.app :title="$authorName . ' — Profil Penulis' . $pageSuffix . ' | ' . $siteBrand"
               :description="'Profil dan daftar artikel yang ditulis oleh ' . $authorName . ' di ' . $siteBrand . $pageSuffix . '. ' . ($author->bio ? \Illuminate\Support\Str::limit($author->bio, 120) : '')"
               :canonical="$pageCanonical"
               :image="$avatar"
               :image-alt="$authorName"
               :image-width="800"
               :image-height="800">
    @push('jsonld')<x-jsonld :data="$profileLd" />@endpush

    <div class="container-x py-8 lg:py-12">
        <x-breadcrumb :items="$crumbs" />

        {{-- Profil Penulis Card --}}
        <div class="rounded-3xl border border-ink-100 bg-white p-6 sm:p-8 md:p-10 shadow-sm mb-12">
            <div class="flex flex-col md:flex-row items-start md:items-center gap-6 md:gap-8">
                <div class="relative shrink-0">
                    @if($author->avatarUrl())
                        <img src="{{ $author->avatarUrl() }}" alt="{{ $authorName }}" class="h-24 w-24 md:h-28 md:w-28 rounded-2xl object-cover ring-4 ring-brand-50 shadow-md">
                    @else
                        <div class="grid h-24 w-24 md:h-28 md:w-28 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-3xl font-black text-white shadow-md">
                            {{ mb_substr($pureName, 0, 1) }}
                        </div>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span> Penulis Terverifikasi
                        </span>
                        @if($author->job_title)
                            <span class="inline-flex items-center rounded-full bg-ink-100 px-3 py-1 text-xs font-medium text-ink-700">
                                {{ $author->job_title }}
                            </span>
                        @endif
                    </div>

                    <h1 class="font-display text-2xl sm:text-3xl md:text-4xl font-extrabold text-ink-900 tracking-tight">
                        {{ $authorName }}
                    </h1>

                    @if($author->bio)
                        <p class="mt-3 text-sm sm:text-base leading-relaxed text-ink-600 max-w-3xl">
                            {{ $author->bio }}
                        </p>
                    @endif

                    @if(! empty($author->same_as) && is_array($author->same_as))
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-400 mr-1">Profil:</span>
                            @foreach($author->same_as as $link)
                                @if(!empty($link))
                                    @php
                                        $host = parse_url($link, PHP_URL_HOST) ?? 'link';
                                        $cleanHost = preg_replace('/^www\./', '', $host);
                                    @endphp
                                    <a href="{{ $link }}" target="_blank" rel="noopener noreferrer nofollow"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-2.5 py-1 text-xs font-medium text-ink-700 transition hover:border-brand-500 hover:text-brand-600">
                                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-ink-400" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                        {{ $cleanHost }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Artikel oleh Penulis --}}
        <div class="mb-6 flex items-baseline justify-between border-b border-ink-100 pb-4">
            <div>
                <h2 class="font-display text-xl sm:text-2xl font-bold text-ink-900">
                    Artikel Ditulis oleh {{ $pureName }}
                </h2>
                <p class="mt-1 text-xs sm:text-sm text-ink-500">
                    Kumpulan panduan, kajian teknis, dan wawasan bahan bangunan yang dipublikasikan.
                </p>
            </div>
            <span class="text-xs sm:text-sm font-semibold text-ink-500">{{ $articles->total() }} artikel</span>
        </div>

        @if($articles->isNotEmpty())
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($articles as $article)
                    @php $artImg = $article->primaryImageUrl() ?: \App\Models\Setting::defaultOgImage(); @endphp
                    <article class="card flex flex-col overflow-hidden group">
                        <a href="{{ $article->url() }}" class="relative block aspect-[16/9] overflow-hidden bg-ink-100">
                            @if($artImg)
                                <img src="{{ $artImg }}" alt="{{ $article->image_caption ?: $article->title }}"
                                     width="800" height="450" loading="lazy"
                                     class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                            @endif
                            @if($cat = $article->categories->first())
                                <span class="absolute left-3 top-3 rounded-full bg-ink-950/70 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur-xs">
                                    {{ $cat->name }}
                                </span>
                            @endif
                        </a>
                        <div class="flex flex-1 flex-col p-5">
                            <p class="text-xs text-ink-400 mb-2">
                                {{ $article->published_at?->translatedFormat('d M Y') ?: $article->created_at->translatedFormat('d M Y') }}
                            </p>
                            <h3 class="font-display text-base font-bold text-ink-900 group-hover:text-brand-600 transition line-clamp-2">
                                <a href="{{ $article->url() }}">{{ $article->title }}</a>
                            </h3>
                            @if($article->summary)
                                <p class="mt-2 text-xs leading-relaxed text-ink-500 line-clamp-2 flex-1">
                                    {{ $article->summary }}
                                </p>
                            @endif
                            <div class="mt-4 pt-3 border-t border-ink-100 flex items-center justify-between text-xs text-ink-400">
                                <span>{{ $article->readingTime() }} baca</span>
                                <span class="font-semibold text-brand-600 group-hover:translate-x-0.5 transition inline-flex items-center gap-1">
                                    Baca &rarr;
                                </span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-10">
                {{ $articles->links() }}
            </div>
        @else
            <div class="rounded-2xl border border-ink-100 bg-ink-50 p-12 text-center text-ink-500">
                Belum ada artikel publikasi dari penulis ini.
            </div>
        @endif
    </div>
</x-layouts.app>
