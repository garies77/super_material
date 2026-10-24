{{-- Detail satu jawaban formulir (modal "Lihat"). --}}
<dl class="divide-y divide-gray-100 text-sm dark:divide-white/10">
    @foreach($submission->answers() as $label => $answer)
        <div class="grid gap-1 py-2.5 sm:grid-cols-3 sm:gap-4">
            <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
            <dd class="whitespace-pre-line text-gray-950 sm:col-span-2 dark:text-white">{{ $answer !== '' ? $answer : '—' }}</dd>
        </div>
    @endforeach
    <div class="grid gap-1 py-2.5 sm:grid-cols-3 sm:gap-4">
        <dt class="font-medium text-gray-500 dark:text-gray-400">Masuk</dt>
        <dd class="text-gray-950 sm:col-span-2 dark:text-white">{{ $submission->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</dd>
    </div>
    @if($submission->source_url)
        <div class="grid gap-1 py-2.5 sm:grid-cols-3 sm:gap-4">
            <dt class="font-medium text-gray-500 dark:text-gray-400">Dikirim dari</dt>
            <dd class="break-all text-gray-950 sm:col-span-2 dark:text-white">{{ $submission->source_url }}</dd>
        </div>
    @endif
</dl>
