                {{-- 4. Gambar Utama (16:9) dengan Keterangan & Kredit Foto --}}
                @if($article->imageUrl())
                    <figure class="my-6">
                        <div class="overflow-hidden rounded-2xl bg-ink-100 shadow-sm">
                            @php
                                // Elemen LCP: WebP sesuai lebar layar, tetap eager + fetchpriority tinggi
                                $heroSrcset = \App\Support\ImageDerivatives::srcset($article->imageUrl(), [480, 800, 1200]);
                            @endphp
                            <img src="{{ \App\Support\ImageDerivatives::resized($article->imageUrl(), 1200) }}"
                                 @if($heroSrcset) srcset="{{ $heroSrcset }}" sizes="(min-width: 1024px) 740px, calc(100vw - 2.5rem)" @endif
                                 alt="{{ $article->image_caption ?: $article->title }}"
                                 width="1200" height="675"
                                 fetchpriority="high"
                                 class="aspect-[16/9] w-full object-cover">
                        </div>
                        <figcaption class="mt-2 text-xs text-ink-500 leading-relaxed">
                            @if($article->image_caption)
                                <span class="italic">{{ $article->image_caption }}</span>
                            @endif
                            <span class="text-ink-400 font-medium">
                                (Foto: {{ $article->image_credit ?: 'Dok. Supermaterial' }})
                            </span>
                        </figcaption>
                    </figure>
                @endif
