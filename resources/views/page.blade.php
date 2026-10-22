@php
    $pageImage = $page->seoImage() ?: \App\Models\Setting::defaultOgImage();
@endphp
<x-layouts.app :title="($page->meta_title ?: $page->title).' | '.config('app.name')"
               :description="$page->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($page->content), 155)"
               :canonical="$page->canonical_url ?: url($page->slug)"
               :image="$pageImage"
               :image-alt="$page->title"
               :noindex="$page->noindex ?? false"
               :nofollow="$page->nofollow ?? false">
    <div class="container-x pt-8">
        <x-breadcrumb :items="[[$page->title, null]]" />
        <article class="mx-auto max-w-3xl">
            <h1 class="font-display text-3xl font-extrabold sm:text-4xl">{{ $page->title }}</h1>
            <div class="content mt-6">{!! $page->content !!}</div>
        </article>
    </div>
</x-layouts.app>
