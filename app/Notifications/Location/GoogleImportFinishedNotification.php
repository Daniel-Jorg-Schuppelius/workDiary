<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleImportFinishedNotification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Notifications\Location;

use App\Notifications\DirectNotification;
use App\Support\NotificationText;

/** Abschlussmeldung des Google-Standortverlauf-Imports im Hintergrund (MVP-1186). */
class GoogleImportFinishedNotification extends DirectNotification {
    private const TITLE_KEY = 'Standortverlauf importiert: :count Punkte';

    private const MESSAGE_KEY = 'Die Punkte sind verarbeitet; Vorschläge finden Sie im Prüf-Eingang der Standorterfassung.';

    public function __construct(public readonly int $count) {
        parent::__construct(['database']);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array {
        $params = ['count' => $this->count];

        return [
            'title' => NotificationText::render(self::TITLE_KEY, $params),
            'title_key' => self::TITLE_KEY,
            'title_params' => $params,
            'message' => NotificationText::render(self::MESSAGE_KEY, []),
            'message_key' => self::MESSAGE_KEY,
            'message_params' => [],
            'count' => $this->count,
            'icon' => 'where_to_vote',
            'url' => route('location.review.index'),
        ];
    }
}
