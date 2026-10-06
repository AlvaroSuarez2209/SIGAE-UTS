<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * En cola desde la importación masiva de docentes (ver
 * App\Services\TeacherImport\TeacherImportService): un lote de decenas de
 * docentes dispara igual número de Password::sendResetLink() seguidos — sin
 * cola, eso bloquearía la petición HTTP de "confirmar importación" mientras
 * se envían uno por uno. Mismo criterio que ya exige
 * EvidenceStatusNotificationsQueueableTest para las notificaciones de
 * evidencias. No cambia el contenido del correo ni el flujo existente de
 * "olvidé mi contraseña" — sus tests usan Notification::fake(), que
 * intercepta la notificación esté o no en cola.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->view('emails.reset-password', [
                'userName' => $notifiable->name,
                'resetUrl' => $url,
                'expireMinutes' => $expireMinutes,
            ]);
    }
}
