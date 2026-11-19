<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Enviado pela fila (QUEUE_CONNECTION): a requisição de "esqueci a senha"
 * responde na hora e o e-mail sai pelo worker (php artisan queue:work).
 */
class ResetPasswordNotification extends BaseResetPassword implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = 30;

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
