<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainResponseParser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\DomainReselling\Support;

use App\Plugins\Support\Domain\DomainResponse;

/**
 * Parser für das INI-ähnliche DomainReselling-Plaintextprotokoll (Feature 083,
 * MVP-385).
 *
 *  - Property-Namen sind case-insensitive und ohne Leerzeichen auszuwerten
 *    (hier klein normalisiert, Whitespace um `=` getrimmt).
 *  - Wiederholte Werte kommen als `PROPERTY[NAME][INDEX]=VALUE`; der Index
 *    korreliert Spalten (eine Domain je Index in `QueryDomainList`).
 *  - Jede vollständige Antwort MUSS mit `EOF` enden; fehlt der Marker, gilt
 *    das Ergebnis als unklar (`hasEof=false`) und bestätigt keine Mutation.
 */
final class DomainResponseParser {
    public static function parse(string $body): DomainResponse {
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];

        $code = 0;
        $description = '';
        $runtime = null;
        $queuetime = null;
        $hasEof = false;
        /** @var array<string, array<int, string>> $properties */
        $properties = [];
        $autoIndex = [];

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            if (strcasecmp($line, 'EOF') === 0) {
                $hasEof = true;

                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));

            if (preg_match('/^property\[([^\]]*)\](?:\[(\d+)\])?$/', $key, $m) === 1) {
                $name = strtolower(trim($m[1]));
                if (isset($m[2])) {
                    $index = (int) $m[2];
                } else {
                    $index = $autoIndex[$name] ?? 0;
                }
                $autoIndex[$name] = max($autoIndex[$name] ?? 0, $index) + 1;
                $properties[$name][$index] = $value;

                continue;
            }

            match ($key) {
                'code' => $code = (int) $value,
                'description' => $description = $value,
                'runtime' => $runtime = is_numeric($value) ? (float) $value : null,
                'queuetime' => $queuetime = is_numeric($value) ? (float) $value : null,
                default => null,
            };
        }

        return new DomainResponse($code, $description, $properties, $hasEof, $runtime, $queuetime, $body);
    }
}
