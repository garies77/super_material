{{-- 404 dengan layout situs: navigasi, pencarian, dan footer tetap ada agar pengunjung tidak buntu. --}}
<x-layouts.app :title="'Halaman tidak ditemukan | '.\App\Models\Setting::brandName()" :noindex="true">
    <div class="container-x py-16 sm:py-24">
        <div class="mx-auto max-w-xl text-center">
            <p class="text-sm font-semibold text-brand-700">404</p>
            <h1 class="mt-2 font-display text-3xl font-extrabold text-ink-950 sm:text-4xl">Halaman tidak ditemukan</h1>
            <p class="mt-4 leading-relaxed text-ink-600">
                Alamat yang Anda buka tidak ada atau sudah dipindahkan. Coba cari produk atau artikel yang Anda butuhkan.
            </p>
            <form action="{{ route('search') }}" method="get" role="search" class="mx-auto mt-8 flex max-w-md gap-2">
                <label for="q-404" class="sr-only">Kata kunci</label>
                <input id="q-404" name="q" type="search" placeholder="Cari semen, keramik, baja ringan…"
                       class="min-w-0 flex-1 rounded-xl border border-ink-200 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                <button type="submit" class="btn btn-primary">Cari</button>
            </form>
            <div class="mt-6 flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm font-semibold">
                <a href="{{ url('/') }}" class="text-brand-700 hover:underline">Beranda</a>
                <a href="{{ route('product.index') }}" class="text-brand-700 hover:underline">Katalog produk</a>
                <a href="{{ route('article.index') }}" class="text-brand-700 hover:underline">Artikel &amp; panduan</a>
            </div>
        </div>
    </div>
</x-layouts.app>
