<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhoneNumberMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Support\PhoneSearchKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Stammdaten-Treffer zu einer Rufnummer (Audit 2026-08, W2.4).
 *
 * Der Abgleich lief ursprünglich über einen LIKE-Vorfilter auf die letzten
 * sieben Ziffern von `phone`/`mobile` — wortgleich kopiert im
 * {@see \App\Services\Cti\CtiCallService} und im
 * {@see \App\Plugins\Fritzbox\FritzboxImportService}. Der Vorfilter hatte eine
 * Lücke: Trennzeichen INNERHALB dieser sieben Ziffern („0511 / 123 456 78")
 * hebelten ihn aus, der Datensatz blieb unauffindbar, und im Alltag sah das
 * aus wie „der Anrufer wird nicht erkannt".
 *
 * Seit dem Folgeschnitt (2026-08-21) tragen die Stammdaten einen
 * normalisierten Suchschlüssel ({@see \App\Models\Concerns\HasPhoneSearchKeys}):
 * `phone_e164`/`mobile_e164`, indiziert je Organisation. Gesucht wird exakt
 * darauf — keine Schreibweise mehr, kein Nachfiltern, kein Kandidatenlimit.
 *
 * Plugin-spezifisch bleibt, was DAVOR läuft: das Nummern-Gedächtnis der
 * Zuordnungs-Inbox (ExternalReference/Alias) kennt nur Fritzbox.
 */
class PhoneNumberMatcher {
    /**
     * Erster Stammdaten-Treffer zur E.164-Nummer. Die Klassenliste bestimmt
     * die Priorität — der erste Treffer gewinnt (z. B. Endkunde vor Firma).
     *
     * @param  list<class-string<Model>>  $classes  Modelle mit Rufnummern-Suchschlüssel
     */
    public function match(int $organizationId, string $e164, array $classes): ?Model {
        // Die gesuchte Nummer selbst normalisieren: Anrufanlagen liefern sie
        // nicht immer in reiner E.164-Form.
        $key = PhoneSearchKey::of($e164) ?? $e164;
        if (trim($key) === '') {
            return null;
        }

        foreach ($classes as $class) {
            $match = $class::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where(function ($query) use ($key): void {
                    $query->where('phone_e164', $key)->orWhere('mobile_e164', $key);
                })
                ->first();

            if ($match instanceof Model) {
                return $match;
            }
        }

        return null;
    }
}
