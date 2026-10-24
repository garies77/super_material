{{--
    Kartu konsultasi WhatsApp untuk sidebar (daftar artikel & artikel tunggal), gaya sama dengan seksi CTA beranda.
    Variabel: $badge, $title, $description, $button, $message.
--}}
@php
    $wa = preg_replace('/\D+/', '', (string) \App\Models\Setting::get('whatsapp_number'));
    $wa = str_starts_with($wa, '0') ? '62'.substr($wa, 1) : $wa;
@endphp
<div data-nosnippet class="relative overflow-hidden rounded-2xl border border-white/15 bg-gradient-to-br from-brand-500 to-brand-700 p-6 text-white shadow-lg shadow-brand-700/20">
    <div class="hero-grid absolute inset-0 opacity-40"></div>
    <div class="pointer-events-none absolute -right-16 -top-16 h-44 w-44 rounded-full bg-white/15 blur-3xl"></div>

    <div class="relative">
        <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[11px] font-semibold text-white">
            <span class="relative flex h-2 w-2">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
            </span>
            {{ $badge }}
        </p>
        <h4 class="mt-3 font-display text-lg font-extrabold leading-tight text-white">{{ $title }}</h4>
        <p class="mt-2 text-xs leading-relaxed text-white/85">{{ $description }}</p>

        <div class="mt-4 flex items-center gap-3">
            <div class="flex -space-x-2.5" aria-hidden="true">
                @foreach([['A', 'bg-brand-400 text-ink-900'], ['R', 'bg-emerald-500 text-white'], ['D', 'bg-white text-brand-700']] as [$initial, $color])
                    <span class="grid h-8 w-8 place-items-center rounded-full {{ $color }} font-display text-xs font-bold ring-2 ring-brand-600">{{ $initial }}</span>
                @endforeach
            </div>
            <div class="min-w-0 text-xs">
                <p class="font-display font-bold text-white">Tim Ahli {{ \App\Models\Setting::brandName() }}</p>
                <p class="inline-flex items-center gap-1.5 text-white/85"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Online sekarang</p>
            </div>
        </div>

        @if($wa)
            <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($message) }}" target="_blank" rel="nofollow noopener"
               class="group mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-3 text-sm font-bold text-white shadow-lg shadow-[#25D366]/25 transition duration-200 hover:-translate-y-0.5 hover:bg-[#20ba5a] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413"/></svg>
                <span>{{ $button }}</span>
                <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        @endif
    </div>
</div>
