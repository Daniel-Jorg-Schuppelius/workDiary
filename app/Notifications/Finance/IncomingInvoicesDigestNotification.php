<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoicesDigestNotification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Notifications\Finance;

use App\Notifications\DirectNotification;
use App\Support\NotificationText;
use Illuminate\Notifications\Messages\MailMessage;

/** Täglicher Hinweis auf offene Zuordnungen im Rechnungseingang (Feature 163, MVP-1110); nur bei Befund. */
class IncomingInvoicesDigestNotification extends DirectNotification {
    private const TITLE_KEY = 'Rechnungseingang: :count Eingänge zuzuordnen';

    private const MESSAGE_KEY = 'Davon :unrecognized ohne erkannte Rechnungsdaten. Bitte die Arbeitsliste „Rechnungseingang“ prüfen.';

    public function __construct(
        public readonly int $openCount,
        public readonly int $unrecognizedCount,
    ) {
        parent::__construct(['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage)
            ->subject(__(self::TITLE_KEY, ['count' => $this->openCount]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name ?? '']))
            ->line(__(self::MESSAGE_KEY, ['unrecognized' => $this->unrecognizedCount]))
            ->action(__('Rechnungseingang öffnen'), route('finance.incoming-invoices.index', ['tab' => 'assign']));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array {
        $titleParams = ['count' => $this->openCount];
        $messageParams = ['unrecognized' => $this->unrecognizedCount];

        return [
            'title' => NotificationText::render(self::TITLE_KEY, $titleParams),
            'title_key' => self::TITLE_KEY,
            'title_params' => $titleParams,
            'message' => NotificationText::render(self::MESSAGE_KEY, $messageParams),
            'message_key' => self::MESSAGE_KEY,
            'message_params' => $messageParams,
            'open_count' => $this->openCount,
            'unrecognized_count' => $this->unrecognizedCount,
            'icon' => 'move_to_inbox',
            'url' => route('finance.incoming-invoices.index', ['tab' => 'assign']),
        ];
    }
}
