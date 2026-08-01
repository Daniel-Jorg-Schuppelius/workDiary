<?php
/*
 * Created on   : Fri Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenTimesDigestNotification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Notifications\Finance;

use App\Notifications\DirectNotification;
use App\Support\NotificationText;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Wöchentlicher Offene-Zeiten-Digest (MVP-461): geht nur bei Befund raus —
 * Nachzügler in bereits abgerechneten Zeiträumen oder überfällige offene
 * Einträge. Empfänger sind die Nutzer mit Org-weiter Zeit-Sicht
 * (timeEntry.viewAny bzw. Admin), analog zur Arbeitsliste.
 */
class OpenTimesDigestNotification extends DirectNotification {
    private const TITLE_KEY = 'Offene Zeiten: :count Einträge warten auf Abrechnung';

    private const MESSAGE_KEY = 'Davon :late Nachzügler in bereits abgerechneten Zeiträumen und :stale älter als :days Tage. Bitte die Arbeitsliste „Offene Zeiten" prüfen.';

    public function __construct(
        public readonly int $openCount,
        public readonly int $lateCount,
        public readonly int $staleCount,
        public readonly int $staleAfterDays,
    ) {
        parent::__construct(['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage)
            ->subject(__(self::TITLE_KEY, ['count' => $this->openCount]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name ?? '']))
            ->line(__(self::MESSAGE_KEY, [
                'late' => $this->lateCount,
                'stale' => $this->staleCount,
                'days' => $this->staleAfterDays,
            ]))
            ->action(__('Offene Zeiten öffnen'), route('finance.open-times.index'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array {
        $titleParams = ['count' => $this->openCount];
        $messageParams = ['late' => $this->lateCount, 'stale' => $this->staleCount, 'days' => $this->staleAfterDays];

        return [
            'title' => NotificationText::render(self::TITLE_KEY, $titleParams),
            'title_key' => self::TITLE_KEY,
            'title_params' => $titleParams,
            'message' => NotificationText::render(self::MESSAGE_KEY, $messageParams),
            'message_key' => self::MESSAGE_KEY,
            'message_params' => $messageParams,
            'open_count' => $this->openCount,
            'late_count' => $this->lateCount,
            'stale_count' => $this->staleCount,
            'icon' => 'pending_actions',
            'url' => route('finance.open-times.index'),
        ];
    }
}
