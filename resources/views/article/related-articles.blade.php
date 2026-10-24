        {{-- ==================== BAGIAN BAWAH: ARTIKEL TERKAIT & REKOMENDASI ==================== --}}
        @if($related->isNotEmpty() || $recommendations->isNotEmpty())
            <section class="mt-14 border-t border-ink-200 pt-10">
                <div class="mb-6">
                    <span class="text-xs font-black uppercase tracking-wider text-brand-600">Baca Selanjutnya</span>
                    <h2 class="font-display text-2xl font-black text-ink-950">Artikel Terkait & Rekomendasi Untukmu</h2>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($related->concat($recommendations)->unique('id')->take(4) as $item)
                        <x-article-card :article="$item" />
                    @endforeach
                </div>
            </section>
        @endif
