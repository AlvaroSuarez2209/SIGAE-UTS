<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Restablecimiento de contraseña — SIGAE-UTS')
            ->greeting('Hola, '.$notifiable->name)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta en SIGAE-UTS.')
            ->action('Restablecer contraseña', $url)
            ->line("Este enlace expira en {$expireMinutes} minutos.")
            ->line('Si tú no solicitaste este cambio, puedes ignorar este correo — tu contraseña actual seguirá funcionando.');
    }
}
