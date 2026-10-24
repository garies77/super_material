                {{-- Widget 3: Kategori Artikel --}}
                @if($article->categories->isNotEmpty())
                    <div class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm">
                        <h3 class="font-display text-sm font-black uppercase tracking-tight text-ink-900 mb-3">
                            Kategori Terkait
                        </h3>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($article->categories as $c)
                                <a href="{{ route('article.category', $c->slug) }}"
                                   class="inline-block rounded-lg bg-ink-50 px-2.5 py-1 text-xs font-medium text-ink-700 hover:bg-brand-50 hover:text-brand-700 transition">
                                    {{ $c->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
