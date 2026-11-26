{{--
    Blok Custom HTML, dirender langsung di halaman (tanpa iframe) agar skrip SEO/pelacakan bisa membaca dan
    mengubah halaman: JSON-LD, meta tag, dataLayer, pixel. Yang boleh mengisi hanya Admin atau pengguna yang diberi
    izin (User::canManageCustomHtml, ditegakkan di model). CSP tetap aktif: setiap <script> diberi nonce request ini,
    sedangkan atribut onclick= dan sejenisnya tetap diblokir (script-src-attr 'none').
    Dipanggil dengan $model (kolom custom_html/css/js), atau dengan $customCode ['html', 'css', 'js'] + $customScope
    untuk kode yang tidak disimpan di model, misalnya seksi HTML Custom di beranda.
--}}
@php
    $customCode ??= ['html' => $model->custom_html, 'css' => $model->custom_css, 'js' => $model->custom_js];
    $customScope ??= 'cc-'.\Illuminate\Support\Str::kebab(class_basename($model)).'-'.$model->getKey();
    $customWrapperClass ??= 'custom-html mt-8';
    $nonce = Vite::cspNonce();
    // Cegah CSS menutup tag <style> dan menyisipkan markup
    $css = str_ireplace('</style', '', (string) ($customCode['css'] ?? ''));
    // Nonce lama/salah dari kode yang ditempel diganti dengan nonce request ini
    $html = preg_replace_callback('/<script\b([^>]*)>/i', fn ($m) => '<script nonce="'.$nonce.'"'.preg_replace('/\snonce\s*=\s*("[^"]*"|\'[^\']*\'|\S+)/i', '', $m[1]).'>', (string) ($customCode['html'] ?? ''));
    // Cegah JS menutup tag <script> lebih awal
    $js = str_ireplace('</script', '<\/script', (string) ($customCode['js'] ?? ''));
@endphp

@if(filled($css))
    <style>#{{ $customScope }}{ {!! $css !!} }</style>
@endif
@if(filled($html))
    <div id="{{ $customScope }}" class="{{ $customWrapperClass }}">{!! $html !!}</div>
@endif
@if(filled($js))
    <script nonce="{{ $nonce }}">{!! $js !!}</script>
@endif
