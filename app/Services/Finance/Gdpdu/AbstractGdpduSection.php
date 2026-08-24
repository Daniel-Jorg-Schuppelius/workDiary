<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractGdpduSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use Carbon\CarbonInterface;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Carbon;

/**
 * Gemeinsame Zellenformatierung aller Z3-Bereiche — hash-relevant: jede
 * Änderung hier ändert die CSV-Bytes und damit den Paket-Hash.
 */
abstract class AbstractGdpduSection implements GdpduSection {
    protected function str(mixed $value): string {
        return trim((string) ($value ?? ''));
    }

    protected function num(mixed $value, int $accuracy): string {
        if ($value === null || $value === '') {
            return '';
        }

        // Byte-identisch zu number_format(…, ',', '') — GoBD-Hash bleibt stabil.
        return NumberHelper::toGermanFormat((float) $value, $accuracy);
    }

    /** Zeitpunkt (Datum + Uhrzeit) als Alpha-Spalte — GDPdU-`Date` kennt nur Datum. */
    protected function dateTime(mixed $value): string {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }

    protected function date(mixed $value): string {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }
}
