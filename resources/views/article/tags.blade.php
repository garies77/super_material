                {{-- 10. Topik Terkait (Tag Chip) --}}
                @if($article->tags->isNotEmpty())
                    <div class="mt-6">
                        <p class="text-xs font-black uppercase tracking-wider text-ink-400 mb-3">Topik Terkait</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($article->tags as $tag)
                                <a href="{{ route('article.tag', $tag->slug) }}"
                                   class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-ink-700 shadow-xs transition hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700">
                                    <span class="text-brand-500 font-bold">#</span>
                                    <span>{{ $tag->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
