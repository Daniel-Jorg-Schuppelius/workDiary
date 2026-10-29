<?php
/*
 * Created on   : Thu Oct 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReadsProviderFields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Domain\Concerns;

use CommonToolkit\Helper\Data\StringHelper;

/**
 * Feldzugriff auf Anbieterzeilen mit toleranten Schlüsseln (Feature 083,
 * Abgleich 2026-10-29): Die Wissensdatenbank von CentralNic Reseller schreibt
 * `ACCOUNTING_DATE`, `OBJECT_ID`, `CONTACT_ORGANIZATION`, der Live-Gateway
 * liefert die RRPproxy-Namen `ACCOUNTINGDATE`, `OBJECTID`, `DOMAINSTATUS`
 * (Live-Befund 2026-08-05). Verglichen wird deshalb ohne Unterstriche,
 * Bindestriche und Leerzeichen, in Kleinschreibung — so trifft ein
 * Kandidat beide Schreibweisen.
 */
trait ReadsProviderFields {
    /**
     * Erster nicht leerer Wert zu einem der Kandidatenschlüssel.
     *
     * @param  array<string, string|null>  $row
     * @param  list<string>  $keys
     */
    protected static function providerField(array $row, array $keys): ?string {
        $compact = [];
        foreach ($row as $name => $value) {
            if ($value === null || trim((string) $value) === '') {
                continue;
            }
            $compact[self::compactKey((string) $name)] ??= trim((string) $value);
        }
        foreach ($keys as $key) {
            $value = $compact[self::compactKey($key)] ?? null;
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  list<string>  $keys
     */
    protected static function providerBool(array $row, array $keys, bool $default): bool {
        $value = self::providerField($row, $keys);

        return $value === null ? $default : (StringHelper::parseBool($value) ?? false);
    }

    protected static function compactKey(string $key): string {
        return strtolower(str_replace(['_', '-', ' '], '', $key));
    }
}
