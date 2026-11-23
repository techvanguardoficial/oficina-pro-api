<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * "Esqueci minha senha" do Portal do Cliente. Sai pela fila, como o da oficina,
 * e leva o motorista para a tela de nova senha do Portal.
 */
class ClientResetPasswordNotification extends BaseResetPassword implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = 30;

    public function toMail($notifiable): MailMessage
    {
        $url = rtrim(config('app.client_portal_url'), '/')
            . '/reset-password?token=' . urlencode($this->token)
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        $minutes = (int) config('auth.passwords.client_app_users.expire', 60);
        [$count, $unit] = $minutes >= 60
            ? [intdiv($minutes, 60), intdiv($minutes, 60) === 1 ? 'hora' : 'horas']
            : [$minutes, $minutes === 1 ? 'minuto' : 'minutos'];

        return (new MailMessage)
            ->subject('Crie uma nova senha — FourAuto')
            ->view('emails.reset-password', [
                'url'   => $url,
                'count' => $count,
                'unit'  => $unit,
                'brand' => 'FourAuto',
            ]);
    }
}
