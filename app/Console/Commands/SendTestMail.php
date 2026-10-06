<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Prueba la configuración de correo de .env enviando un mensaje real.
 * Uso: php artisan app:send-test-mail destinatario@dominio.com
 */
class SendTestMail extends Command
{
    protected $signature = 'app:send-test-mail {email : Correo que recibirá la prueba}';

    protected $description = 'Envía un correo de prueba con la configuración de MAIL_* del .env';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("«{$email}» no es un correo válido.");

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $this->line("Mailer: <info>{$mailer}</info> · Host: <info>".config("mail.mailers.{$mailer}.host", '—').'</info> · Remitente: <info>'.config('mail.from.address').'</info>');

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER={$mailer}: el correo NO sale del servidor, solo se escribe en storage/logs. Configura SMTP en .env.");
        }

        try {
            Mail::raw(
                'Este es un correo de prueba de '.config('app.name').'. Si lo recibiste, la configuración de correo funciona.',
                fn ($message) => $message->to($email)->subject('Prueba de correo · '.config('app.name')),
            );
        } catch (Throwable $exception) {
            $this->error('No se pudo enviar: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->line('El mensaje quedó escrito en storage/logs/laravel.log (no se envió).');

            return self::SUCCESS;
        }

        $this->info("Correo de prueba enviado a {$email}. Revisa la bandeja de entrada y también la carpeta de spam.");

        return self::SUCCESS;
    }
}
