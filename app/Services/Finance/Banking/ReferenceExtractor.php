<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReferenceExtractor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Banking;

/**
 * Löst Rechnungsnummern-Kandidaten aus Verwendungszweck und End-to-End-ID
 * (Feature 045, „Zuordnungsvorschläge berücksichtigen Rechnungsnummer …").
 *
 * Das Ergebnis ist plaintext (Spalte `extracted_refs`) und bewusst frei von
 * Personenbezug: extrahiert werden nur alphanumerische Tokens, die wie eine
 * Belegnummer aussehen (>= 1 Ziffer, >= 3 Zeichen). Pro Token wird zusätzlich
 * eine normalisierte Variante (Trennzeichen entfernt, Großschreibung) abgelegt,
 * damit der Abgleich „R-2026-0001" gegen „R20260001" findet.
 */
final class ReferenceExtractor {
    /**
     * @return list<string> Eindeutige Kandidaten (Original + normalisiert).
     */
    public static function extract(?string ...$sources): array {
        $candidates = [];

        foreach ($sources as $source) {
            if ($source === null || trim($source) === '') {
                continue;
            }
            // Tokens: Buchstaben/Ziffern/-/_/. zusammenhängend.
            if (preg_match_all('/[A-Za-z0-9][A-Za-z0-9._\/-]{2,}/u', $source, $matches) === false) {
                continue;
            }
            foreach ($matches[0] as $token) {
                $token = trim($token, " \t.-_/");
                if ($token === '' || ! preg_match('/\d/', $token) || mb_strlen($token) < 3) {
                    continue;
                }
                $candidates[] = $token;
                $normalized = self::normalize($token);
                if ($normalized !== '' && $normalized !== mb_strtoupper($token)) {
                    $candidates[] = $normalized;
                }
            }
        }

        return array_values(array_unique($candidates));
    }

    /** Großschreibung und Entfernen gängiger Trennzeichen. */
    public static function normalize(string $token): string {
        return mb_strtoupper((string) preg_replace('/[\s._\/-]+/', '', $token));
    }
}
