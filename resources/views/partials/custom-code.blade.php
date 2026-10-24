{{--
    Blok Custom HTML, dirender langsung di halaman (tanpa iframe) agar skrip SEO/pelacakan bisa membaca dan
    mengubah halaman: JSON-LD, meta tag, dataLayer, pixel. Yang boleh mengisi hanya Admin atau pengguna yang diberi
    izin (User::canManageCustomHtml, ditegakkan di model). CSP tetap aktif: setiap <script> diberi nonce request ini,
    sedangkan atribut onclick= dan sejenisnya tetap diblokir (script-src-attr 'none').
--}}
@php
    $scope = 'cc-'.\Illuminate\Support\Str::kebab(class_basename($model)).'-'.$model->getKey();
    $nonce = Vite::cspNonce();
    // Cegah CSS menutup tag <style> dan menyisipkan markup
    $css = str_ireplace('</style', '', (string) $model->custom_css);
    // Nonce lama/salah dari kode yang ditempel diganti dengan nonce request ini
    $html = preg_replace_callback('/<script\b([^>]*)>/i', fn ($m) => '<script nonce="'.$nonce.'"'.preg_replace('/\snonce\s*=\s*("[^"]*"|\'[^\']*\'|\S+)/i', '', $m[1]).'>', (string) $model->custom_html);
    // Cegah JS menutup tag <script> lebih awal
    $js = str_ireplace('</script', '<\/script', (string) $model->custom_js);
@endphp

@if(filled($css))
    <style>#{{ $scope }}{ {!! $css !!} }</style>
@endif
@if(filled($html))
    <div id="{{ $scope }}" class="custom-html mt-8">{!! $html !!}</div>
@endif
@if(filled($js))
    <script nonce="{{ $nonce }}">{!! $js !!}</script>
@endif
