                {{-- Widget 1: Terpopuler (CNN Indonesia Style Numbered 1 to 5) --}}
                @if($popular->isNotEmpty())
                    <div class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm">
                        <div class="border-b-2 border-brand-500 pb-3 mb-4 flex items-center justify-between">
                            <h3 class="font-display text-base font-black uppercase tracking-tight text-ink-950">
                                Terpopuler
                            </h3>
                            <span class="text-[11px] font-bold text-brand-600 bg-brand-50 px-2 py-0.5 rounded-full">
                                Trending
                            </span>
                        </div>
                        <div class="divide-y divide-ink-100">
                            @foreach($popular as $index => $item)
                                <a href="{{ $item->url() }}" class="flex items-start gap-3.5 py-3 group">
                                    <span class="font-display text-3xl font-black text-brand-500 w-7 shrink-0 leading-none pt-0.5 group-hover:text-brand-600 transition">
                                        {{ $index + 1 }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        @if($cat = $item->categories->first())
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-brand-700">
                                                {{ $cat->name }}
                                            </span>
                                        @endif
                                        <h4 class="font-display text-sm font-bold text-ink-900 leading-snug group-hover:text-brand-600 transition line-clamp-2">
                                            {{ $item->title }}
                                        </h4>
                                        <p class="text-[11px] text-ink-400 mt-1">
                                            {{ $item->readingMinutes() }} menit baca
                                        </p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
