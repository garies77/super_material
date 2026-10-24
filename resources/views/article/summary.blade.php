                {{-- 5. Ringkasan Artikel (Lead Paragraph) --}}
                @if($article->summary)
                    <div class="my-5 rounded-2xl border-l-4 border-brand-500 bg-brand-50/60 p-4 sm:p-5 text-base sm:text-lg font-medium leading-relaxed text-ink-900">
                        {{ $article->summary }}
                    </div>
                @endif
