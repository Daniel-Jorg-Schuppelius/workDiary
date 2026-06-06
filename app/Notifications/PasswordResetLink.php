<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PasswordResetLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-Mail mit dem Link zum Zurücksetzen des Passworts (self-contained Flow).
 */
class PasswordResetLink extends Notification {
    use Queueable;

    public function __construct(
        public string $url,
        public int $expireMinutes,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage())
            ->subject(__('Passwort zurücksetzen'))
            ->greeting(__('Hallo :name', ['name' => $notifiable->name ?? '']))
            ->line(__('Sie haben das Zurücksetzen Ihres Passworts angefordert.'))
            ->action(__('Passwort zurücksetzen'), $this->url)
            ->line(__('Dieser Link ist :min Minuten gültig.', ['min' => $this->expireMinutes]))
            ->line(__('Falls Sie das nicht angefordert haben, ist keine weitere Aktion nötig.'));
    }
}
