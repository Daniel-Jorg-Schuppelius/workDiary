<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxCsvParser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Fritzbox\Sources;

use App\Support\Toolkit\CsvFacade;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{PhoneNumberHelper, StringHelper};
use RuntimeException;

/**
 * Parser für den FRITZ!Box-Anruflisten-Export: ISO-8859-1, `sep=;`-Vorzeile,
 * Header `Typ;Datum;Name;Rufnummer;…;Eigene Rufnummer;Dauer`, Datum `d.m.y H:i`
 * in der Box-Zeitzone (= Org-Zeitzone), Dauer `H:MM`. Kaputte Zeilen werden
 * übersprungen statt den Import abzubrechen.
 */
class FritzboxCsvParser {
    /** FRITZ!OS exportiert 2-stellige Jahre; ältere Firmware teils 4-stellig. */
    private const DATE_FORMATS = ['d.m.y H:i', 'd.m.Y H:i'];

    /** Header-Spalte → Feldname (DE + EN-Export). */
    private const HEADER_ALIASES = [
        'Typ' => 'type',
        'Type' => 'type',
        'Datum' => 'date',
        'Date' => 'date',
        'Name' => 'name',
        'Rufnummer' => 'number',
        'Telephone number' => 'number',
        'Number' => 'number',
        'Eigene Rufnummer' => 'own_line',
        'Own number' => 'own_line',
        'Dauer' => 'duration',
        'Duration' => 'duration',
    ];

    /**
     * @return array<int, FritzboxCall>
     *
     * @throws RuntimeException wenn der Inhalt keine FRITZ!Box-Anrufliste ist
     */
    public function parse(string $content, string $timezone, bool $type3Outgoing = false): array {
        $rows = CsvFacade::parseRows($this->preprocess($content), ';');
        if ($rows === []) {
            throw new RuntimeException('Keine FRITZ!Box-Anrufliste: Datei ist leer.');
        }

        $columns = $this->headerColumns(array_shift($rows));
        if (! isset($columns['type'], $columns['date'], $columns['number'], $columns['duration'])) {
            throw new RuntimeException('Keine FRITZ!Box-Anrufliste: Kopfzeile (Typ;Datum;…;Dauer) fehlt.');
        }

        $calls = [];
        foreach ($rows as $row) {
            $call = $this->callFromRow($row, $columns, $timezone, $type3Outgoing);
            if ($call !== null) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    /** Billige Inhalts-Erkennung für die Mail-Pipeline (ohne Vollparse). */
    public static function looksLikeCallReport(string $content): bool {
        $prepared = (new self)->preprocess($content);
        $firstLine = strtok($prepared, "\r\n");

        return $firstLine !== false
            && preg_match('/^(Typ;Datum;|Type;Date;)/i', trim($firstLine)) === 1;
    }

    /** Encoding → UTF-8, BOM und Excel-`sep=`-Vorzeile entfernen. */
    private function preprocess(string $raw): string {
        $encoding = StringHelper::detectEncoding($raw);
        if ($encoding !== false && strtoupper($encoding) !== 'UTF-8') {
            $raw = StringHelper::convertEncoding($raw, $encoding, 'UTF-8');
        }
        $raw = StringHelper::stripBom($raw);

        return (string) preg_replace('/^\s*sep=.\s*\r?\n/i', '', $raw, 1);
    }

    /**
     * @param  list<string>  $headerRow
     * @return array<string, int> Feldname → Spaltenindex
     */
    private function headerColumns(array $headerRow): array {
        $columns = [];
        foreach ($headerRow as $index => $label) {
            $field = self::HEADER_ALIASES[trim($label)] ?? null;
            if ($field !== null && ! isset($columns[$field])) {
                $columns[$field] = $index;
            }
        }

        return $columns;
    }

    /**
     * @param  list<string>  $row
     * @param  array<string, int>  $columns
     */
    private function callFromRow(array $row, array $columns, string $timezone, bool $type3Outgoing): ?FritzboxCall {
        $cell = static fn (string $field): string => trim((string) ($row[$columns[$field] ?? -1] ?? ''));

        $type = $cell('type');
        $startedAt = $this->parseDate($cell('date'), $timezone);
        if (! ctype_digit($type) || $startedAt === null) {
            return null; // kaputte/fremde Zeile — überspringen statt abbrechen
        }

        $durationMinutes = $this->parseDurationMinutes($cell('duration'));
        $direction = $this->resolveDirection((int) $type, $durationMinutes, $type3Outgoing);

        $numberRaw = $cell('number');
        $name = $cell('name');
        $ownLine = $this->normalizeOwnLine($cell('own_line'));

        return new FritzboxCall(
            type: (int) $type,
            direction: $direction,
            startedAt: $startedAt,
            endedAt: $startedAt->addMinutes($durationMinutes),
            durationMinutes: $durationMinutes,
            numberRaw: $numberRaw,
            e164: $numberRaw !== '' ? PhoneNumberHelper::toE164($numberRaw, 'DE') : null,
            name: $name !== '' ? $name : null,
            ownLine: $ownLine,
        );
    }

    private function parseDate(string $value, string $timezone): ?CarbonImmutable {
        foreach (self::DATE_FORMATS as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $value, $timezone);
                if ($parsed instanceof CarbonImmutable) {
                    return $parsed->setTimezone('UTC');
                }
            } catch (\Throwable) {
                // nächstes Format versuchen
            }
        }

        return null;
    }

    /** FRITZ!Box-Dauer ist `H:MM` (Stunden:Minuten), nicht Minuten:Sekunden. */
    private function parseDurationMinutes(string $value): int {
        if (preg_match('/^(\d+):(\d{1,2})$/', $value, $matches) !== 1) {
            return 0;
        }

        return ((int) $matches[1]) * 60 + (int) $matches[2];
    }

    /**
     * Typ 1 = angenommen, 2 = verpasst, 4 = ausgehend. Typ 3 ist mehrdeutig:
     * neuere Firmware = abgewiesen, ältere = ausgehend — Gespräche mit Dauer
     * sind faktisch ausgehend, der Rest zählt als erfolglos (isMissed).
     */
    private function resolveDirection(int $type, int $durationMinutes, bool $type3Outgoing): string {
        return match (true) {
            $type === FritzboxCall::TYPE_OUTGOING => FritzboxCall::DIR_OUT,
            $type === FritzboxCall::TYPE_REJECTED && ($type3Outgoing || $durationMinutes > 0) => FritzboxCall::DIR_OUT,
            default => FritzboxCall::DIR_IN,
        };
    }

    /** „Internet: 58399100" → „58399100". */
    private function normalizeOwnLine(string $value): ?string {
        $value = trim((string) preg_replace('/^[[:alpha:]]+:\s*/u', '', $value));

        return $value !== '' ? $value : null;
    }
}
