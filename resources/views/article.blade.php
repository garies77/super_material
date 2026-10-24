@php
    $mainCategory = $article->categories->first();
    $crumbs = [
        ['Artikel', route('article.index')],
    ];
    if ($mainCategory) {
        $crumbs[] = [$mainCategory->name, route('article.category', $mainCategory->slug)];
    }
    $crumbs[] = [$article->title, null];

    $articleImage = $article->primaryImageUrl();
    $imageMultiRatio = \App\Support\ImageDerivatives::multiRatioUrls($articleImage);
    $authorModel = $article->author;
    $authorLd = $authorModel ? array_filter([
        '@type' => 'Person',
        'name' => $authorModel->name,
        'jobTitle' => $authorModel->job_title ?: null,
        'honorificPrefix' => $authorModel->honorific_prefix ?: null,
        'honorificSuffix' => $authorModel->honorific_suffix ?: null,
        'url' => route('author.show', $authorModel->slug),
        'sameAs' => ! empty($authorModel->same_as) ? (array) $authorModel->same_as : null,
    ]) : [
        '@type' => 'Organization',
        'name' => \App\Models\Setting::get('org_name', \App\Models\Setting::brandName()),
        'url' => url('/'),
    ];

    $publishedIso = ($article->published_at ?? $article->created_at)->setTimezone('Asia/Jakarta')->toIso8601String();
    $modifiedIso = $article->modifiedAt()->setTimezone('Asia/Jakarta')->toIso8601String();

    $ld = array_filter([
        '@context' => 'https://schema.org',
        '@type' => $article->schemaType(),
        'headline' => \Illuminate\Support\Str::limit($article->title, 110, ''),
        'description' => $article->seoDescription(),
        'image' => !empty($imageMultiRatio) ? $imageMultiRatio : ($articleImage ? [$articleImage] : null),
        'datePublished' => $publishedIso,
        'dateModified' => $modifiedIso,
        'author' => $authorLd,
        'publisher' => [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => \App\Models\Setting::get('org_name', \App\Models\Setting::brandName()),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => \App\Models\Setting::logoUrl() ?: asset('favicon.ico'),
            ],
        ],
        'mainEntityOfPage' => $article->url(),
    ]);

    $faqLd = $article->faqs->isNotEmpty() ? [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $article->faqs->map(fn ($f) => [
            '@type' => 'Question',
            'name' => $f->question,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f->answer]
        ])->all(),
    ] : null;


    // Pengaturan Artikel (tombol roda gigi di admin): layout, lebar, visibilitas, urutan, media sosial artikel
    $settings = \App\Support\ArticleSettings::get();
    $show = $settings['show'];
    $social = \App\Support\ArticleSettings::social();
    $socialLinks = \App\Support\ArticleSettings::socialLinks($article);
    $socialPositions = \App\Support\ArticleSettings::socialPositions($settings['has_sidebar']);
    $layout = $settings['layout'];
    $gridStyle = "--content: {$settings['content_width']}px; --sb: {$settings['sidebar_width']}px; --gap: {$settings['gap']}px";
@endphp

<x-layouts.app :title="$article->seoTitle()" :description="$article->seoDescription()" :canonical="$article->canonical_url ?: $article->url()"
               :image="$articleImage"
               :image-alt="$article->image_caption ?: $article->title"
               :image-width="1200"
               :image-height="675"
               type="article"
               :article-published-time="$publishedIso"
               :article-modified-time="$modifiedIso"
               :article-author="$authorModel ? route('author.show', $authorModel->slug) : null"
               :article-section="$mainCategory?->name"
               :article-tags="$article->tags"
               :noindex="$article->noindex"
               :nofollow="$article->nofollow">
    @push('jsonld')
        <x-jsonld :data="$ld" />
        @if($faqLd)<x-jsonld :data="$faqLd" />@endif
    @endpush


    <article class="container-x py-6 lg:py-10" data-article-layout="{{ $layout }}">
        @if($show['breadcrumb'])
            <div class="mb-4">
                <x-breadcrumb :items="$crumbs" />
            </div>
        @endif

        {{-- Grid utama: kolom bacaan + sidebar (kanan/kiri) atau tanpa sidebar, lebar dari Pengaturan Artikel --}}
        <div style="{{ $gridStyle }}"
             class="grid items-start gap-[var(--gap)] {{ match ($layout) {
                 'sidebar_left' => 'lg:grid-cols-[var(--sb)_minmax(0,1fr)]',
                 'standard' => 'grid-cols-1',
                 default => 'lg:grid-cols-[minmax(0,1fr)_var(--sb)]',
             } }}">

            {{-- ==================== KOLOM BACAAN UTAMA ==================== --}}
            <div class="min-w-0 w-full max-w-[var(--content)] {{ $layout === 'standard' ? 'mx-auto' : '' }} {{ $layout === 'sidebar_left' ? 'lg:order-2' : '' }}">

                @if($show['category'] && $mainCategory)
                    <a href="{{ route('article.category', $mainCategory->slug) }}"
                       class="inline-block text-xs font-black uppercase tracking-wider text-brand-600 hover:text-brand-700 mb-2">
                        {{ $mainCategory->name }}
                    </a>
                @endif

                {{-- Judul yang disembunyikan tetap ada untuk pembaca layar dan mesin pencari (H1) --}}
                <h1 class="{{ $show['title'] ? '' : 'sr-only' }} font-display text-2xl sm:text-3xl lg:text-[40px] font-black leading-[1.2] tracking-tight text-ink-950">
                    {{ $article->title }}
                </h1>

                @if(in_array('after_title', $socialPositions, true))
                    @include('article.share', ['links' => $socialLinks, 'variant' => 'compact'])
                @endif

                @foreach($settings['header_order'] as $block)
                    @if($block === 'meta')
                        @include('article.meta')
                        @if(in_array('after_meta', $socialPositions, true))
                            @include('article.share', ['links' => $socialLinks, 'variant' => 'compact'])
                        @endif
                    @elseif($block === 'image')
                        {{-- Slot kampanye "Atas artikel" selalu tepat sebelum gambar utama --}}
                        @isset($ads['top'])
                            @include('partials.promo-slot', ['ad' => $ads['top'], 'slot' => 'top', 'preview' => $adPreview])
                        @endisset
                        @if($show['image'])
                            @include('article.image')
                        @endif
                    @elseif($block === 'summary' && $show['summary'])
                        @include('article.summary')
                    @elseif($block === 'toc' && $show['toc'])
                        @include('article.toc')
                    @endif
                @endforeach

                @if($show['content'])
                    @include('article.content')
                @endif

                @isset($ads['bottom'])
                    @include('partials.promo-slot', ['ad' => $ads['bottom'], 'slot' => 'bottom', 'preview' => $adPreview])
                @endisset

                @if(in_array('end', $socialPositions, true))
                    @include('article.share', ['links' => $socialLinks, 'variant' => 'buttons'])
                @endif

                @if($show['tags'])
                    @include('article.tags')
                @endif
                @if($show['author_box'])
                    @include('article.author-box')
                @endif
                @if($show['faq'])
                    @include('article.faq')
                @endif

                {{-- Mobile (layout bersidebar): widget sidebar pindah ke bawah isi --}}
                @if($settings['has_sidebar'])
                    <div class="mt-10 space-y-8 lg:hidden">
                        @include('article.sidebar-stack')
                    </div>
                @endif
            </div>

            @if($settings['has_sidebar'])
                {{-- ==================== SIDEBAR (DESKTOP) ==================== --}}
                <aside class="hidden lg:block space-y-8 sticky top-24 {{ $layout === 'sidebar_left' ? 'lg:order-1' : '' }}">
                    @include('article.sidebar-stack')
                </aside>
            @else
                {{-- Layout standar: tanpa kolom samping; widget yang masih aktif tampil di bawah isi --}}
                <div class="mx-auto grid w-full max-w-[var(--content)] gap-8 md:grid-cols-2">
                    @include('article.sidebar-stack')
                </div>
            @endif
        </div>

        @if($show['related_products'])
            @include('article.related-products')
        @endif
        @if($show['related_articles'])
            @include('article.related-articles')
        @endif
    </article>
</x-layouts.app>
