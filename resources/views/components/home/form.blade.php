{{-- Seksi Formulir di beranda: judul & teks seksi di kiri, formulir di kanan (bertumpuk di layar kecil). --}}
@php
    $form = $data['form'];
@endphp
@if($form?->isEmbeddable())
    <section class="container-x mt-20">
        <div class="grid gap-8 rounded-3xl border border-ink-100 bg-white p-6 shadow-sm sm:p-10 lg:grid-cols-12 lg:gap-12">
            <div class="lg:col-span-5">
                <h2 class="section-title">{{ $section->title ?: $form->title }}</h2>
                @if($text = $section->setting('description') ?: $form->description)
                    <p class="mt-3 whitespace-pre-line leading-relaxed text-ink-600">{{ $text }}</p>
                @endif
            </div>
            <div class="lg:col-span-7">
                @include('partials.custom-form', ['form' => $form, 'variant' => 'embed'])
            </div>
        </div>
    </section>
@endif
