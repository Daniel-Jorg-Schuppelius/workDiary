<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettingsTree.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Settings;

/**
 * Schreibweg verschachtelter Settings-Gruppen (JSON-Spalten): Ein leer
 * übermitteltes Feld entfernt den gespeicherten Wert, damit der Default
 * greift; nicht übermittelte Felder bleiben (MVP-1010).
 */
final class SettingsTree {
    /**
     * @param  array<array-key, mixed>  $stored
     * @param  array<array-key, mixed>  $submitted
     * @return array<array-key, mixed>
     */
    public static function merge(array $stored, array $submitted): array {
        return self::prune(array_replace_recursive($stored, $submitted));
    }

    /**
     * Entfernt null, leere Strings und dadurch leer gewordene Unter-Arrays.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public static function prune(array $values): array {
        $out = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = self::prune($value);
                if ($value === []) {
                    continue;
                }
            } elseif ($value === null || $value === '') {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
