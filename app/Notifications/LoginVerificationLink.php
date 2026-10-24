<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginVerificationLink extends Notification
{
    public function __construct(public string $url, public int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifikasi login '.Setting::brandName())
            ->line('Kami mendeteksi banyak percobaan login yang gagal pada akun Anda, jadi login kali ini perlu verifikasi email.')
            ->action('Masuk ke panel admin', $this->url)
            ->line("Tautan berlaku {$this->minutes} menit dan hanya bisa dipakai sekali.")
            ->line('Jika bukan Anda yang mencoba masuk, abaikan email ini dan segera ganti password Anda.');
    }
}
