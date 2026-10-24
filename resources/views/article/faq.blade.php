                {{-- 11. FAQ Artikel (Bila Ada) --}}
                @if($article->faqs->isNotEmpty())
                    <section class="mt-10 rounded-2xl border border-ink-200 bg-white p-6 shadow-xs">
                        <h2 class="font-display text-xl font-bold text-ink-950 mb-4">Pertanyaan Seputar Artikel</h2>
                        <div class="space-y-3">
                            @foreach($article->faqs as $faq)
                                <details class="group rounded-xl border border-ink-100 bg-ink-50/60 p-4 transition open:border-brand-300 open:bg-brand-50/30">
                                    <summary class="flex cursor-pointer list-none items-center justify-between font-bold text-ink-900">
                                        <span>{{ $faq->question }}</span>
                                        <svg class="h-4 w-4 shrink-0 transition group-open:rotate-180 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                    </summary>
                                    <p class="mt-2 text-sm leading-relaxed text-ink-600">{{ $faq->answer }}</p>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endif
