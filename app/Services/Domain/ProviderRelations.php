<?php
/*
 * Created on   : Fri Oct 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProviderRelations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Domain;

use App\Plugins\Support\Domain\DomainResponse;

/**
 * Relationen einer Anbieterantwort (API Reference Manual 3.0, Abschnitt 1.1
 * „User Relations“): Paare `relation type`/`relation value` aus `StatusUser`
 * und `StatusUserclass`; der Live-Gateway schreibt die Namen mit oder ohne
 * Leerzeichen. Domainpreise heißen `PRICE_CLASS_DOMAIN_<ZONE>_<ART>` mit den
 * Arten ANNUAL, SETUP, TRANSFER, CURRENCY, RESTORE, FACTOR, MONTHLY, …;
 * Perioden- und Promo-Varianten (`ANNUAL5`, `ANNUAL1_PROMO`) behalten ihren Namen.
 */
final class ProviderRelations {
    /**
     * @return list<array{type: string, value: string}>
     */
    public static function fromResponse(DomainResponse $response): array {
        $types = [];
        $values = [];
        foreach ($response->properties as $name => $byIndex) {
            $compact = self::compact((string) $name);
            if ($compact === 'relationtype') {
                $types = $byIndex;
            } elseif ($compact === 'relationvalue') {
                $values = $byIndex;
            }
        }
        ksort($types);
        $relations = [];
        foreach ($types as $index => $type) {
            $relations[] = ['type' => trim((string) $type), 'value' => trim((string) ($values[$index] ?? ''))];
        }

        return $relations;
    }

    /**
     * Werte einer Eigenschaft, Name tolerant gegen Leerzeichen, Unterstriche und Schreibung.
     *
     * @return list<string>
     */
    public static function values(DomainResponse $response, string $wanted): array {
        $wanted = self::compact($wanted);
        foreach ($response->properties as $name => $byIndex) {
            if (self::compact((string) $name) === $wanted) {
                ksort($byIndex);

                return array_values(array_map(static fn ($value): string => trim((string) $value), $byIndex));
            }
        }

        return [];
    }

    /**
     * Domainpreise je Zone: ZONE (wie geliefert, Großbuchstaben) → ART → Wert.
     *
     * @param  list<array{type: string, value: string}>  $relations
     * @return array<string, array<string, string>>
     */
    public static function domainPrices(array $relations): array {
        $prices = [];
        foreach ($relations as $relation) {
            if (preg_match('/^PRICE_CLASS_DOMAIN_(?<zone>[A-Z0-9-]+)_(?<kind>[A-Z]+\d*(?:_PROMO)?)$/i', $relation['type'], $m) !== 1) {
                continue;
            }
            $prices[strtoupper($m['zone'])][strtoupper($m['kind'])] = $relation['value'];
        }
        ksort($prices);

        return $prices;
    }

    private static function compact(string $name): string {
        return str_replace([' ', '_', '-'], '', strtolower($name));
    }
}
