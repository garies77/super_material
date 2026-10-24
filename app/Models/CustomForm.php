<?php

namespace App\Models;

use Database\Factories\CustomFormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Formulir dinamis (prospek, pendaftaran, survei). Pertanyaan disimpan sebagai JSON di `fields`:
 * [{key, label, type, placeholder, help, required, options: [..]}]. Kunci (key) dibuat dari label sekali saja
 * agar jawaban lama tetap terbaca walau label diubah.
 */
class CustomForm extends Model
{
    /** @use HasFactory<CustomFormFactory> */
    use HasFactory;

    public const FIELD_TYPES = [
        'text' => 'Teks singkat',
        'textarea' => 'Paragraf',
        'email' => 'Email',
        'phone' => 'Telepon / WhatsApp',
        'number' => 'Angka',
        'radio' => 'Pilihan ganda',
        'checkbox' => 'Kotak centang',
        'select' => 'Dropdown',
        'date' => 'Tanggal',
    ];

    public const CHOICE_TYPES = ['radio', 'checkbox', 'select'];

    public const DISPLAY_MODES = [
        'both' => 'Halaman penuh & sematan',
        'full' => 'Halaman penuh saja',
        'embed' => 'Sematan saja',
    ];

    /** Nama field jebakan bot; tidak boleh dipakai sebagai kunci pertanyaan. */
    public const HONEYPOT = 'website_url';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fields' => 'array', 'is_active' => 'boolean', 'send_confirmation' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(fn (CustomForm $form) => $form->fields = self::normalizeFields($form->fields ?? []));
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function url(): string
    {
        return route('form.show', $this->slug);
    }

    public function hasFullPage(): bool
    {
        return in_array($this->display_mode, ['full', 'both'], true);
    }

    public function isEmbeddable(): bool
    {
        return in_array($this->display_mode, ['embed', 'both'], true);
    }

    /** Pilihan untuk Select di admin (blok beranda, halaman, footer). */
    public static function embedOptions(): array
    {
        return static::query()->where('display_mode', '!=', 'full')->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Aturan validasi server dari skema pertanyaan.
     *
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(): array
    {
        $rules = [];
        foreach ($this->fields as $field) {
            $key = $field['key'];
            $base = ! empty($field['required']) ? ['required'] : ['nullable'];
            $options = array_values(array_filter((array) ($field['options'] ?? []), 'filled'));

            $rules[$key] = match ($field['type']) {
                'textarea' => [...$base, 'string', 'max:5000'],
                'email' => [...$base, 'string', 'email:rfc', 'max:255'],
                'phone' => [...$base, 'string', 'regex:/^[0-9+()\-\s.]{6,25}$/'],
                'number' => [...$base, 'numeric'],
                'date' => [...$base, 'date'],
                'radio', 'select' => [...$base, 'string', Rule::in($options)],
                'checkbox' => [...$base, 'array'],
                default => [...$base, 'string', 'max:255'],
            };

            if ($field['type'] === 'checkbox') {
                $rules["{$key}.*"] = ['string', Rule::in($options)];
            }
        }

        return $rules;
    }

    /**
     * Pesan error berbahasa Indonesia untuk pengisi (bahasa aplikasi bawaan "en").
     *
     * @return array<string, string>
     */
    public function validationMessages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'email' => 'Masukkan alamat email yang valid.',
            'regex' => 'Masukkan nomor telepon yang valid.',
            'numeric' => 'Kolom :attribute harus berupa angka.',
            'date' => 'Kolom :attribute harus berupa tanggal.',
            'in' => 'Pilih salah satu jawaban yang tersedia.',
            'array' => 'Pilihan :attribute tidak valid.',
            'max' => 'Kolom :attribute terlalu panjang (maksimal :max karakter).',
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return collect($this->fields)->mapWithKeys(fn ($f) => [$f['key'] => mb_strtolower($f['label'])])->all();
    }

    /**
     * Nama, email, dan telepon dari jawaban untuk kolom email marketing.
     *
     * @return array{name: ?string, email: ?string, phone: ?string}
     */
    public function extractContact(array $payload): array
    {
        $fields = collect($this->fields);
        $value = fn (?array $field) => $field && filled($payload[$field['key']] ?? null) ? Str::limit((string) $payload[$field['key']], 250, '') : null;

        return [
            'email' => $value($fields->firstWhere('type', 'email')),
            'phone' => $value($fields->firstWhere('type', 'phone')),
            'name' => $value($fields->first(fn ($f) => $f['type'] === 'text' && preg_match('/\b(nama|name)\b/i', $f['label'].' '.$f['key']))),
        ];
    }

    /** Lengkapi kunci unik, buang opsi kosong, dan rapikan tipe yang tidak dikenal. */
    public static function normalizeFields(array $fields): array
    {
        $used = [];

        return collect($fields)->values()->map(function ($field) use (&$used) {
            $type = isset(self::FIELD_TYPES[$field['type'] ?? '']) ? $field['type'] : 'text';
            $base = Str::limit(Str::slug($field['key'] ?? '', '_') ?: (Str::slug($field['label'] ?? '', '_') ?: 'pertanyaan'), 40, '');
            if ($base === self::HONEYPOT) {
                $base .= '_1';
            }
            $key = $base;
            for ($n = 2; isset($used[$key]); $n++) {
                $key = "{$base}_{$n}";
            }
            $used[$key] = true;

            return [
                'key' => $key,
                'label' => (string) ($field['label'] ?? 'Pertanyaan'),
                'type' => $type,
                'placeholder' => $field['placeholder'] ?? null,
                'help' => $field['help'] ?? null,
                'required' => (bool) ($field['required'] ?? false),
                'options' => in_array($type, self::CHOICE_TYPES, true)
                    ? array_values(array_filter(array_map('trim', (array) ($field['options'] ?? [])), 'strlen'))
                    : [],
            ];
        })->all();
    }
}
