{{-- Widget sidebar artikel sesuai urutan dan visibilitas di Pengaturan Artikel. Variabel: $settings, $socialPositions, $socialLinks, $social. --}}
@if(in_array('sidebar', $socialPositions, true))
    @include('article.share', ['links' => $socialLinks, 'social' => $social, 'variant' => 'sidebar'])
@endif
@foreach($settings['sidebar_order'] as $widget)
    @continue(! $settings['show_widgets'][$widget])
    @if($widget === 'campaign')
        @isset($ads['sidebar'])
            @include('partials.promo-slot', ['ad' => $ads['sidebar'], 'slot' => 'sidebar', 'preview' => $adPreview, 'class' => 'mx-auto w-full max-w-[340px]'])
        @endisset
    @elseif($widget === 'popular')
        @include('article.widget-popular')
    @elseif($widget === 'consult')
        @include('article.widget-consult')
    @elseif($widget === 'categories')
        @include('article.widget-categories')
    @endif
@endforeach
