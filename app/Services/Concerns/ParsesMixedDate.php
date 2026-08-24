<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParsesMixedDate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Concerns;

use Illuminate\Support\Carbon;

/**
 * Nachsichtiger Datums-Parser für gemischte Service-Eingaben (Vollscan
 * 2026-08-23, B17 — vorher 4 byte-identische Kopien in MeterReading-/
 * MaintenancePlan-/KeyHandover-/ServiceTicketService). Bewusst Carbon::parse
 * statt Toolkit-parseFlexible: die Aufrufer reichen Carbon-Instanzen und
 * ISO-Strings aus eigenen Formularen durch, keine Fremdformate.
 */
trait ParsesMixedDate {
    private function parseDate(mixed $value): ?Carbon {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value;
        }

        return Carbon::parse((string) $value);
    }
}
