<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EurostatMinimumWageImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Payroll;

use App\Models\MinimumWageReference;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Importiert die monatlichen gesetzlichen Mindestlöhne aller gemeldeten Länder
 * aus dem Eurostat-Datensatz `earn_mw_cur` (Dissemination-API, JSON-stat) in
 * die globale Referenztabelle {@see MinimumWageReference}. Idempotent (Upsert
 * je Land + Stichtag). Die Werte sind MONATLICH (EUR), getrennt vom
 * org-spezifischen Stunden-Mindestlohn.
 *
 * Hinweis: `earn_mw_cur` liefert den absoluten Monatsbetrag. Der häufig
 * verlinkte `earn_mw_avgr2` ist dagegen nur der Anteil am Durchschnittsverdienst
 * (Prozent) und hier ungeeignet.
 */
class EurostatMinimumWageImporter {
    public const DATA_URL = 'https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data/earn_mw_cur';

    /** Eurostat-geo-Aggregate, die keine einzelnen Länder sind. */
    private const NON_COUNTRIES = ['EU', 'EA'];

    /**
     * Holt die Daten und schreibt sie in die Referenztabelle.
     *
     * @return int Anzahl upserteter Datenpunkte
     */
    public function import(): int {
        $response = Http::acceptJson()
            ->timeout(30)
            ->get(self::DATA_URL, ['format' => 'JSON', 'lang' => 'EN', 'currency' => 'EUR']);

        if (! $response->successful()) {
            throw new RuntimeException('Eurostat-Abruf fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return $this->ingest((array) $response->json());
    }

    /**
     * Verarbeitet eine JSON-stat-2.0-Struktur und upsertet die Datenpunkte.
     *
     * @param  array<string, mixed>  $json
     */
    public function ingest(array $json): int {
        $values = (array) ($json['value'] ?? []);
        $ids = (array) ($json['id'] ?? []);
        $sizes = (array) ($json['size'] ?? []);
        $dimension = (array) ($json['dimension'] ?? []);
        if ($values === [] || $ids === [] || count($ids) !== count($sizes)) {
            return 0;
        }

        // Index→Key je Dimension; Strides für die lineare Index-Dekodierung.
        $reverse = [];
        foreach ($ids as $dim) {
            $index = (array) ($dimension[$dim]['category']['index'] ?? []);
            $reverse[$dim] = array_flip($index);
        }
        $strides = [];
        $stride = 1;
        for ($k = count($sizes) - 1; $k >= 0; $k--) {
            $strides[$k] = $stride;
            $stride *= (int) $sizes[$k];
        }

        $count = 0;
        foreach ($values as $flatIndex => $value) {
            if ($value === null) {
                continue;
            }
            $coords = [];
            foreach ($ids as $k => $dim) {
                $pos = intdiv((int) $flatIndex, $strides[$k]) % (int) $sizes[$k];
                $coords[$dim] = $reverse[$dim][$pos] ?? null;
            }

            $geo = (string) ($coords['geo'] ?? '');
            $time = (string) ($coords['time'] ?? '');
            $currency = (string) ($coords['currency'] ?? 'EUR');
            $validFrom = $this->periodToDate($time);

            if (strlen($geo) !== 2 || in_array($geo, self::NON_COUNTRIES, true) || $validFrom === null) {
                continue;
            }

            // whereDate-Abgleich, da valid_from mit Zeitanteil gespeichert wird
            // (ein String-Vergleich auf 'Y-m-d' würde sonst nie matchen → Dubletten).
            $ref = MinimumWageReference::query()
                ->where('country', strtoupper($geo))
                ->where('currency', strtoupper($currency))
                ->whereDate('valid_from', $validFrom)
                ->first();

            $attrs = ['monthly_amount' => round((float) $value, 2), 'source' => 'eurostat'];
            if ($ref !== null) {
                $ref->fill($attrs)->save();
            } else {
                MinimumWageReference::create($attrs + [
                    'country' => strtoupper($geo),
                    'valid_from' => $validFrom,
                    'currency' => strtoupper($currency),
                ]);
            }
            $count++;
        }

        return $count;
    }

    /** Eurostat-Periode → Stichtag: "YYYY-S1"→01-01, "YYYY-S2"→07-01, "YYYY"→01-01. */
    private function periodToDate(string $period): ?string {
        if (preg_match('/^(\d{4})-S([12])$/', $period, $m) === 1) {
            return $m[1] . ($m[2] === '1' ? '-01-01' : '-07-01');
        }
        if (preg_match('/^(\d{4})$/', $period, $m) === 1) {
            return $m[1] . '-01-01';
        }

        return null;
    }
}
