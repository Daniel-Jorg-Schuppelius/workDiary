<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuestionTicketOpener.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning\Contracts;

use App\Models\Platform\{Organization, User};
use App\Models\ServiceTicket\ServiceTicket;

/**
 * Lernfrage als Ticket eröffnen (MVP-863): definiert von der Lernplattform,
 * gebunden vom Helpdesk. Null-Bindung: kein Ticket — die Frage geht per Mail
 * an die Kursverantwortung.
 */
interface QuestionTicketOpener {
    /** @param array<string, mixed> $attributes Titel, Beschreibung, Quellenreferenz */
    public function open(Organization $organization, User $learner, array $attributes, User $assignee): ?ServiceTicket;
}
