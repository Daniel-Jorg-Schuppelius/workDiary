<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobOffset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling;

/**
 * Deterministischer Minutenversatz je Job (Leistungsanalyse 2026-11-02,
 * perf-01): Ohne Versatz starteten zur vollen Stunde 68 Prozesse
 * gleichzeitig. Der Versatz hängt nur am Job-Key, damit Adminseite,
 * Wächter und Registrar dieselbe Fälligkeit sehen.
 */
final class JobOffset {
    public static function minutes(string $jobKey, CadenceType $type): int {
        $interval = $type->intervalMinutes();
        if ($interval <= 1) {
            return 0;
        }
        $hash = crc32($jobKey);

        // Stundentakt meidet die Minuten 0 und 1, dort laufen ohnehin die
        // Minuten- und Fünf-Minuten-Jobs; die Takte darunter meiden nur die 0.
        return $interval === 60 ? 2 + $hash % 56 : 1 + $hash % ($interval - 1);
    }
}
