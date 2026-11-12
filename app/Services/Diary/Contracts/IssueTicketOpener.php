<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IssueTicketOpener.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Contracts;

use App\Models\Diary\OpenIssue;
use App\Models\Platform\User;

/**
 * Ticket aus einem offenen Punkt (MVP-1549): definiert vom Tagebuch, gebunden
 * vom Helpdesk. Null-Bindung: kein Angebot, kein Ticket.
 */
interface IssueTicketOpener {
    /** Gibt es den Helpdesk und darf die Person Tickets anlegen? */
    public function available(User $actor): bool;

    /**
     * Legt das Ticket an oder liefert das bereits bestehende (höchstens eines je Punkt).
     *
     * @return array{label: string, url: string|null, created: bool}|null
     */
    public function open(OpenIssue $issue, User $actor): ?array;

    /**
     * Bestehende Tickets zu den Punkten, geschlüsselt nach Punkt-ID.
     *
     * @param  list<int>  $issueIds
     * @return array<int, array{label: string, url: string|null}>
     */
    public function linksFor(array $issueIds, User $viewer): array;
}
