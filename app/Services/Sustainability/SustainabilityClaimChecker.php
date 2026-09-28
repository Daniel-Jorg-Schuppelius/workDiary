<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityClaimChecker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sustainability;

/**
 * Prüft Umweltaussagen auf Formulierungen, die seit der EmpCo-Richtlinie
 * (EU 2024/825, anwendbar ab 27.09.2026) unzulässig oder nur mit Nachweis
 * zulässig sind (MVP-961). Ein Hinweis, keine Rechtsprüfung.
 */
final class SustainabilityClaimChecker {
    /** Muster → Grund (Übersetzungsschlüssel unter sustainability.claim.reason). */
    private const RULES = [
        '/\b(klima|co2|co₂|carbon|climate|kohlenstoff)[\s-]*(neutral|positiv|positive|negativ|negative|kompensiert|compensated|offset)\w*/iu' => 'offset_neutrality',
        '/\bnet[\s-]*zero\b|\bnetto[\s-]*null\b/iu' => 'offset_neutrality',
        '/\b(umweltfreundlich|klimafreundlich|nachhaltig|grün|eco[\s-]*friendly|environmentally friendly|sustainable|green)\b/iu' => 'generic',
        '/\b(100\s*%\s*(ökostrom|grünstrom|green energy|renewable))\b/iu' => 'evidence',
    ];

    /** @return list<array{term: string, reason: string}> */
    public function check(string $text): array {
        $findings = [];
        foreach (self::RULES as $pattern => $reason) {
            if (preg_match_all($pattern, $text, $matches) > 0) {
                foreach (array_unique($matches[0]) as $term) {
                    $findings[] = ['term' => trim($term), 'reason' => $reason];
                }
            }
        }

        return $findings;
    }
}
