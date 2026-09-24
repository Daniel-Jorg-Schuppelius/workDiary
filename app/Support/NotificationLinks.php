<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

use App\Models\Diary\OpenIssue;

/**
 * Links für Benachrichtigungs-Payloads (MVP-018). Offene Punkte haben keine
 * eigene Detailseite — sie leben im Panel ihres Subjekts, dessen Seite hier
 * samt Nachladen der Relation aufgelöst wird.
 */
final class NotificationLinks {
    public static function openIssueUrl(OpenIssue $issue): ?string {
        $issue->loadMissing('subject');

        return EntityUrl::for($issue->subject);
    }
}
