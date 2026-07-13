<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexwareProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeExport\Profiles;

use App\Models\{TimeExport, TimeExportLine};
use App\Services\TimeExport\WageTypeResolver;

/**
 * Lexware-Lohn-naher CSV-Export (Feature 005). Analog zu {@see DatevLodasProfile},
 * aber im Lexware-üblichen Format:
 *   - CSV ohne BOM, Trenner `;`, EOL `\r\n`, Zeichensatz Windows-1252 (ANSI)
 *   - Header: `Jahr;Monat;Personalnummer;Lohnartnummer;Wert;Stundensatz`
 *   - Jahr/Monat aus dem Zeilen-Zeitraum (`period_end`)
 *   - Personalnummer: `users.personnel_number`, Fallback User-ID
 *   - Lohnartnummer: Org-Mapping ({@see WageTypeResolver}, A21) vor
 *     `TimeExportLine.wage_type_code`, sonst die konfigurierbare
 *     Default-Lohnart (`normal_wage_type_code`) für Normalstunden
 *   - Wert: Stunden als Dezimalzahl mit Komma, 2 Nachkommastellen
 *   - Stundensatz: bewusst leer — den Satz führt Lexware aus dem Lohnart-Stamm
 *     (keine Kostenstellen je Zeile, wie bei DATEV-LODAS auch)
 *
 * Eine zertifizierte Lexware-Datei (Mandantenkopf o. Ä.) ist nicht Teil des MVP;
 * die Struktur genügt dem Import-Assistenten des Lohnbüros.
 */
class LexwareProfile implements ExportProfile {
    private const EOL = "\r\n";

    private const DELIMITER = ';';

    /** Default-Lohnart für Normalstunden, wenn die Zeile keine eigene trägt. */
    private string $normalWageTypeCode;

    /** @param  array<string, mixed>  $options */
    public function __construct(array $options = []) {
        $this->normalWageTypeCode = is_string($options['normal_wage_type_code'] ?? null)
            ? $options['normal_wage_type_code']
            : '1000';
    }

    public function key(): string {
        return 'lexware';
    }

    public function format(): string {
        return 'csv';
    }

    public function render(TimeExport $export): string {
        $rows = [implode(self::DELIMITER, ['Jahr', 'Monat', 'Personalnummer', 'Lohnartnummer', 'Wert', 'Stundensatz'])];

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

            // Org-Mapping vor Regel-Code vor Default (A21, Rückwärtskompatibilität).
            $wageTypeCode = $resolver->resolveCode($line) ?? $this->normalWageTypeCode;

            $rows[] = implode(self::DELIMITER, [
                $line->period_end->format('Y'),
                $line->period_end->format('m'),
                $personnelNo,
                $wageTypeCode,
                number_format((float) $line->quantity, 2, ',', ''),
                '', // Stundensatz: führt Lexware aus dem Lohnart-Stamm
            ]);
        }

        $csv = implode(self::EOL, $rows) . self::EOL;

        // Lexware erwartet ANSI/Windows-1252.
        return (string) mb_convert_encoding($csv, 'Windows-1252', 'UTF-8');
    }
}
