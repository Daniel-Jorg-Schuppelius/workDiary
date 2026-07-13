<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevLodasProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeExport\Profiles;

use App\Models\{TimeExport, TimeExportLine};
use App\Services\TimeExport\WageTypeResolver;

/**
 * DATEV-LODAS-naher CSV-Export (Feature 005, MVP).
 *
 * Format (dokumentiert, KEINE zertifizierte LODAS-Datei):
 *   - CSV ohne BOM, Trenner ;  EOL \r\n, keine Quotes (Felder sind numerisch
 *     bzw. enthalten keine Trennzeichen)
 *   - Header: Personalnummer;Datum;Lohnart;Stunden;Kostenstelle
 *   - Personalnummer: users.personnel_number, Fallback User-ID
 *   - Datum: TT.MM.JJJJ — bei tagesgenauen Zuschlagszeilen der Kalendertag,
 *     bei Monats-Summenzeilen (work.normal) der Monatsletzte (period_end)
 *   - Lohnart: Org-Mapping ({@see WageTypeResolver}, A21) vor
 *     TimeExportLine.wage_type_code (Zuschlagsregel) vor der
 *     konfigurierbaren Default-Lohnart für Normalstunden (Option
 *     `normal_wage_type_code`, Default "1000")
 *   - Stunden: Dezimal mit Komma, 2 Nachkommastellen (LODAS-üblich)
 *   - Kostenstelle: TimeExportLine.cost_center (Rang 35 — Regeln je
 *     Benutzer/Team bzw. Override im Prüf-UI), leer wenn keine
 *
 * Eine perfekte LODAS-Konformität (Mandanten-/Beraternummer-Kopfsatz,
 * Satzbeschreibungen) ist bewusst nicht Teil des MVP; die Struktur
 * Personalnummer/Datum/Lohnart/Stunden ist für den Import-Assistenten
 * des Lohnbüros ausreichend.
 */
class DatevLodasProfile implements ExportProfile {
    private const EOL = "\r\n";

    private const DELIMITER = ';';

    /** Default-Lohnart für Normalstunden, wenn die Zeile keine eigene trägt. */
    private string $normalWageTypeCode;

    /** @param  array<string,mixed>  $options */
    public function __construct(array $options = []) {
        $this->normalWageTypeCode = is_string($options['normal_wage_type_code'] ?? null)
            ? $options['normal_wage_type_code']
            : '1000';
    }

    public function key(): string {
        return 'datev';
    }

    public function format(): string {
        return 'csv';
    }

    public function render(TimeExport $export): string {
        $rows = [implode(self::DELIMITER, ['Personalnummer', 'Datum', 'Lohnart', 'Stunden', 'Kostenstelle'])];

        $resolver = new WageTypeResolver((int) $export->organization_id, $this->key());
        $lines = $export->lines()
            ->with('user:id,personnel_number')
            ->orderBy('user_id')
            ->orderBy('period_start')
            ->orderBy('wage_type')
            ->get();

        /** @var TimeExportLine $line */
        foreach ($lines as $line) {
            $personnelNo = $line->user?->personnel_number;
            if ($personnelNo === null || $personnelNo === '') {
                $personnelNo = (string) $line->user_id;
            }

            // Zuschlagszeilen sind tagesgenau (start == end → Tagesdatum);
            // Monatszeilen (work.normal) laufen auf den Monatsletzten.
            $date = $line->period_end->format('d.m.Y');

            // Org-Mapping vor Regel-Code vor Default (A21, Rückwärtskompatibilität).
            $wageTypeCode = $resolver->resolveCode($line) ?? $this->normalWageTypeCode;

            $rows[] = implode(self::DELIMITER, [
                $personnelNo,
                $date,
                $wageTypeCode,
                number_format((float) $line->quantity, 2, ',', ''),
                (string) $line->cost_center,
            ]);
        }

        return implode(self::EOL, $rows) . self::EOL;
    }
}
