{{--
    Kampanye pop-up (format Banner, tampil sebagai pop-up). Kandidat dirender di dalam <template> sehingga gambar tidak
    diunduh dan tidak memengaruhi LCP; JS memilih kandidat pertama yang belum dibatasi frekuensi,
    lalu membukanya setelah jeda atau gulir. data-nosnippet sesuai PRD 9.11.4.
    Nama kelas/atribut sengaja netral ("sm-campaign") agar tidak disembunyikan ad blocker.
--}}
@php
    $smPreview = $smCampaignPreview ?? null;
    $smCampaigns = $smPreview ? collect([$smPreview]) : \App\Models\Ad::popupsForRequest(request());
@endphp
@if($smCampaigns->isNotEmpty())
    <div data-nosnippet data-sm-campaign-root data-sm-campaign-token="{{ csrf_token() }}" @if($smPreview) data-sm-campaign-preview @endif hidden>
        @foreach($smCampaigns as $c)
            @php
                $href = $c->ctaUrl();
                // rel="sponsored" hanya untuk materi mitra berbayar, sama dengan banner dan sidebar
                $linkAttrs = $c->isExternal() ? 'target="_blank" rel="'.($c->label === 'Sponsor' ? 'sponsored nofollow noopener' : 'nofollow noopener').'"' : '';
                $titleId = 'sm-campaign-title-'.$c->id;
                $withText = $c->format === 'text';
            @endphp
            <template data-sm-campaign='@json($c->clientConfig())' data-sm-campaign-event="{{ route('ad.event', $c) }}" data-sm-campaign-utm="{{ $c->utmCampaign() }}">
                <div class="sm-campaign fixed inset-0 z-[60] flex items-center justify-center p-4" data-nosnippet>
                    <div data-sm-campaign-dismiss class="sm-campaign-backdrop absolute inset-0 bg-ink-950/70"></div>
                    <div role="dialog" aria-modal="true" aria-labelledby="{{ $titleId }}" tabindex="-1"
                         class="sm-campaign-panel relative flex max-h-[calc(100dvh-2rem)] w-full max-w-[min(92vw,34rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl outline-none">
                        <button type="button" data-sm-campaign-dismiss aria-label="Tutup"
                                class="absolute right-2 top-2 z-10 grid h-11 w-11 place-items-center rounded-full bg-white/90 text-ink-800 shadow ring-1 ring-ink-900/10 transition hover:bg-white hover:text-ink-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-500">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>

                        <a href="{{ $href }}" {!! $linkAttrs !!} data-sm-campaign-cta class="block min-h-0 shrink bg-ink-50" @if($withText) tabindex="-1" @endif>
                            <picture>
                                @if($c->mobileImageUrl())
                                    <source media="(max-width: 639px)" srcset="{{ $c->mobileImageUrl() }}">
                                @endif
                                <img src="{{ $c->imageUrl() }}" alt="{{ $c->image_alt }}" decoding="async"
                                     width="1200" height="750"
                                     class="h-auto max-h-[60dvh] w-full object-cover">
                            </picture>
                        </a>

                        @if($withText)
                            <div class="p-5 sm:p-6">
                                <h2 id="{{ $titleId }}" class="font-display text-xl font-bold leading-snug text-ink-900 sm:text-2xl">{{ $c->title }}</h2>
                                @if($c->body)
                                    <p class="mt-2 text-sm leading-6 text-ink-600 sm:text-base">{{ $c->body }}</p>
                                @endif
                                <a href="{{ $href }}" {!! $linkAttrs !!} data-sm-campaign-cta class="btn btn-primary mt-5 w-full justify-center">{{ $c->cta_label ?: 'Lihat promo' }}</a>
                            </div>
                        @else
                            <h2 id="{{ $titleId }}" class="sr-only">{{ $c->title ?: $c->image_alt }}</h2>
                        @endif

                        @if($c->allow_never_again)
                            <button type="button" data-sm-campaign-never class="border-t border-ink-100 px-4 py-3 text-xs font-semibold text-ink-500 transition hover:bg-ink-50 hover:text-ink-800">Jangan tampilkan lagi</button>
                        @endif
                    </div>
                </div>
            </template>
        @endforeach
    </div>
@endif
