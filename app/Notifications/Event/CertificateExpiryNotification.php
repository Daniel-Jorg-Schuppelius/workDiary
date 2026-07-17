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

use App\Notifications\DirectNotification;
use App\Support\NotificationText;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;

class CertificateExpiryNotification extends DirectNotification {
    private const TITLE_KEY = 'Zertifikat läuft in :days Tagen ab';

    private const MESSAGE_KEY = 'Dein Zertifikat für „:title" läuft am :date ab.';

    public function __construct(
        public readonly int $eventId,
        public readonly string $eventTitle,
        public readonly CarbonInterface $expiresAt,
        public readonly int $daysRemaining,
    ) {
        parent::__construct(['mail', 'database']);
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
        $titleParams = ['days' => $this->daysRemaining];
        // Datum als ISO-Date (ohne Zeit) → NotificationText formatiert in der
        // Anzeige-Locale ohne Zeitzonenverschiebung.
        $messageParams = ['title' => $this->eventTitle, 'date' => $this->expiresAt->toDateString()];

        return [
            'event_id' => $this->eventId,
            'title' => NotificationText::render(self::TITLE_KEY, $titleParams),
            'title_key' => self::TITLE_KEY,
            'title_params' => $titleParams,
            'message' => NotificationText::render(self::MESSAGE_KEY, $messageParams),
            'message_key' => self::MESSAGE_KEY,
            'message_params' => $messageParams,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'days_remaining' => $this->daysRemaining,
            'icon' => 'workspace_premium',
        ];
    }
}
