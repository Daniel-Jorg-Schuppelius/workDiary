<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParsesOrgLocalDateTimes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Concerns;

use App\Support\Tz;

/**
 * Hilfsmethode für FormRequests: wandelt datetime-local-Eingaben (Wanduhrzeit
 * ohne Zeitzone) von der aktiven Anzeige-Zeitzone nach UTC um, bevor validiert
 * und gespeichert wird. Die DB hält damit durchgängig UTC.
 *
 * Aufruf in prepareForValidation():
 *     $this->mergeOrgLocalToUtc(['started_at', 'ended_at']);
 */
trait ParsesOrgLocalDateTimes {
    /**
     * @param  list<string>  $keys
     */
    protected function mergeOrgLocalToUtc(array $keys): void {
        foreach ($keys as $key) {
            $value = $this->input($key);
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            try {
                $this->merge([$key => Tz::parse(trim($value))->format('Y-m-d H:i:s')]);
            } catch (\Throwable) {
                // Ungültige Eingabe unverändert lassen – die 'date'-Regel meldet sie.
            }
        }
    }
}
