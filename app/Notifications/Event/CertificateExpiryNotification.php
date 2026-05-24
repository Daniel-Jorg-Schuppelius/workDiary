<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CertificateExpiryNotification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Notifications\Event;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateExpiryNotification extends Notification {
    use Queueable;

    public function __construct(
        public readonly int $eventId,
        public readonly string $eventTitle,
        public readonly CarbonInterface $expiresAt,
        public readonly int $daysRemaining,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage)
            ->subject(__('Zertifikat läuft in :days Tagen ab', ['days' => $this->daysRemaining]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name ?? '']))
            ->line(__('Dein Zertifikat für „:title" läuft am :date ab.', [
                'title' => $this->eventTitle,
                'date' => $this->expiresAt->isoFormat('LL'),
            ]))
            ->line(__('Bitte um rechtzeitige Auffrischung.'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array {
        return [
            'event_id' => $this->eventId,
            'title' => $this->eventTitle,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'days_remaining' => $this->daysRemaining,
            'icon' => 'workspace_premium',
        ];
    }
}
