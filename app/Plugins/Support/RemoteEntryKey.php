<?php
/*
 * Created on   : Tue Jul 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteEntryKey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

/**
 * Die Fremd-ID hinter dem Idempotenz-Schlüssel eines importierten Zeiteintrags.
 *
 * Die Schlüssel sind präfixiert (`toggl:123`, `api:123`, `openproject:te:42`)
 * oder — bei CSV-Import — ein Hash über die Zeilenwerte (`csv:<sha1>`), weil es
 * dort gar keine Fremd-ID gibt. Die Rückrichtung braucht die nackte ID; für
 * alles ohne adressierbares Gegenstück gibt es nichts zurückzuschreiben.
 */
final class RemoteEntryKey {
    /** Nackte Fremd-ID, oder null wenn der Schlüssel kein Fremdobjekt adressiert. */
    public static function externalId(string $entryKey): ?string {
        $entryKey = trim($entryKey);

        // CSV-Zeilenhash und synthetische Zeitfenster-Schlüssel (`toggl:<start>|<stop>`,
        // wenn die Quelle keine ID lieferte) zeigen auf nichts Adressierbares.
        if ($entryKey === '' || str_starts_with($entryKey, 'csv:') || str_contains($entryKey, '|')) {
            return null;
        }

        $position = strrpos($entryKey, ':');
        $id = $position === false ? $entryKey : substr($entryKey, $position + 1);

        return $id !== '' ? $id : null;
    }
}
