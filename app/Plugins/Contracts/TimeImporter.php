<?php
/*
 * Created on   : Fri Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

use App\Models\Organization;

/**
 * Plugins mit dieser Fähigkeit ({@see PluginCapability::TimeImport}) importieren
 * Zeiteinträge der Organisation aus einem externen System (z. B. Toggl, Fernwartung).
 * Einheitlicher programmatischer Einstieg über das Standard-Sync-Zeitfenster der
 * Plugin-Konfiguration; gibt eine Ergebnis-Statistik zurück.
 */
interface TimeImporter {
    /**
     * @return array<string, int|string>  z. B. ['created' => 3, 'skipped' => 1, 'unmatched' => 0]
     */
    public function importTimeEntries(Organization $organization): array;
}
