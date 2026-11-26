{{-- Seksi HTML Custom di beranda: kode dari Kustomisasi Beranda (khusus Admin), dirender lewat partial kode custom bersama. --}}
@include('partials.custom-code', [
    'customCode' => [
        'html' => $section->setting('html'),
        'css' => $section->setting('css'),
        'js' => $section->setting('js'),
    ],
    'customScope' => 'cc-home-'.\Illuminate\Support\Str::slug($section->key),
    'customWrapperClass' => 'custom-html mt-20'.($section->setting('full_width') ? '' : ' container-x'),
])
