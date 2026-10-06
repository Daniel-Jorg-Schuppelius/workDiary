<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConvertsEventTimesToUtc.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club\Concerns;

use App\Support\Tz;
use Carbon\CarbonImmutable;

/**
 * Wanduhrzeiten eines Termin-Formulars in UTC — für Termin, Wettkampf und
 * Spiel dieselbe Regel (Konsolidierungs-Audit 2026-10, k3-12: drei Kopien,
 * zwei davon machten aus einem leeren Ende die aktuelle Uhrzeit).
 */
trait ConvertsEventTimesToUtc {
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys  Zeitfelder des Formulars
     * @return array<string, mixed>
     */
    private function withUtcTimes(array $data, array $keys = ['started_at', 'ended_at']): array {
        $tz = trim((string) ($data['timezone'] ?? ''));
        $tz = Tz::isValid($tz) && $tz !== 'UTC' ? $tz : Tz::current();
        $data['timezone'] = $tz;
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $data[$key] = CarbonImmutable::parse((string) $data[$key], $tz)->utc()->format('Y-m-d H:i:s');
            }
        }

        return $data;
    }
}
