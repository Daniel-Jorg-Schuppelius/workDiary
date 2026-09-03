<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NameTokenMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

/**
 * Firmennamen tokenbasiert vergleichen: Rechtsform und Füllwörter zählen
 * nicht, die Reihenfolge auch nicht. „Auerswald" trifft „Auerswald GmbH & Co.
 * KG", „Urban Fliesen" trifft „Fliesen Urban", „Meyer und Simson" trifft
 * „Meyer&Simson Elektroinstallationen GmbH" — alle Kern-Tokens des kürzeren
 * Namens stecken im längeren, und mindestens einer davon ist länger als drei
 * Zeichen (sonst träfe „GSR" jede Firma mit diesem Kürzel).
 */
final class NameTokenMatcher {
    private const GENERIC = [
        'gmbh', 'co', 'kg', 'e', 'k', 'v', 'ug', 'ohg', 'mbb', 'ag', 'gbr', 'ltd', 'inc', 'se',
        'und', 'and', 'haftungsbeschraenkt', 'partnerschaftsgesellschaft', 'gesellschaft',
        'service', 'services', 'berlin', 'haus', 'herr', 'frau', 'fam', 'familie',
        'dienstleistungen', 'dienstleistung', 'gruppe', 'group', 'company', 'die', 'der', 'das', 'von', 'mit',
    ];

    /**
     * @return list<string>
     */
    public static function significantTokens(string $name): array {
        $tokens = [];
        foreach (explode(' ', MarketplaceCompany::normalizeName($name)) as $token) {
            if ($token === '' || in_array($token, self::GENERIC, true)) {
                continue;
            }
            $tokens[] = $token;
        }

        return array_values(array_unique($tokens));
    }

    public static function matches(string $a, string $b): bool {
        $tokensA = self::significantTokens($a);
        $tokensB = self::significantTokens($b);
        if ($tokensA === [] || $tokensB === []) {
            return false;
        }
        [$short, $long] = count($tokensA) <= count($tokensB) ? [$tokensA, $tokensB] : [$tokensB, $tokensA];
        if (array_diff($short, $long) !== []) {
            return false;
        }
        foreach ($short as $token) {
            if (mb_strlen($token) >= 4) {
                return true;
            }
        }

        return false;
    }
}
