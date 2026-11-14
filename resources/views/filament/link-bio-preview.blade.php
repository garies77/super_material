{{--
    Pratinjau ponsel Link Bio (App\Filament\Support\LinkBioPreview). Halaman dirender di iframe srcdoc agar CSS situs
    terpisah dari CSS admin. Ukuran bingkai dihitung dari tinggi yang tersedia (rasio HP ±9:19); iframe selalu selebar
    ponsel asli (390 px) lalu diperkecil agar pas, jadi tata letaknya sama dengan di HP.
    wire:key berubah setiap isi berubah supaya iframe dimuat ulang.
--}}
<div
    class="sm-lb-preview"
    x-data="{
        w: 320, h: 660, s: 0.8,
        fit() {
            const available = window.matchMedia('(min-width: 80rem)').matches ? this.$el.clientHeight - 32 : 680
            this.h = Math.max(420, Math.min(available, 820))
            this.w = Math.round(this.h * 0.47)
            this.s = (this.w - 16) / 390
        },
    }"
    x-init="fit(); new ResizeObserver(() => fit()).observe($el); window.addEventListener('resize', () => fit())"
    style="display:flex;flex-direction:column;align-items:center;gap:.5rem"
>
    <div
        x-bind:style="`width:${w}px;height:${h}px`"
        style="width:320px;height:660px;flex:none;border-radius:2.5rem;padding:8px;background:#111827;box-shadow:0 24px 48px -16px rgba(0,0,0,.45)"
    >
        <div style="position:relative;width:100%;height:100%;overflow:hidden;border-radius:2rem;background:#fff">
            <iframe
                wire:key="lb-preview-{{ md5($html) }}"
                srcdoc="{{ $html }}"
                title="Pratinjau halaman Link Bio"
                sandbox="allow-same-origin"
                x-bind:style="`height:${(h - 16) / s}px;transform:scale(${s})`"
                style="position:absolute;top:0;left:0;width:390px;height:800px;border:0;transform-origin:0 0"
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
