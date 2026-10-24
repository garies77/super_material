{{-- Footer dari Footer Builder. Variabel: $footer (SiteLayout::footer()), $siteName, $menuCategories. --}}
@php
    $columns = $footer['columns'];
    // Tanpa kolom "Informasi kontak", kontak dan media sosial tampil di kolom identitas agar tidak hilang
    $contactInBrand = ! $footer['has_contact_column'] && (filled($footer['phone']) || filled($footer['email']) || $footer['socials']);
    $hasBrand = $footer['show_logo'] || filled($footer['description']) || $contactInBrand;
    $grid = ['1' => 'md:grid-cols-1', '2' => 'md:grid-cols-2', '3' => 'md:grid-cols-3', '4' => 'md:grid-cols-4', '5' => 'md:grid-cols-3 lg:grid-cols-5', '6' => 'md:grid-cols-3 lg:grid-cols-6'];
    $span = count($columns) + ($hasBrand ? 2 : 0);
    $target = fn (array $item) => ! empty($item['new_tab']) ? 'target="_blank" rel="noopener"' : '';
@endphp
<footer class="mt-20 bg-ink-950 text-ink-200">
    <div class="container-x grid gap-10 py-14 {{ $grid[(string) max(1, min(6, $span))] }}">
        @if($hasBrand)
            <div class="md:col-span-2">
                @if($footer['show_logo'])
                    <div class="flex items-center gap-2.5">
                        @if($siteLogo = \App\Models\Setting::logoUrl())
                            <img src="{{ $siteLogo }}" alt="{{ $siteName }}" @if($logoSize = \App\Support\ImageDerivatives::dimensions($siteLogo)) width="{{ (int) round(36 * $logoSize[0] / max(1, $logoSize[1])) }}" height="36" @endif loading="lazy" class="h-9 w-auto max-h-9 max-w-[180px] object-contain">
                        @endif
                        <p class="font-display text-2xl font-bold text-white">{!! \App\Models\Setting::brandHtml('text-brand-400') !!}</p>
                    </div>
                @endif
                <p class="mt-3 max-w-md text-sm leading-7 text-ink-400">{{ $footer['description'] ?: \App\Models\Setting::get('site_tagline', 'Katalog bahan bangunan lengkap. Bandingkan, pilih, lalu beli lewat marketplace favorit Anda atau konsultasi langsung via WhatsApp.') }}</p>
                @if(filled($footer['address']))<p class="mt-4 text-sm text-ink-400">{{ $footer['address'] }}</p>@endif
                @if($contactInBrand)
                    <div class="mt-4">@include('partials.site-footer-contact', ['footer' => $footer, 'withAddress' => false])</div>
                @endif
            </div>
        @endif

        @foreach($columns as $column)
            <div>
                <p class="mb-3 text-sm font-semibold text-white">{{ $column['title'] }}</p>
                @php $ctype = $column['type'] ?? 'links'; @endphp
                    @if($ctype === 'categories')
                        <ul class="space-y-2 text-sm">
                            @foreach($menuCategories as $c)<li><a class="transition hover:text-brand-300" href="{{ $c->url() }}">{{ $c->name }}</a></li>@endforeach
                        </ul>
                    @elseif($ctype === 'pages')
                        <ul class="space-y-2 text-sm">
                            <li><a class="transition hover:text-brand-300" href="{{ route('article.index') }}">Artikel &amp; panduan</a></li>
                            @foreach(\App\Models\Page::published()->orderBy('title')->get(['title', 'slug']) as $p)
                                <li><a class="transition hover:text-brand-300" href="{{ route('page.show', $p->slug) }}">{{ $p->title }}</a></li>
                            @endforeach
                        </ul>
                    @elseif($ctype === 'contact')
                        @include('partials.site-footer-contact', ['footer' => $footer, 'withAddress' => true])
                    @elseif($ctype === 'form')
                        <x-custom-form :id="$column['form_id'] ?? 0" :show-title="false" variant="dark" />
                    @elseif($ctype === 'text')
                        <p class="whitespace-pre-line text-sm leading-7 text-ink-400">{{ $column['text'] ?? '' }}</p>
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach($column['links'] as $link)
                                <li><a class="transition hover:text-brand-300" href="{{ $link['href'] }}" {!! $target($link) !!}>{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                @endif
            </div>
        @endforeach
    </div>
    <div data-nosnippet class="border-t border-white/10 py-5 text-xs text-ink-400">
        <div class="container-x flex flex-wrap items-center gap-x-5 gap-y-2 {{ ($footer['bottom_align'] ?? 'center') === 'left' ? 'justify-between' : 'justify-center text-center' }}">
            <span>{{ $footer['copyright'] }}</span>
            @if($footer['bottom_links'])
                <span class="flex flex-wrap gap-4">
                    @foreach($footer['bottom_links'] as $link)
                        <a class="transition hover:text-brand-300" href="{{ $link['href'] }}" {!! $target($link) !!}>{{ $link['label'] }}</a>
                    @endforeach
                </span>
            @endif
        </div>
    </div>
</footer>
