                {{-- Video tambahan artikel (video utama tampil di posisi gambar utama) --}}
                @php($extraVideos = $article->videos->reject(fn ($v) => $v->is($primaryVideo)))
                @if($extraVideos->isNotEmpty())
                    <section class="mt-10" aria-label="Video">
                        <h2 class="font-display text-xl font-bold text-ink-950 mb-2">Video Terkait</h2>
                        @foreach($extraVideos as $video)
                            <x-video-player :video="$video->toVideoData($article->url())" />
                        @endforeach
                    </section>
                @endif
