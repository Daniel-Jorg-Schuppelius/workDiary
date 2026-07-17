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
 * (nach ltrim) erhält ein Apostroph-Präfix. OWASP nennt zusätzlich Tab/CR
 * als Präfixe — bewusst bei der bestehenden App-Semantik geblieben.
 * Die Header-Zeile bleibt ungeguarded (entwicklerkontrolliert).
 */
final class CsvExport {
    public const DELIMITER = ';';
    public const EOL = "\r\n";

    /**
     * @param  list<string>  $header
     * @param  iterable<int, list<int|float|bool|string|null>>  $rows
     */
    public static function streamFromRows(string $filename, array $header, iterable $rows): StreamedResponse {
        $callback = static function () use ($header, $rows): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            // BOM für Excel-UTF8
            fwrite($out, StringHelper::BOM_UTF8);
            fwrite($out, CsvStringHelper::encodeLine($header, self::DELIMITER) . self::EOL);
            foreach ($rows as $row) {
                fwrite($out, CsvStringHelper::encodeLine(array_map([self::class, 'guard'], $row), self::DELIMITER) . self::EOL);
            }
            fclose($out);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Formel-Injektions-Guard für eine einzelne Zelle; Nicht-Strings
     * (echte Zahlen/Bools/null) passieren unverändert.
     */
    private static function guard(int|float|bool|string|null $value): int|float|bool|string|null {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
