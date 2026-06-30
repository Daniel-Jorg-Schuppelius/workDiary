<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InboxFirstSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Organization;

/**
 * Markiert eine {@see EntitySpec}, die den Import-Modus „Inbox-First" unterstützt:
 * bei einem eindeutigen Treffer wird aktualisiert, sonst wird NICHT blind
 * angelegt, sondern ein Eintrag in der universellen Zuordnungs-Inbox erzeugt
 * (MVP-103). Nur Specs mit einem MatchProfile (Customer/Supplier/Article).
 */
interface InboxFirstSpec {
    /**
     * Wie {@see EntitySpec::upsert()}, aber ohne Blind-Anlage: unzuordenbare
     * Zeilen landen in der Zuordnungs-Inbox (Ergebnis {@see ImportOutcome::Skipped}).
     *
     * @param  array<string, mixed>  $row
     * @return array{0: ImportOutcome, 1: ?ValidationIssue}
     */
    public function upsertOrStage(array $row, Organization $organization): array;
}
