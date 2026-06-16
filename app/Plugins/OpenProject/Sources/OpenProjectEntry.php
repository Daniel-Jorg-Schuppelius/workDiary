<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject\Sources;

use Carbon\CarbonImmutable;

/**
 * Normalisierte Repräsentation eines OpenProject-Zeiteintrags. {@see OpenProjectApiClient}
 * mappt die HAL-Rohdaten auf dieses DTO; der {@see \App\Plugins\OpenProject\Services\OpenProjectImportService}
 * verarbeitet ausschließlich diese Struktur.
 *
 * OpenProject-Zeiteinträge tragen ein Buchungsdatum (`spentOn`) und eine Dauer
 * (`hours`), aber keine Start-/Stopp-Zeiten — daher nur {@see $spentOn} + {@see $minutes}.
 */
final class OpenProjectEntry {
    public function __construct(
        /** Stabiler Idempotenz-Schlüssel ("openproject:te:<id>"). */
        public readonly string $entryKey,
        /** OpenProject-Projekt-ID (als String) → workDiary-Projekt. */
        public readonly ?string $projectExternalId,
        public readonly ?string $projectName,
        /** OpenProject-Work-Package-ID (als String) → workDiary-Aufgabe. Null = nur Projektbuchung. */
        public readonly ?string $workPackageExternalId,
        public readonly ?string $workPackageSubject,
        public readonly ?string $description,
        public readonly CarbonImmutable $spentOn,
        public readonly int $minutes,
        /** OpenProject-Benutzer-ID (als String), für das User-Mapping. */
        public readonly ?string $userExternalId,
        /** Anzeigename des OpenProject-Benutzers (informativ, für die Inbox). */
        public readonly ?string $userName,
        /** OpenProject kennt kein Abrechenbar-Flag je Eintrag — wird vom Import aus der Config bestimmt. */
        public readonly bool $billable = true,
    ) {}
}
