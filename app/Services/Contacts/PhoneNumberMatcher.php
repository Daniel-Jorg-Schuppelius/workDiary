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

use CommonToolkit\Helper\Data\PhoneNumberHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Stammdaten-Treffer zu einer Rufnummer (Audit 2026-08, W2.4).
 *
 * Der Algorithmus — tail-7-LIKE-Vorfilter auf `phone`/`mobile`, danach exakter
 * E.164-Vergleich — steckte wortgleich im {@see \App\Services\Cti\CtiCallService}
 * und im {@see \App\Plugins\Fritzbox\FritzboxImportService}; die Kopien waren
 * bereits auseinandergelaufen (Fritzbox mit `whereLikeEscaped`, CTI mit rohem
 * LIKE). Der Vorfilter ist nötig, weil Rufnummern in beliebiger Schreibweise
 * gespeichert sind — verglichen wird erst nach der Normalisierung.
 *
 * Plugin-spezifisch bleibt, was DAVOR läuft: das Nummern-Gedächtnis der
 * Zuordnungs-Inbox (ExternalReference/Alias) kennt nur Fritzbox.
 *
 * BEKANNTE GRENZE (Bestandsverhalten, bei der Zusammenführung belegt): Der
 * Vorfilter sucht die letzten sieben Ziffern als zusammenhängende Zeichenkette.
 * Stehen im gespeicherten Wert Trennzeichen INNERHALB dieser sieben Ziffern
 * („0511 / 123 456 78"), greift er nicht — der Datensatz wird nicht gefunden,
 * obwohl er dieselbe Nummer meint. Eine Behebung braucht einen normalisierten
 * Suchschlüssel an den Stammdaten (E.164-Spalte/Index) statt eines
 * LIKE-Vorfilters; das ist ein eigener Schnitt und bewusst nicht Teil der
 * Konsolidierung. Der Regressionstest hält die Grenze fest.
 */
class PhoneNumberMatcher {
    /** Sicherheitsnetz gegen zu breite Vorfilter-Treffer. */
    private const CANDIDATE_LIMIT = 50;

    /**
     * Erster Stammdaten-Treffer zur E.164-Nummer. Die Klassenliste bestimmt
     * die Priorität — der erste Treffer gewinnt (z. B. Endkunde vor Firma).
     *
     * @param  list<class-string<Model>>  $classes  Modelle mit phone/mobile-Spalten
     */
    public function match(int $organizationId, string $e164, array $classes): ?Model {
        $tail = self::tail($e164);
        if ($tail === null) {
            return null;
        }

        foreach ($classes as $class) {
            $candidates = $class::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where(function ($query) use ($tail): void {
                    $query->whereLikeEscaped('phone', $tail)
                        ->orWhereLikeEscaped('mobile', $tail);
                })
                ->limit(self::CANDIDATE_LIMIT)
                ->get();

            foreach ($candidates as $candidate) {
                // Die Klassenliste garantiert phone/mobile; Model selbst kennt sie nicht.
                foreach ([(string) $candidate->getAttribute('phone'), (string) $candidate->getAttribute('mobile')] as $stored) {
                    if ($stored !== '' && PhoneNumberHelper::toE164($stored, 'DE') === $e164) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    /** Letzte 7 Ziffern als Vorfilter; null, wenn die Nummer keine hergibt. */
    public static function tail(string $e164): ?string {
        $tail = substr((string) preg_replace('/\D/', '', $e164), -7);

        return $tail !== '' ? $tail : null;
    }
}
