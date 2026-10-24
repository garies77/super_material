                {{-- Isi artikel: paragraf pembuka sesuai teks asli penulis (tanpa dateline/inisial gaya cetak) --}}
                <div class="content mt-6 text-[17px] leading-[1.8] text-ink-800">
                    {!! $html !!}
                </div>
                @include('partials.custom-code', ['model' => $article])
