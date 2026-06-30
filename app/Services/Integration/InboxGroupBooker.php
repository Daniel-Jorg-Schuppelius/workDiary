<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InboxGroupBooker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\Organization;
use Illuminate\Support\Collection;

/**
 * Plugin-spezifischer Handler für gruppierte Zeit-Import-Einträge in der
 * universellen Zuordnungs-Inbox: listet die offenen Gruppen (mit Vorschlägen),
 * validiert + bucht eine Gruppe gegen die plugin-eigenen Ziele (Toggl: Kunde +
 * Projekt; OpenProject: Projekt) und verwirft eine Gruppe.
 *
 * So bleibt der generische Inbox-Controller plugin-agnostisch: Validierung und
 * Ziel-Auflösung liegen beim Booker.
 */
interface InboxGroupBooker {
    /**
     * Offene Gruppen der Organisation als View-Modelle. Jede Gruppe trägt einen
     * `form`-Diskriminator (z. B. 'customer_project' | 'project') für die UI.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function groups(Organization $organization): Collection;

    /**
     * Validierungsregeln für die `book()`-Eingabe (plugin-spezifische Felder).
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Löst die Ziele aus der validierten Eingabe auf und bucht alle offenen
     * Einträge der Gruppe.
     *
     * @param  array<string, mixed>  $input  validierte Request-Daten
     * @return array{created: int, skipped: int}
     */
    public function book(Organization $organization, string $groupKey, array $input): array;

    /** Verwirft alle offenen Einträge einer Gruppe. */
    public function dismiss(Organization $organization, string $groupKey): int;
}
