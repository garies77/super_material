<?php

namespace App\Models;

use Database\Factories\FormSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu jawaban formulir. IP disimpan sebagai hash harian (ClientHash), bukan IP mentah. */
class FormSubmission extends Model
{
    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory;

    public const STATUSES = ['new' => 'Baru', 'contacted' => 'Sudah dihubungi', 'done' => 'Selesai', 'spam' => 'Spam'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }

    /** Jawaban sebagai teks per pertanyaan, mengikuti urutan skema formulir saat ini. */
    public function answers(): array
    {
        $fields = collect($this->form?->fields ?? [])->keyBy('key');
        $answers = [];
        foreach ($this->payload as $key => $value) {
            $answers[$fields[$key]['label'] ?? $key] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return $answers;
    }
}
