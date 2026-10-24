{{--
    Bar media sosial artikel dari Social Media Builder. Variabel: $links (ArticleSettings::socialLinks),
    $social (konfigurasi), $variant: compact (ikon) | buttons (akhir artikel) | sidebar.
    Tombol "Bagikan" memakai URL artikel; tombol profil menuju akun resmi.
--}}
@if($links || $social['show_copy'])
    @php $label = $social['label'] ?: 'Bagikan'; @endphp
    @if($variant === 'buttons')
        <div class="mt-8 border-t border-b border-ink-100 py-4 flex flex-wrap items-center justify-between gap-3" data-article-social="end">
            <span class="text-xs font-bold text-ink-500 uppercase tracking-wider">{{ $label }}:</span>
            <div class="flex flex-wrap items-center gap-2">
                @foreach($links as $link)
                    <a href="{{ $link['href'] }}" target="_blank" rel="noopener noreferrer"
                       style="background-color: {{ $link['color'] }}"
                       class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:opacity-90"
                       aria-label="{{ $link['type'] === 'share' ? 'Bagikan ke '.$link['label'] : 'Ikuti kami di '.$link['label'] }}">
                        @include('partials.social-icon', ['platform' => $link['platform'], 'class' => 'h-4 w-4'])
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach
                @if($social['show_copy'])
                    <button type="button" data-copy-link="{{ $article->url() }}"
                            class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-white px-4 py-2 text-xs font-bold text-ink-700 transition hover:border-brand-500 hover:text-brand-600">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                        <span>Salin tautan</span>
                    </button>
                @endif
            </div>
        </div>
    @else
        <div class="{{ $variant === 'sidebar' ? 'rounded-2xl border border-ink-100 bg-white p-5 shadow-sm' : 'mt-4 mb-6 py-1' }}" data-article-social="{{ $variant }}">
            <div class="flex flex-wrap items-center gap-2 text-xs font-bold text-ink-500 uppercase tracking-wider">
                <span class="{{ $variant === 'sidebar' ? 'w-full mb-1' : '' }}">{{ $label }}:</span>
                @if($social['show_copy'])
                    <span class="relative inline-block">
                        <button type="button" data-copy-link="{{ $article->url() }}"
                                class="flex h-9 items-center gap-1.5 rounded-full border border-ink-200 bg-white px-3 text-xs font-semibold normal-case tracking-normal text-ink-700 shadow-xs transition hover:border-brand-500 hover:text-brand-600 hover:bg-brand-50/50"
                                title="Salin Tautan">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                            <span>Salin</span>
                        </button>
                        <span data-copy-tooltip class="hidden absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-ink-950 px-2 py-1 text-[11px] font-medium normal-case tracking-normal text-white shadow-md z-20">URL berhasil disalin!</span>
                    </span>
                @endif
                @foreach($links as $link)
                    <a href="{{ $link['href'] }}" target="_blank" rel="noopener noreferrer"
                       style="color: {{ $link['color'] }}"
                       class="flex h-9 w-9 items-center justify-center rounded-full bg-ink-100 transition hover:bg-ink-900 hover:!text-white"
                       title="{{ $link['type'] === 'share' ? 'Bagikan ke '.$link['label'] : 'Ikuti kami di '.$link['label'] }}"
                       aria-label="{{ $link['type'] === 'share' ? 'Bagikan ke '.$link['label'] : 'Ikuti kami di '.$link['label'] }}">
                        @include('partials.social-icon', ['platform' => $link['platform'], 'class' => 'h-4 w-4'])
                    </a>
                @endforeach
            </div>
        </div>
    @endif
@endif
