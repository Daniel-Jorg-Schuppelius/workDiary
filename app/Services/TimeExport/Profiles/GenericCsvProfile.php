<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GenericCsvProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeExport\Profiles;

use App\Models\{TimeExport, TimeExportLine};
use CommonToolkit\Enums\Common\CSV\QuotingStyle;
use CommonToolkit\Helper\Data\CSV\StringHelper as CsvStringHelper;

/**
 * Generisches CSV-Profil (MVP-019).
 *
 * Format:
 *   - UTF-8 mit BOM
 *   - Trenner ;  Quote "  EOL \r\n
 *   - Spalten: personnel_no;wage_type;cost_center;period_start;period_end;quantity;unit;note
 *
 * Personalnummer fällt auf User#id zurück, wenn kein `personnel_no`-Feld
 * existiert (das wird in einem Folge-Paket nachgezogen).
 */
class GenericCsvProfile implements ExportProfile {
    /** @var array{delimiter:string,enclosure:string,eol:string,bom:bool} */
    private array $opts;

    /** @param  array<string,mixed>  $options */
    public function __construct(array $options = []) {
        $this->opts = [
            'delimiter' => is_string($options['delimiter'] ?? null) ? $options['delimiter'] : ';',
            'enclosure' => is_string($options['enclosure'] ?? null) ? $options['enclosure'] : '"',
            'eol' => is_string($options['eol'] ?? null) ? $options['eol'] : "\r\n",
            'bom' => (bool) ($options['bom'] ?? true),
        ];
    }

    public function key(): string {
        return 'generic';
    }

    public function format(): string {
        return 'csv';
    }

    public function render(TimeExport $export): string {
        $rows = [];
        $rows[] = $this->csvLine([
            'personnel_no',
            'wage_type',
            'cost_center',
            'period_start',
            'period_end',
            'quantity',
            'unit',
            'note',
        ]);

        $lines = $export->lines()->orderBy('user_id')->orderBy('wage_type')->orderBy('cost_center')->get();
        /** @var TimeExportLine $line */
        foreach ($lines as $line) {
            $rows[] = $this->csvLine([
                (string) $line->user_id,
                $line->wage_type,
                (string) ($line->cost_center ?? ''),
                $line->period_start->format('Y-m-d'),
                $line->period_end->format('Y-m-d'),
                number_format((float) $line->quantity, 4, '.', ''),
                $line->unit,
                (string) ($line->note ?? ''),
            ]);
        }

        $body = implode($this->opts['eol'], $rows) . $this->opts['eol'];

        return $this->opts['bom'] ? \CommonToolkit\Helper\Data\StringHelper::prependBom($body) : $body;
    }

    /**
     * Eine CSV-Zeile im Always-Quote-Stil (Audit 2026-08, W2.8).
     *
     * Seit dem Toolkit-Release mit `QuotingStyle::ALWAYS` deckt
     * {@see CsvStringHelper::encodeLine()} das Verhalten des frueheren
     * app-lokalen Serializers ab. Vor der Umstellung wurde Byte-Paritaet
     * belegt (leere Felder, Quotes im Wert, Trennzeichen im Wert,
     * Zeilenumbruch im Wert) - der `payload_hash` bleibt damit stabil, was
     * hier revisionsrelevant ist: er weist den ausgelieferten Export nach.
     * Der Regressionstest haelt die Hashes fest.
     *
     * @param  array<int,string>  $fields
     */
    private function csvLine(array $fields): string {
        return CsvStringHelper::encodeLine(
            $fields,
            $this->opts['delimiter'],
            $this->opts['enclosure'],
            QuotingStyle::ALWAYS,
        );
    }
}
