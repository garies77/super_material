{{--
    Render blok page builder untuk halaman publik dan pratinjau. Variabel: $blocks.
    HTML dari editor dan blok HTML khusus selalu disanitasi; gambar tidak pernah melebihi lebar kontainer.
--}}
@php
    $alignText = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'];
    $alignFlex = ['left' => 'justify-start', 'center' => 'justify-center', 'right' => 'justify-end'];
    $clean = fn ($html) => \App\Support\Api\HtmlSanitizer::clean($html);
@endphp
<div class="page-blocks space-y-6">
@foreach($blocks as $block)
    @php $d = $block['data'] ?? []; @endphp
    @php $type = $block['type'] ?? null; @endphp
        @if($type === 'heading')
            @php $tag = in_array($d['level'] ?? 'h2', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $d['level'] : 'h2'; @endphp
            <{{ $tag }} class="{{ $alignText[$d['align'] ?? 'left'] ?? 'text-left' }}">{{ $d['text'] ?? '' }}</{{ $tag }}>

        @elseif($type === 'text')
            <div class="content">{!! $clean($d['content'] ?? '') !!}</div>

        @elseif($type === 'image')
            @php
                // Lebar manual (opsional) menimpa hasil tarik; nilai sudah divalidasi angka sehingga aman di atribut style
                $width = (int) ($d['width'] ?? 0);
                $unit = ($d['width_unit'] ?? '%') === 'px' ? 'px' : '%';
                $ratio = preg_match('#^\d+/\d+$#', $d['ratio'] ?? '') ? $d['ratio'] : null;
                $vars = collect([
                    $width > 0 ? '--w:'.min($width, $unit === '%' ? 100 : 2000).$unit : null,
                    $ratio ? '--ratio:'.$ratio : null,
                ])->filter()->implode(';');
            @endphp
            <figure class="page-image content" @if($vars) style="{{ $vars }}" @endif>
                {!! $clean($d['image'] ?? '') !!}
                @if(filled($d['caption'] ?? null))
                    <figcaption class="-mt-3 text-center text-sm text-ink-500">{{ $d['caption'] }}</figcaption>
                @endif
            </figure>

        @elseif($type === 'gallery')
            @php $cols = ['2' => 'sm:grid-cols-2', '3' => 'sm:grid-cols-3', '4' => 'sm:grid-cols-2 lg:grid-cols-4'][(string) ($d['columns'] ?? 3)] ?? 'sm:grid-cols-3'; @endphp
            <div class="grid grid-cols-2 gap-3 {{ $cols }}">
                @foreach((array) ($d['images'] ?? []) as $path)
                    <img src="{{ asset('storage/'.ltrim($path, '/')) }}" alt="{{ $d['alt'] ?? '' }}" loading="lazy"
                         class="aspect-square w-full rounded-xl object-cover">
                @endforeach
            </div>

        @elseif($type === 'button')
            @php $href = \App\Support\LinkTarget::url($d); @endphp
            @if($href)
                <div class="flex {{ $alignFlex[$d['align'] ?? 'left'] ?? 'justify-start' }}">
                    <a href="{{ $href }}" @if(! empty($d['new_tab'])) target="_blank" rel="noopener" @endif
                       class="btn {{ ['primary' => 'btn-primary', 'dark' => 'bg-ink-900 text-white hover:bg-ink-800', 'outline' => 'border-2 border-ink-900 text-ink-900 hover:bg-ink-900 hover:text-white'][$d['style'] ?? 'primary'] ?? 'btn-primary' }}">{{ $d['label'] ?? '' }}</a>
                </div>
            @endif

        @elseif($type === 'list')
            @php $items = collect((array) ($d['items'] ?? []))->map(fn ($i) => is_array($i) ? ($i['text'] ?? '') : $i)->filter(fn ($i) => filled($i))->all(); @endphp
            <div class="content">
                @if(($d['style'] ?? 'bullet') === 'number')
                    <ol>@foreach($items as $item)<li>{{ $item }}</li>@endforeach</ol>
                @else
                    <ul>@foreach($items as $item)<li>{{ $item }}</li>@endforeach</ul>
                @endif
            </div>

        @elseif($type === 'divider')
            <hr class="border-ink-200 {{ ['sm' => 'my-2', 'md' => 'my-6', 'lg' => 'my-12'][$d['spacing'] ?? 'md'] ?? 'my-6' }}">

        @elseif($type === 'spacer')
            <div aria-hidden="true" style="height:{{ max(4, min(400, (int) ($d['height'] ?? 40))) }}px"></div>

        @elseif($type === 'embed')
            @if(preg_match('#(?:v=|youtu\.be/|shorts/|embed/)([\w-]{11})#', $d['url'] ?? '', $m))
                <div class="aspect-video overflow-hidden rounded-xl bg-ink-100">
                    <iframe src="https://www.youtube-nocookie.com/embed/{{ $m[1] }}" title="{{ $d['title'] ?? 'Video' }}" loading="lazy"
                            class="h-full w-full border-0" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen
                            referrerpolicy="strict-origin-when-cross-origin"></iframe>
                </div>
            @endif

        @elseif($type === 'form')
            <x-custom-form :id="$d['form_id'] ?? 0" :show-title="(bool) ($d['show_title'] ?? true)" class="not-prose rounded-2xl border border-ink-100 bg-white p-5 sm:p-8" />

        @elseif($type === 'html')
            <div class="custom-html">{!! \App\Support\Api\HtmlSanitizer::clean($d['html'] ?? '') !!}</div>
    @endif
@endforeach
</div>
