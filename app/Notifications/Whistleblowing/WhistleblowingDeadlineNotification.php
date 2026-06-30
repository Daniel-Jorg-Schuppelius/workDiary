<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingDeadlineNotification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Notifications\Whistleblowing;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Inhaltsarme Fristen-Erinnerung an Fallbearbeiter (Abschnitt 15). Enthaelt
 * NUR Fallnummer, Priorität, Art der Frist, Fälligkeit und einen Link – NIE
 * Betreff, Beschreibung, Namen oder Anhaenge.
 */
class WhistleblowingDeadlineNotification extends Notification {
    public function __construct(
        public readonly string $caseNumber,
        public readonly string $priority,
        public readonly string $kind,
        public readonly ?string $dueAt,
        public readonly string $url,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage {
        $label = $this->kind === 'acknowledge'
            ? __('Eingangsbestätigung überfällig')
            : __('Rückmeldung fällig');

        return (new MailMessage)
            ->subject(__('Hinweisgeber-Frist') . ': ' . $this->caseNumber)
            ->line($label)
            ->line(__('Fallnummer') . ': ' . $this->caseNumber)
            ->line(__('Priorität') . ': ' . $this->priority)
            ->line(__('Fällig') . ': ' . (string) $this->dueAt)
            ->action(__('Fall öffnen'), $this->url);
    }

    /** @return array<string, string|null> */
    public function toArray(object $notifiable): array {
        return [
            'case_number' => $this->caseNumber,
            'priority' => $this->priority,
            'kind' => $this->kind,
            'due_at' => $this->dueAt,
            'url' => $this->url,
        ];
    }
}
