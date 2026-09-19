<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        if ($expireMinutes >= 60) {
            $count = $expireMinutes / 60;
            $unit  = $count === 1 ? 'hora' : 'horas';
        } else {
            $count = $expireMinutes;
            $unit  = $count === 1 ? 'minuto' : 'minutos';
        }

        return (new MailMessage)
            ->subject('Redefinição de senha — OficinaPro')
            ->view('emails.reset-password', [
                'url'   => $url,
                'count' => $count,
                'unit'  => $unit,
            ]);
    }
}
