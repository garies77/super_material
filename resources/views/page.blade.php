@php
    $pageImage = $page->seoImage() ?: \App\Models\Setting::defaultOgImage();
    // Pratinjau mengirim $blocks berisi draf; halaman publik memakai versi terbit
    $blocks ??= $page->blocks;
@endphp
<x-layouts.app :title="($page->meta_title ?: $page->title).' | '.config('app.name')"
               :description="$page->meta_description ?: $page->seoFallbackDescription()"
               :canonical="$page->canonical_url ?: url($page->slug)"
               :image="$pageImage"
               :image-alt="$page->title"
               :noindex="($preview ?? false) || ($page->noindex ?? false)"
               :nofollow="$page->nofollow ?? false">
    <div class="container-x pt-8">
        <x-breadcrumb :items="[[$page->title, null]]" />
        <article class="mx-auto max-w-3xl">
            <h1 class="font-display text-3xl font-extrabold sm:text-4xl">{{ $page->title }}</h1>
            <div class="mt-6">
                @if(! empty($blocks))
                    @include('partials.page-blocks', ['blocks' => $blocks])
                @else
                    <div class="content">{!! $page->content !!}</div>
                @endif
            </div>
            @include('partials.custom-code', ['model' => $page])
        </article>
    </div>
</x-layouts.app>
