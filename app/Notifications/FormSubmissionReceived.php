<?php

namespace App\Notifications;

use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Models\FormSubmission;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email ke admin: ringkasan jawaban baru dan tautan ke panel. */
class FormSubmissionReceived extends Notification
{
    public function __construct(public FormSubmission $submission) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Jawaban baru: '.$this->submission->form->title)
            ->line('Ada jawaban baru untuk formulir "'.$this->submission->form->title.'".');

        foreach ($this->submission->answers() as $label => $answer) {
            $message->line("**{$label}:** ".($answer !== '' ? $answer : '—'));
        }

        if ($this->submission->source_url) {
            $message->line('Dikirim dari: '.$this->submission->source_url);
        }

        return $message->action('Buka di panel admin', FormSubmissionResource::getUrl());
    }
}
