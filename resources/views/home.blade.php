{{-- Beranda: orkestrator seksi aktif dari menu Kustomisasi Beranda (urutan & visibilitas di tabel home_sections). --}}
@php
    use App\Models\Setting;
    $siteBrand = Setting::brandName();
    $homeImage = $heroBanner['image'] ?? Setting::defaultOgImage();
    $hasHero = $sections->contains(fn ($s) => $s['section']->type === 'hero');
    $prevType = null;
@endphp
<x-layouts.app :canonical="url('/')"
               :image="$homeImage"
               :image-alt="$siteBrand . ' — ' . Setting::get('site_tagline', 'Katalog Bahan Bangunan')"
               :image-width="1200"
               :image-height="630">

    @unless($hasHero)
        {{-- Hero dimatikan: H1 tetap ada untuk mesin pencari --}}
        <h1 class="sr-only">{{ $siteBrand }} — {{ Setting::get('site_tagline', 'Katalog Bahan Bangunan') }}</h1>
    @endunless

    @foreach($sections as $item)
        @include($item['section']->view(), [
            'section' => $item['section'],
            'data' => $item['data'],
            'overlap' => $prevType === 'hero',
        ])
        @php $prevType = $item['section']->type; @endphp
    @endforeach
</x-layouts.app>
