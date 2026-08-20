<?php
/*
 * Created on   : Thu Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsvExport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

use CommonToolkit\Helper\Data\CSV\StringHelper as CsvStringHelper;
use CommonToolkit\Helper\Data\StringHelper;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Zentrales Streaming-CSV-Skelett: UTF-8-BOM + `encodeLine(';')` + "\r\n"
 * und eingebauter Formel-Injektions-Guard auf allen String-Datenzellen.
 *
 * Guard-Semantik (aus DiaryExport übernommen): führendes `=`, `+`, `-`, `@`
 * (nach ltrim) erhält ein Apostroph-Präfix. Das Entschärfen selbst macht das
 * common-toolkit ({@see CsvStringHelper::neutralizeFormulaInjection()});
 * app-seitig bleiben die beiden Geschäftsregeln, die dort NICHT hingehören:
 * die engere Präfixliste (ohne Tab/CR, Bestandsverhalten) und der
 * Typ-Filter — echte Zahlen und Bools werden nie entschärft.
 * Die Header-Zeile bleibt ungeguarded (entwicklerkontrolliert).
 */
final class CsvExport {
    public const DELIMITER = ';';
    public const EOL = "\r\n";

    /**
     * Präfixe des Formel-Guards. Bewusst OHNE Tab/CR, die die
     * OWASP-Liste des Toolkits ({@see CsvStringHelper::FORMULA_PREFIXES})
     * zusätzlich führt: Die App-Semantik stammt aus dem DiaryExport und ist
     * seit jeher auf diese vier Zeichen festgelegt; eine stille Erweiterung
     * würde bestehende Exporte verändern.
     *
     * @var list<string>
     */
    public const FORMULA_PREFIXES = ['=', '+', '-', '@'];

    /**
     * @param  list<string>  $header
     * @param  iterable<int, list<int|float|bool|string|null>>  $rows
     * @param  list<string>  $commentLines  rohe Kopfzeilen (z. B. `#report:x`) vor dem Header
     */
    public static function streamFromRows(string $filename, array $header, iterable $rows, string $delimiter = self::DELIMITER, array $commentLines = []): StreamedResponse {
        $callback = static function () use ($header, $rows, $delimiter, $commentLines): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            // BOM für Excel-UTF8
            fwrite($out, StringHelper::BOM_UTF8);
            foreach ($commentLines as $line) {
                fwrite($out, $line . self::EOL);
            }
            fwrite($out, CsvStringHelper::encodeLine($header, $delimiter) . self::EOL);
            foreach ($rows as $row) {
                fwrite($out, CsvStringHelper::encodeLine(self::guardRow($row), $delimiter) . self::EOL);
            }
            fclose($out);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Nicht-streamende Variante (W4.1): gleiche Struktur (BOM → Kommentarkopf →
     * optionaler Header → geguardete Datenzeilen) als String — Unterbau für
     * Report-Trait und ISMS-Register-Export.
     *
     * @param  list<string>|null  $header  null = Aufrufer liefert den Header als erste Datenzeile
     * @param  iterable<int, list<int|float|bool|string|null>>  $rows
     * @param  list<string>  $commentLines
     */
    public static function toString(?array $header, iterable $rows, string $delimiter = self::DELIMITER, array $commentLines = []): string {
        $csv = '';
        foreach ($commentLines as $line) {
            $csv .= $line . self::EOL;
        }
        if ($header !== null) {
            $csv .= CsvStringHelper::encodeLine($header, $delimiter) . self::EOL;
        }
        foreach ($rows as $row) {
            $csv .= CsvStringHelper::encodeLine(self::guardRow($row), $delimiter) . self::EOL;
        }

        return StringHelper::prependBom($csv);
    }

    /**
     * Formel-Injektions-Guard für eine komplette Datenzeile.
     *
     * @param  list<int|float|bool|string|null>  $row
     * @return list<int|float|bool|string|null>
     */
    public static function guardRow(array $row): array {
        return array_map([self::class, 'guard'], $row);
    }

    /**
     * Formel-Injektions-Guard für eine einzelne Zelle; Nicht-Strings
     * (echte Zahlen/Bools/null) passieren unverändert.
     */
    public static function guard(int|float|bool|string|null $value): int|float|bool|string|null {
        // Nicht-Strings passieren: eine echte Zahl -12,5 ist kein Formelrisiko,
        // ein Apostroph davor macht sie in Excel aber zu Text.
        if (! is_string($value)) {
            return $value;
        }

        return CsvStringHelper::neutralizeFormulaInjection($value, self::FORMULA_PREFIXES, ignoreLeadingWhitespace: true);
    }
}
