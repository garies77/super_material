<?php

namespace App\Events;

use App\Models\FormSubmission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Jawaban formulir berhasil disimpan. Pendengar: notifikasi admin dan konfirmasi ke pengisi. */
class FormSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public FormSubmission $submission) {}
}
