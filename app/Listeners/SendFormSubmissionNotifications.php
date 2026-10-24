<?php

namespace App\Listeners;

use App\Events\FormSubmitted;
use App\Notifications\FormSubmissionConfirmation;
use App\Notifications\FormSubmissionReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/** Dikirim lewat antrean agar pengisi tidak menunggu server email. */
class SendFormSubmissionNotifications implements ShouldQueue
{
    public function handle(FormSubmitted $event): void
    {
        $submission = $event->submission->loadMissing('form');
        $form = $submission->form;

        if (filled($form->notify_email)) {
            Notification::route('mail', $form->notify_email)->notify(new FormSubmissionReceived($submission));
        }

        if ($form->send_confirmation && filled($submission->email)) {
            Notification::route('mail', $submission->email)->notify(new FormSubmissionConfirmation($submission));
        }
    }
}
