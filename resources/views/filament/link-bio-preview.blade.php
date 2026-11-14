{{--
    Pratinjau ponsel Link Bio (App\Filament\Support\LinkBioPreview). Halaman dirender di iframe srcdoc agar CSS situs
    terpisah dari CSS admin. Iframe selalu selebar ponsel asli (390 px) lalu diperkecil agar pas di bingkai.

    Ukuran dihitung JS dari tinggi yang tersedia (rasio HP ±9:19) dan HANYA ditulis sebagai variabel CSS di pembungkus
    (--lb-w, --lb-h, --lb-s). wire:ignore.self menjaga atribut pembungkus tidak direset Livewire saat pratinjau diperbarui;
    isi di dalamnya (iframe baru tiap isi berubah, lewat wire:key) tetap diperbarui dan cukup membaca variabel itu.
--}}
<div
    class="sm-lb-preview"
    wire:ignore.self
    x-data
    x-init="
        const fit = () => {
            const wide = window.matchMedia('(min-width: 80rem)').matches
            const available = wide ? $el.clientHeight - 32 : 680
            const h = Math.max(420, Math.min(available, 820))
            const w = Math.round(h * 0.47)
            $el.style.setProperty('--lb-h', h + 'px')
            $el.style.setProperty('--lb-w', w + 'px')
            $el.style.setProperty('--lb-s', ((w - 16) / 390).toFixed(4))
        }
        fit()
        new ResizeObserver(fit).observe($el)
    "
    style="--lb-w:310px;--lb-h:660px;--lb-s:0.7538;display:flex;flex-direction:column;align-items:center;gap:.5rem"
>
    <div style="flex:none;width:var(--lb-w);height:var(--lb-h);box-sizing:border-box;border-radius:2.5rem;padding:8px;background:#111827;box-shadow:0 24px 48px -16px rgba(0,0,0,.45)">
        <div style="position:relative;width:100%;height:100%;overflow:hidden;border-radius:2rem;background:#fff">
            <iframe
                wire:key="lb-preview-{{ md5($html) }}"
                srcdoc="{{ $html }}"
                title="Pratinjau halaman Link Bio"
                sandbox="allow-same-origin"
                style="position:absolute;top:0;left:0;width:390px;height:calc((var(--lb-h) - 16px) / var(--lb-s));border:0;transform:scale(var(--lb-s));transform-origin:0 0"
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
