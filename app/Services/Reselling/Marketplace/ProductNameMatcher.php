<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductNameMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

/**
 * Erkennt, ob eine Rechnungsposition eine Marketplace-Edition meint.
 *
 * Zwei Wege: Alle Kern-Tokens der Edition kommen im Positionstext vor
 * („Exchange Online (Plan 1)" → exchange, online, plan, 1), oder eine der
 * gebräuchlichen Kurzschreibweisen trifft („Business Standard"). Füllwörter
 * wie „Microsoft" oder „Lizenz" zählen nicht, weil der Reseller sie auf der
 * Rechnung frei setzt.
 */
final class ProductNameMatcher {
    private const STOPWORDS = [
        'microsoft', 'ms', 'for', 'no', 'eea', 'ohne', 'the', 'and', 'und', 'fuer',
        'lizenz', 'lizenzen', 'license', 'licence', 'jahreslizenz', 'monatslizenz', 'abo', 'abonnement',
    ];

    /** @var array<string, list<string>> normalisierte Edition → Kurzschreibweisen (normalisiert) */
    private const ALIASES = [
        'exchange online plan 1' => ['exchange online', 'exchange plan 1', 'exo plan 1', 'exchange p1'],
        'microsoft 365 business standard' => ['m365 business standard', 'o365 business standard', 'business standard'],
        'microsoft 365 business premium' => ['m365 business premium', 'o365 business premium', 'business premium'],
        'microsoft 365 business basic' => ['m365 business basic', 'o365 business basic', 'business basic'],
        'microsoft 365 apps for business' => ['apps for business', 'm365 apps', 'microsoft 365 apps'],
        'microsoft teams essentials' => ['teams essentials'],
        'office 365 e5 eea no teams' => ['office 365 e5', 'o365 e5', 'e5 eea'],
    ];

    /** Eigene Dienstleistungen, die trotz „Business"/„Microsoft" im Text keine Lizenzposition sind. */
    private const SERVICE_HINTS = [
        'support', 'stunde', 'stunden', 'wartung', 'beratung', 'einrichtung', 'schulung', 'entwicklung',
        'pauschale', 'anfahrt', 'fernwartung', 'migration', 'installation', 'datev',
    ];

    private const MICROSOFT_HINTS = [
        'microsoft', 'm365', 'o365', 'office 365', 'exchange online', 'teams essentials',
        'business premium', 'business standard', 'business basic', 'apps for business', 'sharepoint', 'onedrive', 'azure',
    ];

    public static function normalize(string $text): string {
        $text = mb_strtolower($text);
        $text = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $text);
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    /**
     * @return list<string>
     */
    public function tokens(string $edition): array {
        $tokens = [];
        foreach (explode(' ', self::normalize($edition)) as $token) {
            if ($token === '' || in_array($token, self::STOPWORDS, true)) {
                continue;
            }
            $tokens[] = $token;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Quellneutraler Produktschlüssel: „Exchange Online (Plan 1)" (Telekom) und
     * „Exchange Online Plan 1" (Quality Hosting) sind dasselbe Produkt.
     */
    public function productKey(string $edition): string {
        return implode(' ', $this->tokens($edition));
    }

    /**
     * Anteil der Kern-Tokens im Text (0…1); 1.0 bei Alias-Treffer.
     */
    public function score(string $edition, string $text): float {
        $haystack = ' ' . self::normalize($text) . ' ';
        foreach (self::ALIASES[self::normalize($edition)] ?? [] as $alias) {
            if (str_contains($haystack, ' ' . $alias . ' ')) {
                return 1.0;
            }
        }

        $tokens = $this->tokens($edition);
        if ($tokens === []) {
            return 0.0;
        }

        $hits = 0;
        foreach ($tokens as $token) {
            if ($this->containsToken($haystack, $token)) {
                $hits++;
            }
        }

        return $hits / count($tokens);
    }

    public function matches(string $edition, string $text): bool {
        return $this->score($edition, $text) >= 1.0;
    }

    public function looksLikeMicrosoftProduct(string $text): bool {
        $haystack = ' ' . self::normalize($text) . ' ';
        foreach (self::SERVICE_HINTS as $hint) {
            if (str_contains($haystack, ' ' . $hint . ' ')) {
                return false; // „Business Support" ist eine eigene Leistung, keine Lizenz
            }
        }
        foreach (self::MICROSOFT_HINTS as $hint) {
            if (str_contains($haystack, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function containsToken(string $haystack, string $token): bool {
        if (strlen($token) <= 2) {
            return (bool) preg_match('/(?<![a-z0-9])' . preg_quote($token, '/') . '(?![a-z0-9])/', $haystack);
        }

        return str_contains($haystack, $token);
    }
}
