{{--
    Pratinjau ponsel Link Bio (App\Filament\Support\LinkBioPreview). Halaman dirender di iframe srcdoc agar CSS situs
    terpisah dari CSS admin. wire:key berubah setiap isi berubah supaya iframe dimuat ulang.
--}}
<div class="sm-lb-preview" style="display:flex;flex-direction:column;align-items:center;gap:.75rem">
    <div class="sm-lb-phone" style="width:100%;max-width:340px;aspect-ratio:9/18.5;border-radius:2.5rem;padding:10px;background:#111827;box-shadow:0 20px 40px -12px rgba(0,0,0,.35)">
        <iframe
            wire:key="lb-preview-{{ md5($html) }}"
            srcdoc="{{ $html }}"
            title="Pratinjau halaman Link Bio"
            sandbox="allow-same-origin"
            loading="eager"
            style="width:100%;height:100%;border:0;border-radius:2rem;background:#fff"
        ></iframe>
    </div>
    <p style="font-size:.75rem;opacity:.7;text-align:center;max-width:340px">
        Pratinjau berubah saat Anda mengedit; perubahan tersimpan setelah klik Simpan.
        @if($url)
            <br><a href="{{ $url }}" target="_blank" rel="noopener" style="text-decoration:underline">Buka halaman asli</a>
        @endif
    </p>
</div>
