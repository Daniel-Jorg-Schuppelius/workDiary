<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MappingPlausibilityCheck.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use Carbon\CarbonImmutable;

/**
 * Prüft Zuordnungen mit abweichendem Namen (E-Mail-Treffer) an den Rechnungen:
 * Hat der Kontakt im ganzen Zeitraum keine einzige Microsoft-Position, ist die
 * Zuordnung wahrscheinlich falsch — der Besteller-Login gehört zu einer anderen
 * Firma desselben Inhabers. Die Firma wird dann vorübergehend als offen
 * behandelt, damit die Text-Zuordnung über Partnerrechnungen greifen kann;
 * findet die nichts, bleibt die ursprüngliche Zuordnung mit Warnhinweis.
 */
final class MappingPlausibilityCheck {
    private const MARKER = 'Name weicht ab';

    public function __construct(private readonly ProductNameMatcher $matcher = new ProductNameMatcher()) {}

    /**
     * @param  array<string, ContactMapping>  $mappings
     * @return array{mappings: array<string, ContactMapping>, demoted: array<string, ContactMapping>}
     */
    public function demoteImplausible(array $mappings, InvoiceLinePool $pool, CarbonImmutable $from, CarbonImmutable $to): array {
        $demoted = [];
        foreach ($mappings as $key => $mapping) {
            if (! $mapping->isResolved() || ! str_contains($mapping->detail, self::MARKER)) {
                continue;
            }
            if ($this->hasMicrosoftLines($mapping, $pool, $from, $to)) {
                continue;
            }
            $demoted[$key] = $mapping;
            $mappings[$key] = new ContactMapping(
                $mapping->company,
                null,
                [],
                ContactMapping::SOURCE_NONE,
                ['E-Mail-Treffer ' . ($mapping->customer->name ?? implode(', ', $mapping->contactIds)) . ' ohne Microsoft-Positionen'],
            );
        }

        return ['mappings' => $mappings, 'demoted' => $demoted];
    }

    /**
     * Was die Text-Zuordnung nicht auflösen konnte, bekommt seine Zuordnung
     * zurück — mit Warnhinweis, damit der Betreiber sie prüft.
     *
     * @param  array<string, ContactMapping>  $mappings
     * @param  array<string, ContactMapping>  $demoted
     * @return array<string, ContactMapping>
     */
    public function restoreUnresolved(array $mappings, array $demoted): array {
        foreach ($demoted as $key => $original) {
            if (isset($mappings[$key]) && $mappings[$key]->isResolved()) {
                continue;
            }
            $mappings[$key] = new ContactMapping(
                $original->company,
                $original->customer,
                $original->contactIds,
                $original->source,
                $original->candidates,
                $original->detail . ' · keine Microsoft-Positionen beim Kontakt',
                $original->billedVia,
            );
        }

        return $mappings;
    }

    private function hasMicrosoftLines(ContactMapping $mapping, InvoiceLinePool $pool, CarbonImmutable $from, CarbonImmutable $to): bool {
        foreach ($mapping->contactIds as $contactId) {
            foreach ($pool->tryLinesFor($contactId, $from, $to) as $line) {
                if (! $line->headerOnly && $this->matcher->looksLikeMicrosoftProduct($line->text())) {
                    return true;
                }
            }
        }

        return false;
    }
}
