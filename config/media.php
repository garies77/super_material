<?php

/*
| Ukuran baku gambar per section. Unggahan baru dipotong ke rasio ini (editor terbuka otomatis
| untuk field satu gambar, potong tengah otomatis untuk galeri) lalu diperkecil ke resolusi target
| di browser. Gambar yang dipilih dari Pustaka Media dengan rasio lain dibuatkan salinan terpotong.
|
| ratio   : satu rasio "lebar:tinggi" (wajib), daftar rasio (salah satu wajib), atau null (bebas)
| options : pilihan rasio di editor; default = ratio
| width/height : resolusi target; tidak memperbesar gambar yang lebih kecil
*/

return [

    'presets' => [
        'product' => ['label' => 'Produk', 'ratio' => '1:1', 'width' => 1200, 'height' => 1200],
        'article' => ['label' => 'Artikel', 'ratio' => '16:9', 'width' => 1200, 'height' => 675],
        'hero_card' => ['label' => 'Hero kartu', 'ratio' => '16:10', 'width' => 1200, 'height' => 750],
        'hero_background' => ['label' => 'Hero latar penuh', 'ratio' => '32:15', 'width' => 1920, 'height' => 900],
        'banner_desktop' => ['label' => 'Banner desktop', 'ratio' => '4:1', 'width' => 1200, 'height' => 300],
        'banner_mobile' => ['label' => 'Banner ponsel', 'ratio' => '4:3', 'width' => 800, 'height' => 600],
        'campaign_popup' => ['label' => 'Kampanye pop-up', 'ratio' => '16:10', 'width' => 1200, 'height' => 750],
        'campaign_slot' => ['label' => 'Kampanye slot iklan', 'ratio' => ['728:250', '300:250'], 'width' => null, 'height' => null],
        'og' => ['label' => 'Open Graph', 'ratio' => '1200:630', 'width' => 1200, 'height' => 630],
        'square_small' => ['label' => 'Ikon / foto profil', 'ratio' => '1:1', 'width' => 400, 'height' => 400],
        'page_gallery' => ['label' => 'Galeri halaman', 'ratio' => null, 'options' => ['1:1', '4:3', '16:9', null], 'width' => 1600, 'height' => null],
    ],

];
