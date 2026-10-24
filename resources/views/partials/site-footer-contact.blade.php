{{-- Kontak dan media sosial footer. Variabel: $footer, $withAddress. --}}
<ul class="space-y-2 text-sm text-ink-400">
    @if($withAddress && filled($footer['address']))<li>{{ $footer['address'] }}</li>@endif
    @if(filled($footer['phone']))<li><a class="transition hover:text-brand-300" href="tel:{{ preg_replace('/[^\d+]/', '', $footer['phone']) }}">{{ $footer['phone'] }}</a></li>@endif
    @if(filled($footer['email']))<li><a class="transition hover:text-brand-300" href="mailto:{{ $footer['email'] }}">{{ $footer['email'] }}</a></li>@endif
</ul>
@if($footer['socials'])
    <div class="mt-4 flex flex-wrap gap-2">
        @foreach($footer['socials'] as $social)
            <a href="{{ $social['url'] }}" target="_blank" rel="noopener" aria-label="{{ \App\Support\SiteLayout::SOCIALS[$social['platform']] }}" title="{{ \App\Support\SiteLayout::SOCIALS[$social['platform']] }}"
               class="grid h-9 w-9 place-items-center rounded-full border border-white/15 transition hover:border-brand-400 hover:text-brand-300">
                @include('partials.social-icon', ['platform' => $social['platform'], 'class' => 'h-4 w-4'])
            </a>
        @endforeach
    </div>
@endif
