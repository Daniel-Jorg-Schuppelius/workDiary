<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncCommandStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Sync;

/**
 * Ergebnis eines Offline-Sync-Befehls (offline-sync-architektur.md §3.2).
 * `conflict` ist für Phase 3 (base_version-Vergleich) reserviert — der
 * MVP-Scope (append-only Befehle) erzeugt nur applied/duplicate/rejected.
 */
enum SyncCommandStatus: string {
    case Applied = 'applied';
    case Duplicate = 'duplicate';
    case Conflict = 'conflict';
    case Rejected = 'rejected';
}
