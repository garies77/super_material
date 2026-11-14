{{--
    Pratinjau ponsel Link Bio (App\Filament\Support\LinkBioPreview). Halaman dirender di iframe srcdoc agar CSS situs
    terpisah dari CSS admin. Iframe selalu selebar ponsel asli (390 px) lalu diperkecil agar pas di bingkai,
    jadi tata letaknya sama dengan di HP. wire:key berubah setiap isi berubah supaya iframe dimuat ulang.
--}}
<div class="sm-lb-preview" style="display:flex;flex-direction:column;align-items:center;gap:.5rem">
    <div class="sm-lb-phone" style="width:100%;max-width:340px;aspect-ratio:9/18;border-radius:2.25rem;padding:9px;background:#111827;box-shadow:0 20px 40px -12px rgba(0,0,0,.35)">
        <div
            x-data="{ scale: 1, height: 780 }"
            x-init="new ResizeObserver(() => { scale = $el.clientWidth / 390; height = $el.clientHeight / scale }).observe($el)"
            style="position:relative;width:100%;height:100%;overflow:hidden;border-radius:1.75rem;background:#fff"
        >
            <iframe
                wire:key="lb-preview-{{ md5($html) }}"
                srcdoc="{{ $html }}"
                title="Pratinjau halaman Link Bio"
                sandbox="allow-same-origin"
                x-bind:style="`height:${height}px;transform:scale(${scale})`"
                style="position:absolute;top:0;left:0;width:390px;height:780px;border:0;transform-origin:0 0"
            ></iframe>
        </div>
    </div>
    <p style="font-size:.75rem;opacity:.7;text-align:center">
        Berubah saat Anda mengedit; tersimpan setelah klik Simpan.
        @if($url)
            <a href="{{ $url }}" target="_blank" rel="noopener" style="text-decoration:underline">Buka halaman</a>
        @endif
    </p>
</div>
