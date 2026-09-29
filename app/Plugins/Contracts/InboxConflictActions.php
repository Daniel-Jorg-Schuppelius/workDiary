<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InboxConflictActions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Models\Integration\IntegrationInboxItem;
use App\Support\Ui\UiAction;

/** Eigene Aktionen eines Plugins an Konfliktfällen der Integrations-Inbox (MVP-1041). */
interface InboxConflictActions {
    /** @return list<UiAction> */
    public function inboxConflictActions(IntegrationInboxItem $item): array;

    /** Ersetzen die Aktionen „Remote übernehmen / Lokal behalten“ (z. B. Datei-Divergenz statt Feld-Diff)? */
    public function replacesDefaultConflictActions(IntegrationInboxItem $item): bool;
}
