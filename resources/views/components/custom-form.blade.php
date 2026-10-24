{{--
    Sematkan formulir di mana saja (artikel, produk, halaman kustom):
    <x-custom-form slug="konsultasi-proyek" />  atau  <x-custom-form :id="3" :show-title="false" />
    Tidak menampilkan apa pun bila formulir nonaktif atau diatur "Halaman penuh saja".
--}}
@props(['slug' => null, 'id' => null, 'form' => null, 'showTitle' => true, 'variant' => 'embed'])
@php
    $form ??= \App\Models\CustomForm::active()
        ->when($id, fn ($q) => $q->whereKey($id), fn ($q) => $q->where('slug', (string) $slug))
        ->first();
@endphp
@if($form?->is_active && $form->isEmbeddable())
    <section {{ $attributes->class(['w-full']) }} aria-labelledby="form-{{ $form->slug }}-title">
        @if($showTitle)
            <h2 id="form-{{ $form->slug }}-title" @class(['font-display text-xl font-bold', 'text-white' => $variant === 'dark', 'text-ink-900' => $variant !== 'dark'])>{{ $form->title }}</h2>
            @if($form->description)
                <p @class(['mt-1.5 text-sm leading-relaxed', 'text-ink-300' => $variant === 'dark', 'text-ink-600' => $variant !== 'dark'])>{{ $form->description }}</p>
            @endif
        @else
            <span id="form-{{ $form->slug }}-title" class="sr-only">{{ $form->title }}</span>
        @endif
        <div @class(['mt-5' => $showTitle])>
            @include('partials.custom-form', ['form' => $form, 'variant' => $variant])
        </div>
    </section>
@endif
