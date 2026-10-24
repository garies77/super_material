<?php

namespace App\Notifications;

use App\Models\FormSubmission;
use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email konfirmasi otomatis ke pengisi formulir (bila diaktifkan per formulir). */
class FormSubmissionConfirmation extends Notification
{
    public function __construct(public FormSubmission $submission) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $form = $this->submission->form;

        return (new MailMessage)
            ->subject('Terima kasih: '.$form->title)
            ->greeting($this->submission->name ? 'Halo '.$this->submission->name.',' : 'Halo,')
            ->line($form->confirmation_message ?: ($form->success_message ?: 'Terima kasih, data Anda sudah kami terima.'))
            ->salutation('Salam, '.Setting::brandName());
    }
}
