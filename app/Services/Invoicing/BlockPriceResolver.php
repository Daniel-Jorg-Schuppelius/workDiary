<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BlockPriceResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\{Customer, TimeEntry};
use App\Services\Billing\OrganizationDefaultRateResolver;

/**
 * Einzelpreis einer Zeit-Position (MVP-485) — eine Stelle statt der bis dahin
 * fünf kopierten Ketten in den Faktura-Zielen plus Vorschau.
 *
 * Reihenfolge:
 *  0. an der Projektregel ausdrücklich gepflegter Nettopreis (bewusste
 *     Festlegung für dieses Projekt),
 *  1. {@see BillingBlock::hourlyRate()} — Umsatz-Snapshot der Zeiten und damit
 *     alles, was jemand gepflegt hat (Eintrag, Kundenkondition, Mitarbeiter,
 *     Tätigkeit, Projekt, Kunde),
 *  2. `hourly_rate` des primären Eintrags, dann der Kundensatz,
 *  3. Nettopreis der Standardleistung (MVP-486),
 *  4. Organisations-Standarderlös (MVP-482),
 *  5. nichts — dann 0,00 € mit Herkunft `none`, die den Aufrufer eine Meldung
 *     erzeugen lässt statt still eine Nullrechnung zu senden.
 *
 * Punkt 3 vor 4: ist eine Leistung ausgewählt, kennt die App deren Preis;
 * erst ohne Leistung greift der Standarderlös.
 */
class BlockPriceResolver {
    public function __construct(
        private readonly OrganizationDefaultRateResolver $organizationRates,
    ) {}

    public function resolve(
        BillingBlock $block,
        ?TimeEntry $primaryEntry,
        ?Customer $customer,
        ?ResolvedService $service = null,
        ?int $organizationId = null,
    ): BlockPrice {
        // Ein an der Projektregel gepflegter Preis ist eine bewusste Ansage
        // für dieses Projekt und schlägt deshalb den allgemeinen Stundensatz.
        if ($service?->priceIsExplicit === true && $service->netPrice !== null && $service->netPrice > 0.0) {
            return new BlockPrice(round($service->netPrice, 2), BlockPrice::SOURCE_SERVICE);
        }

        $snapshot = $block->hourlyRate();
        if ($snapshot !== null && $snapshot > 0.0) {
            return new BlockPrice(round($snapshot, 2), BlockPrice::SOURCE_SNAPSHOT);
        }

        $entryRate = $primaryEntry?->hourly_rate?->toFloat();
        if ($entryRate !== null && $entryRate > 0.0) {
            return new BlockPrice(round($entryRate, 2), BlockPrice::SOURCE_ENTRY);
        }

        $customerRate = $customer?->hourly_rate?->toFloat();
        if ($customerRate !== null && $customerRate > 0.0) {
            return new BlockPrice(round($customerRate, 2), BlockPrice::SOURCE_CUSTOMER);
        }

        if ($service?->netPrice !== null && $service->netPrice > 0.0) {
            return new BlockPrice(round($service->netPrice, 2), BlockPrice::SOURCE_SERVICE);
        }

        $organizationId ??= $primaryEntry?->organization_id !== null ? (int) $primaryEntry->organization_id : null;
        $orgRate = $this->organizationRates->hourlyRateFor($organizationId);
        if ($orgRate !== null && $orgRate > 0.0) {
            return new BlockPrice(round($orgRate, 2), BlockPrice::SOURCE_ORG_DEFAULT);
        }

        return new BlockPrice(0.0, BlockPrice::SOURCE_NONE);
    }
}
