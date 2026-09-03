<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarketplaceReconciler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\ReconciliationStatus;
use App\Services\Reselling\Contracts\InvoiceLineSource;
use CommonToolkit\ValueObjects\Money;
use Throwable;

/**
 * Legt die erwarteten Abrechnungsperioden gegen die eigenen Rechnungspositionen.
 *
 * Je Firma werden alle fälligen Perioden nach Beginn sortiert und der Reihe
 * nach mit Positionen bedient: passendes Produkt, Belegdatum im Fenster um den
 * Periodenbeginn, Restmenge vorhanden — die datumsnächste zuerst. Eine
 * Position mit Menge 8 deckt so acht Einzelpositionen des Marketplace, und
 * keine Position deckt zweimal. Was übrig bleibt und nach Microsoft aussieht,
 * landet als Zusatzposition im Bericht.
 */
final class MarketplaceReconciler {
    public function __construct(
        private readonly ProductNameMatcher $matcher = new ProductNameMatcher(),
    ) {}

    /**
     * @param  list<MarketplaceEntitlement>  $entitlements
     * @param  array<string, ContactMapping>  $mappings  Firmen-Schlüssel → Zuordnung
     */
    public function reconcile(array $entitlements, array $mappings, InvoiceLineSource $source, ReconciliationOptions $options): ReconciliationReport {
        $expander = new BillingPeriodExpander(UnitPriceCatalog::fromEntitlements($entitlements));

        $byCompany = [];
        foreach ($entitlements as $entitlement) {
            $byCompany[$entitlement->company->key][] = $entitlement;
        }

        $companies = [];
        foreach ($byCompany as $key => $items) {
            $mapping = $mappings[$key] ?? new ContactMapping($items[0]->company, null, [], ContactMapping::SOURCE_NONE);

            $periods = [];
            foreach ($items as $entitlement) {
                array_push($periods, ...$expander->dueUntil($entitlement, $options->reference));
            }
            usort($periods, static fn(BillingPeriod $a, BillingPeriod $b): int => $a->startsOn <=> $b->startsOn ?: $a->entitlement->sourceLine <=> $b->entitlement->sourceLine);

            if ($periods === []) {
                $companies[] = new CompanyReconciliation($mapping, [], []);

                continue;
            }

            if (! $mapping->isResolved()) {
                $companies[] = new CompanyReconciliation($mapping, array_map(
                    static fn(BillingPeriod $period): PeriodFinding => new PeriodFinding($period, ReconciliationStatus::Unmapped, [], null, (float) $period->quantity, 'Kein Lexoffice-Kontakt zugeordnet'),
                    $periods,
                ), []);

                continue;
            }

            $companies[] = $this->reconcileCompany($mapping, $periods, $source, $options);
        }

        return new ReconciliationReport($companies, $options);
    }

    /**
     * @param  list<BillingPeriod>  $periods
     */
    private function reconcileCompany(ContactMapping $mapping, array $periods, InvoiceLineSource $source, ReconciliationOptions $options): CompanyReconciliation {
        $first = $periods[0]->startsOn;
        $last = $periods[0]->startsOn;
        foreach ($periods as $period) {
            $first = $period->startsOn->lessThan($first) ? $period->startsOn : $first;
            $last = $period->startsOn->greaterThan($last) ? $period->startsOn : $last;
        }
        $from = $first->subDays($options->windowBefore);
        $to = $last->addDays($options->windowAfter);

        /** @var array<string, InvoiceLine> $lines */
        $lines = [];
        $errors = [];
        foreach ($mapping->contactIds as $contactId) {
            try {
                foreach ($source->linesForContact($contactId, $from, $to) as $line) {
                    $lines[$line->key()] = $line;
                }
            } catch (Throwable $e) {
                $errors[] = 'Kontakt ' . $contactId . ': ' . $e->getMessage();
            }
        }

        /** @var array<string, float> $remaining */
        $remaining = [];
        foreach ($lines as $key => $line) {
            $remaining[$key] = $line->headerOnly ? 0.0 : $line->quantity;
        }

        $findings = [];
        foreach ($periods as $period) {
            $findings[] = $this->reconcilePeriod($period, $lines, $remaining, $options);
        }

        $extras = [];
        foreach ($lines as $key => $line) {
            if ($line->headerOnly || $remaining[$key] < 1.0) {
                continue;
            }
            if ($this->matcher->looksLikeMicrosoftProduct($line->text())) {
                $extras[] = new ExtraLine($line, $remaining[$key]);
            }
        }

        return new CompanyReconciliation($mapping, $findings, $extras, $errors);
    }

    /**
     * @param  array<string, InvoiceLine>  $lines
     * @param  array<string, float>  $remaining
     */
    private function reconcilePeriod(BillingPeriod $period, array $lines, array &$remaining, ReconciliationOptions $options): PeriodFinding {
        $windowStart = $period->startsOn->subDays($options->windowBefore);
        $windowEnd = $period->startsOn->addDays($options->windowAfter);

        $candidates = [];
        foreach ($lines as $key => $line) {
            if ($line->headerOnly || $remaining[$key] <= 0.0) {
                continue;
            }
            if ($line->voucherDate->lessThan($windowStart) || $line->voucherDate->greaterThan($windowEnd)) {
                continue;
            }
            if (! $this->matcher->matches($period->entitlement->edition, $line->text())) {
                continue;
            }
            $candidates[] = ['key' => $key, 'line' => $line, 'distance' => abs($line->voucherDate->diffInDays($period->startsOn))];
        }
        usort($candidates, static fn(array $a, array $b): int => $a['distance'] <=> $b['distance'] ?: $a['line']->position <=> $b['line']->position);

        $needed = (float) $period->quantity;
        $matches = [];
        $lowest = null;
        foreach ($candidates as $candidate) {
            if ($needed <= 0.0) {
                break;
            }
            $take = min($remaining[$candidate['key']], $needed);
            $remaining[$candidate['key']] -= $take;
            $needed -= $take;
            $matches[] = ['line' => $candidate['line'], 'quantity' => $take];
            $unit = $candidate['line']->unitNet;
            $lowest = $lowest === null || $unit->lessThan($lowest) ? $unit : $lowest;
        }

        if ($matches === []) {
            foreach ($lines as $line) {
                if ($line->voucherDate->lessThan($windowStart) || $line->voucherDate->greaterThan($windowEnd)) {
                    continue;
                }
                if ($line->netTotal()->greaterThanOrEqual($period->fee())) {
                    $label = $line->voucherNumber !== '' ? $line->voucherNumber : $line->voucherId;

                    return new PeriodFinding($period, ReconciliationStatus::CoveredByAmount, [['line' => $line, 'quantity' => 0.0]], null, $needed, 'Beleg ' . $label . ' im Fenster, Produkt nicht erkannt: ' . $line->name);
                }
            }

            return new PeriodFinding($period, ReconciliationStatus::Missing, [], null, $needed, 'Keine Rechnung ' . $windowStart->format('d.m.Y') . ' – ' . $windowEnd->format('d.m.Y'));
        }

        if ($needed > 0.0) {
            return new PeriodFinding($period, ReconciliationStatus::Partial, $matches, $lowest, $needed, sprintf('%s von %d berechnet', $this->formatQuantity($period->quantity - $needed), $period->quantity));
        }

        if ($lowest instanceof Money && $lowest->lessThan($period->unitFee)) {
            return new PeriodFinding($period, ReconciliationStatus::Underpriced, $matches, $lowest, 0.0, 'Netto/Stück ' . $lowest->format() . ' unter Einkauf ' . $period->unitFee->format());
        }

        return new PeriodFinding($period, ReconciliationStatus::Covered, $matches, $lowest, 0.0);
    }

    private function formatQuantity(float $quantity): string {
        return rtrim(rtrim(number_format($quantity, 2, ',', ''), '0'), ',');
    }
}
