{{--
    Formulir dinamis. Variabel: $form (CustomForm), $variant: page | embed | dark (latar gelap, mis. footer).
    Lebar mengikuti wadah. Error memakai bag "form_{id}" dan isian lama hanya dipulihkan untuk formulir yang dikirim,
    agar beberapa formulir di satu halaman tidak saling tercampur.
--}}
@php
    $variant ??= 'embed';
    $dark = $variant === 'dark';
    $errors = $errors->getBag('form_'.$form->id);
    $mine = (string) old('_form') === (string) $form->id;
    $old = fn (string $key, $default = null) => $mine ? old($key, $default) : $default;
    $success = session("form_success.{$form->id}");
    $uid = 'f'.$form->id;
    $label = $dark ? 'text-ink-200' : 'text-ink-800';
    $input = 'block w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none transition focus:ring-2 '
        .($dark ? 'border-white/15 bg-white/10 text-white placeholder:text-ink-400 focus:border-brand-400 focus:ring-brand-400/30'
                : 'border-ink-200 bg-white text-ink-900 placeholder:text-ink-400 focus:border-brand-500 focus:ring-brand-500/20');
    $help = $dark ? 'text-ink-400' : 'text-ink-500';
@endphp
<div id="form-{{ $form->slug }}" class="scroll-mt-24">
    @if($success)
        <div role="status" @class(['rounded-2xl p-5 text-sm', 'border border-white/15 bg-white/10 text-white' => $dark, 'border border-emerald-200 bg-emerald-50 text-emerald-900' => ! $dark])>
            <p class="font-semibold">{{ $form->success_message ?: 'Terima kasih, data Anda sudah kami terima.' }}</p>
        </div>
    @else
        <form action="{{ route('form.submit', $form->slug) }}" method="post" class="space-y-4" novalidate>
            @csrf
            <input type="hidden" name="_form" value="{{ $form->id }}">
            <input type="hidden" name="source_url" value="{{ request()->fullUrl() }}">
            {{-- Jebakan bot: tersembunyi dari manusia dan pembaca layar --}}
            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                <label for="{{ $uid }}-hp">Jangan diisi</label>
                <input id="{{ $uid }}-hp" type="text" name="{{ \App\Models\CustomForm::HONEYPOT }}" tabindex="-1" autocomplete="off">
            </div>

            @if($errors->any())
                <p role="alert" @class(['rounded-xl px-3.5 py-2.5 text-sm', 'bg-red-500/15 text-red-200' => $dark, 'bg-red-50 text-red-700' => ! $dark])>
                    Periksa kembali isian yang ditandai.
                </p>
            @endif

            @foreach($form->fields as $field)
                @php
                    $key = $field['key'];
                    $id = $uid.'-'.$key;
                    $error = $errors->first($key) ?: $errors->first($key.'.*');
                    $describedBy = trim(($field['help'] ? $id.'-help ' : '').($error ? $id.'-error' : ''));
                    $attrs = ($field['required'] ? 'required aria-required="true" ' : '').($error ? 'aria-invalid="true" ' : '').($describedBy ? 'aria-describedby="'.$describedBy.'"' : '');
                @endphp
                <div>
                    @if(in_array($field['type'], ['radio', 'checkbox'], true))
                        <fieldset @if($describedBy) aria-describedby="{{ $describedBy }}" @endif>
                            <legend class="mb-2 text-sm font-semibold {{ $label }}">{{ $field['label'] }}@if($field['required'])<span class="text-red-500" aria-hidden="true"> *</span>@endif</legend>
                            <div class="space-y-2">
                                @foreach($field['options'] as $i => $option)
                                    @php $checked = $field['type'] === 'checkbox' ? in_array($option, (array) $old($key, []), true) : $old($key) === $option; @endphp
                                    <label for="{{ $id }}-{{ $i }}" class="flex cursor-pointer items-center gap-2.5 text-sm {{ $dark ? 'text-ink-200' : 'text-ink-700' }}">
                                        <input id="{{ $id }}-{{ $i }}" type="{{ $field['type'] }}" name="{{ $field['type'] === 'checkbox' ? $key.'[]' : $key }}" value="{{ $option }}" @checked($checked)
                                               class="h-4 w-4 border-ink-300 text-brand-600 focus:ring-brand-500 {{ $field['type'] === 'radio' ? 'rounded-full' : 'rounded' }}">
                                        {{ $option }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @else
                        <label for="{{ $id }}" class="mb-1.5 block text-sm font-semibold {{ $label }}">{{ $field['label'] }}@if($field['required'])<span class="text-red-500" aria-hidden="true"> *</span>@endif</label>
                        @if($field['type'] === 'textarea')
                            <textarea id="{{ $id }}" name="{{ $key }}" rows="4" placeholder="{{ $field['placeholder'] }}" class="{{ $input }}" {!! $attrs !!}>{{ $old($key) }}</textarea>
                        @elseif($field['type'] === 'select')
                            <select id="{{ $id }}" name="{{ $key }}" class="{{ $input }}" {!! $attrs !!}>
                                <option value="">{{ $field['placeholder'] ?: 'Pilih salah satu' }}</option>
                                @foreach($field['options'] as $option)
                                    <option value="{{ $option }}" @selected($old($key) === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        @else
                            @php
                                [$type, $extra] = match ($field['type']) {
                                    'email' => ['email', 'autocomplete="email" inputmode="email"'],
                                    'phone' => ['tel', 'autocomplete="tel" inputmode="tel"'],
                                    'number' => ['number', 'inputmode="decimal" step="any"'],
                                    'date' => ['date', ''],
                                    default => ['text', preg_match('/\b(nama|name)\b/i', $field['label']) ? 'autocomplete="name"' : ''],
                                };
                            @endphp
                            <input id="{{ $id }}" type="{{ $type }}" name="{{ $key }}" value="{{ $old($key) }}" placeholder="{{ $field['placeholder'] }}" class="{{ $input }}" {!! $extra !!} {!! $attrs !!}>
                        @endif
                    @endif
                    @if($field['help'])
                        <p id="{{ $id }}-help" class="mt-1 text-xs {{ $help }}">{{ $field['help'] }}</p>
                    @endif
                    @if($error)
                        <p id="{{ $id }}-error" class="mt-1 text-xs font-medium {{ $dark ? 'text-red-300' : 'text-red-600' }}">{{ $error }}</p>
                    @endif
                </div>
            @endforeach

            <button type="submit" class="btn btn-primary w-full justify-center sm:w-auto">{{ $form->submit_label ?: 'Kirim' }}</button>
        </form>
    @endif
</div>
