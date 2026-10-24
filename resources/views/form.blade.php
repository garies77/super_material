{{-- Halaman penuh formulir: /form/{slug}. Fokus satu kolom, tanpa elemen pengalih. --}}
<x-layouts.app :title="$form->title.' | '.\App\Models\Setting::brandName()"
               :description="\Illuminate\Support\Str::limit((string) $form->description, 155)"
               :canonical="$form->url()" :noindex="true">
    <div class="container-x py-10 sm:py-16">
        <div class="mx-auto max-w-2xl">
            <div class="rounded-3xl border border-ink-100 bg-white p-6 shadow-sm sm:p-10">
                <h1 class="font-display text-2xl font-extrabold text-ink-950 sm:text-3xl">{{ $form->title }}</h1>
                @if($form->description)
                    <p class="mt-3 whitespace-pre-line leading-relaxed text-ink-600">{{ $form->description }}</p>
                @endif
                <div class="mt-8">
                    @include('partials.custom-form', ['form' => $form, 'variant' => 'page'])
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
