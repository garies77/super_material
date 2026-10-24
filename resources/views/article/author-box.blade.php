                {{-- Penulis Profil Box (PRD 9.13.2) --}}
                @if($article->author)
                    <div class="mt-8 flex items-start gap-4 rounded-2xl border border-ink-100 bg-white p-5 shadow-xs">
                        @if($article->author->avatarUrl())
                            <img src="{{ \App\Support\ImageDerivatives::resized($article->author->avatarUrl(), 112) }}" alt="{{ $article->author->displayName() }}" width="56" height="56" loading="lazy" class="h-14 w-14 rounded-xl object-cover shrink-0">
                        @else
                            <div class="grid h-14 w-14 shrink-0 place-items-center rounded-xl bg-brand-500 font-black text-white text-lg">
                                {{ mb_substr($article->author->name, 0, 1) }}
                            </div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('author.show', $article->author->slug) }}" class="font-display text-base font-bold text-ink-950 hover:text-brand-600 transition">
                                    {{ $article->author->displayName() }}
                                </a>
                                @if($article->author->job_title)
                                    <span class="rounded bg-ink-100 px-2 py-0.5 text-[11px] font-medium text-ink-600">
                                        {{ $article->author->job_title }}
                                    </span>
                                @endif
                            </div>
                            @if($article->author->bio)
                                <p class="mt-1.5 text-xs text-ink-600 leading-relaxed line-clamp-2">
                                    {{ $article->author->bio }}
                                </p>
                            @endif
                            <a href="{{ route('author.show', $article->author->slug) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700">
                                Profil Penulis &amp; Artikel Lainnya &rarr;
                            </a>
                        </div>
                    </div>
                @endif
