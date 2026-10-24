        {{-- ==================== BAGIAN BAWAH: PRODUK TERKAIT ==================== --}}
        @if($products->isNotEmpty())
            <section class="mt-14 border-t border-ink-200 pt-10">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <span class="text-xs font-black uppercase tracking-wider text-brand-600">Katalog Rekomendasi</span>
                        <h2 class="font-display text-2xl font-black text-ink-950">Produk yang Dibahas dalam Artikel</h2>
                    </div>
                    <a href="{{ route('product.index') }}" class="hidden sm:inline-flex items-center gap-1 text-xs font-bold text-brand-700 hover:underline">
                        <span>Lihat Semua Produk</span>
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif
