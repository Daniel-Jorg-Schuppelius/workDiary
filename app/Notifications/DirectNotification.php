<?php
/*
 * Created on   : Thu Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DirectNotification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Basis der Direkt-Benachrichtigungen (C17): Empfänger und Kanäle bestimmt der
 * Fach-Service — bewusst am NotificationDispatcher vorbei, weil dessen
 * Regel-/Präferenzlogik (deaktivierbare Org-Regel, Ruhezeiten, mail_enabled,
 * Empfänger aus der Regel statt fach-berechnet: Approver, Teilnehmer,
 * Zertifikatsinhaber) den heutigen garantierten Versand nicht exakt abbildet.
 * Ableitungen schreiben in toArray() title_key/message_key+params, damit die
 * Anzeige in der Sprache des Betrachters übersetzt ({@see \App\Support\NotificationText};
 * Alt-Zeilen fallen dort auf title/message zurück).
 */
abstract class DirectNotification extends Notification {
    use Queueable;

    /** @param list<string> $channels */
    public function __construct(public readonly array $channels = ['mail', 'database']) {}

    /**
     * Nur mail/database sind zulässig; andere Kanäle werden verworfen.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array {
        unset($notifiable);

        return array_values(array_filter(
            $this->channels,
            static fn(string $c): bool => in_array($c, ['mail', 'database'], true),
        ));
    }
}
