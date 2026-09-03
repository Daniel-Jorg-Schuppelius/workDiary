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
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Throwable;

/**
 * Legt die erwarteten Abrechnungsperioden gegen die eigenen Rechnungspositionen.
 *
 * Alle fälligen Perioden werden nach Beginn sortiert und der Reihe nach mit
 * Positionen bedient: passendes Produkt, Belegdatum im Fenster um den
 * Periodenbeginn, Restmenge vorhanden — Positionen, die den Endkunden im Text
 * nennen, zuerst, dann die datumsnächste. Der Positionsvorrat ist je
 * Lexoffice-Kontakt **gemeinsam**: Rechnet ein Partner für mehrere Endkunden
 * (Fremdkunden) ab, deckt eine Position mit Menge 2 zwei Endkunden, und keine
 * Position deckt zweimal. Was übrig bleibt und nach Microsoft aussieht, landet
 * einmal je Kontakt als Zusatzposition im Bericht.
 */
final class MarketplaceReconciler {
    public function __construct(
        private readonly ProductNameMatcher $matcher = new ProductNameMatcher(),
    ) {}

    /**
     * @param  list<MarketplaceEntitlement>  $entitlements
     * @param  array<string, ContactMapping>  $mappings  Firmen-Schlüssel → Zuordnung
     */
    public function reconcile(array $entitlements, array $mappings, InvoiceLineSource $source, ReconciliationOptions $options, ?InvoiceLinePool $pool = null): ReconciliationReport {
        $pool ??= new InvoiceLinePool($source);
        $expander = new BillingPeriodExpander(UnitPriceCatalog::fromEntitlements($entitlements));

        /** @var array<string, list<MarketplaceEntitlement>> $byCompany */
        $byCompany = [];
        foreach ($entitlements as $entitlement) {
            $byCompany[$entitlement->company->key][] = $entitlement;
        }

        /** @var array<string, ContactMapping> $resolvedMappings */
        $resolvedMappings = [];
        /** @var array<string, list<BillingPeriod>> $periodsByCompany */
        $periodsByCompany = [];
        /** @var array<string, array{from: CarbonImmutable, to: CarbonImmutable}> $windowByContact */
        $windowByContact = [];

        foreach ($byCompany as $key => $items) {
            $mapping = $mappings[$key] ?? new ContactMapping($items[0]->company, null, [], ContactMapping::SOURCE_NONE);
            $resolvedMappings[$key] = $mapping;

            $periods = [];
            foreach ($items as $entitlement) {
                array_push($periods, ...$expander->dueUntil($entitlement, $options->reference));
            }
            usort($periods, static fn(BillingPeriod $a, BillingPeriod $b): int => $a->startsOn <=> $b->startsOn ?: $a->entitlement->sourceLine <=> $b->entitlement->sourceLine);
            $periodsByCompany[$key] = $periods;

            if ($periods === [] || ! $mapping->isResolved()) {
                continue;
            }
            $first = $periods[0]->startsOn;
            $last = $periods[count($periods) - 1]->startsOn;
            foreach ($mapping->contactIds as $contactId) {
                $window = $windowByContact[$contactId] ?? ['from' => $first, 'to' => $last];
                $windowByContact[$contactId] = [
                    'from' => $first->lessThan($window['from']) ? $first : $window['from'],
                    'to' => $last->greaterThan($window['to']) ? $last : $window['to'],
                ];
            }
        }

        // Positionen je Kontakt einmal laden — gemeinsamer Vorrat für alle
        // Firmen, die über denselben Kontakt abgerechnet werden.
        /** @var array<string, InvoiceLine> $lines */
        $lines = [];
        /** @var array<string, float> $remaining */
        $remaining = [];
        /** @var array<string, list<string>> $errorsByContact */
        $errorsByContact = [];
        foreach ($windowByContact as $contactId => $window) {
            try {
                foreach ($pool->linesFor((string) $contactId, $window['from']->subDays($options->windowBefore), $window['to']->addDays($options->windowAfter)) as $line) {
                    $lines[$line->key()] = $line;
                    $remaining[$line->key()] = $line->headerOnly ? 0.0 : $line->quantity;
                }
            } catch (Throwable $e) {
                $errorsByContact[$contactId][] = 'Kontakt ' . $contactId . ': ' . $e->getMessage();
            }
        }

        // Alle Perioden global nach Beginn, damit bei geteiltem Vorrat die
        // frühere Periode zuerst bedient wird — unabhängig von der Firma.
        /** @var list<array{key: string, period: BillingPeriod}> $queue */
        $queue = [];
        foreach ($periodsByCompany as $key => $periods) {
            if (! $resolvedMappings[$key]->isResolved()) {
                continue;
            }
            foreach ($periods as $period) {
                // Numerische Firmen-Schlüssel („100001") werden als Array-Schlüssel
                // zu int — für den Tiebreaker ausdrücklich als String führen.
                $queue[] = ['key' => (string) $key, 'period' => $period];
            }
        }
        usort($queue, static fn(array $a, array $b): int => $a['period']->startsOn <=> $b['period']->startsOn ?: strcmp($a['key'], $b['key']));

        /** @var array<string, list<PeriodFinding>> $findingsByCompany */
        $findingsByCompany = [];
        foreach ($queue as $entry) {
            $mapping = $resolvedMappings[$entry['key']];
            $findingsByCompany[$entry['key']][] = $this->reconcilePeriod($entry['period'], $mapping, $lines, $remaining, $options);
        }
        // Hinweis: $resolvedMappings/$findingsByCompany sind über den ursprünglichen
        // (ggf. int-)Schlüssel adressiert; PHP normalisiert "100001" und 100001 gleich.

        $companies = [];
        $extrasClaimed = [];
        $linesClaimed = [];
        foreach ($byCompany as $key => $items) {
            $mapping = $resolvedMappings[$key];
            $periods = $periodsByCompany[$key];

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

            // Befunde in Periodenreihenfolge der Firma (die Queue war global sortiert).
            $findings = $findingsByCompany[$key] ?? [];
            usort($findings, static fn(PeriodFinding $a, PeriodFinding $b): int => $a->period->startsOn <=> $b->period->startsOn ?: $a->period->entitlement->sourceLine <=> $b->period->entitlement->sourceLine);

            $extras = [];
            $errors = [];
            $seen = [];
            foreach ($mapping->contactIds as $contactId) {
                foreach ($errorsByContact[$contactId] ?? [] as $error) {
                    $errors[] = $error;
                }
                foreach ($lines as $lineKey => $line) {
                    if ($line->contactId !== $contactId) {
                        continue;
                    }
                    if (! isset($linesClaimed[$lineKey])) {
                        $seen[] = ['line' => $line, 'remaining' => $remaining[$lineKey]];
                        $linesClaimed[$lineKey] = true;
                    }
                    if ($line->headerOnly || $remaining[$lineKey] < 1.0 || isset($extrasClaimed[$lineKey])) {
                        continue;
                    }
                    if ($this->matcher->looksLikeMicrosoftProduct($line->text())) {
                        $extras[] = new ExtraLine($line, $remaining[$lineKey]);
                        $extrasClaimed[$lineKey] = true;
                    }
                }
            }
            usort($seen, static fn(array $a, array $b): int => $a['line']->voucherDate <=> $b['line']->voucherDate ?: $a['line']->position <=> $b['line']->position);

            $companies[] = new CompanyReconciliation($mapping, $findings, $extras, array_values(array_unique($errors)), $seen);
        }

        return new ReconciliationReport($companies, $options);
    }

    /**
     * @param  array<string, InvoiceLine>  $lines
     * @param  array<string, float>  $remaining
     */
    private function reconcilePeriod(BillingPeriod $period, ContactMapping $mapping, array $lines, array &$remaining, ReconciliationOptions $options): PeriodFinding {
        $windowStart = $period->startsOn->subDays($options->windowBefore);
        $windowEnd = $period->startsOn->addDays($options->windowAfter);
        $contactIds = array_flip($mapping->contactIds);
        $companyName = $mapping->company->normalizedName();

        // Stufe 0: Positionen mit erkannter Edition. Stufe 1 (nur ohne
        // strictProducts): jede Microsoft-Position des Kontakts — Sammel-
        // rechnungen nennen die Edition oft nicht („Microsoft 365 Lizenzen").
        $candidates = [];
        foreach ($lines as $key => $line) {
            if (! isset($contactIds[$line->contactId]) || $line->headerOnly || $remaining[$key] <= 0.0) {
                continue;
            }
            if ($line->voucherDate->lessThan($windowStart) || $line->voucherDate->greaterThan($windowEnd)) {
                continue;
            }
            $text = $line->text();
            $exact = $this->matcher->matches($period->entitlement->edition, $text);
            if (! $exact && ($options->strictProducts || ! $this->matcher->looksLikeMicrosoftProduct($text))) {
                continue;
            }
            // Nennung des Endkunden zählt auch im Belegtext (Titel/Einleitung/Schluss).
            $mentions = $companyName !== '' && str_contains(' ' . ProductNameMatcher::normalize($line->fullText()) . ' ', ' ' . $companyName . ' ');
            $candidates[] = ['key' => $key, 'line' => $line, 'tier' => $exact ? 0 : 1, 'mentions' => $mentions ? 0 : 1, 'distance' => abs($line->voucherDate->diffInDays($period->startsOn))];
        }
        usort($candidates, static fn(array $a, array $b): int => $a['tier'] <=> $b['tier'] ?: $a['mentions'] <=> $b['mentions'] ?: $a['distance'] <=> $b['distance'] ?: $a['line']->position <=> $b['line']->position);

        $needed = (float) $period->quantity;
        $matches = [];
        $lowest = null;
        $generic = [];
        foreach ($candidates as $candidate) {
            if ($needed <= 0.0) {
                break;
            }
            $take = min($remaining[$candidate['key']], $needed);
            $remaining[$candidate['key']] -= $take;
            $needed -= $take;
            $matches[] = ['line' => $candidate['line'], 'quantity' => $take, 'exact' => $candidate['tier'] === 0];
            if ($candidate['tier'] === 1) {
                $generic[] = $candidate['line']->name;
            }
            $unit = $candidate['line']->unitNet;
            $lowest = $lowest === null || $unit->lessThan($lowest) ? $unit : $lowest;
        }
        $genericNote = $generic === [] ? '' : 'Produkt nur allgemein erkannt: ' . implode(', ', array_unique($generic));

        if ($matches === []) {
            foreach ($lines as $line) {
                if (! isset($contactIds[$line->contactId])) {
                    continue;
                }
                if ($line->voucherDate->lessThan($windowStart) || $line->voucherDate->greaterThan($windowEnd)) {
                    continue;
                }
                if ($line->netTotal()->greaterThanOrEqual($period->fee())) {
                    $label = $line->voucherNumber !== '' ? $line->voucherNumber : $line->voucherId;

                    return new PeriodFinding($period, ReconciliationStatus::CoveredByAmount, [['line' => $line, 'quantity' => 0.0, 'exact' => false]], null, $needed, 'Beleg ' . $label . ' im Fenster, Produkt nicht erkannt: ' . $line->name);
                }
            }

            return new PeriodFinding($period, ReconciliationStatus::Missing, [], null, $needed, 'Keine Rechnung ' . $windowStart->format('d.m.Y') . ' – ' . $windowEnd->format('d.m.Y') . ($mapping->isBilledViaPartner() ? ' bei ' . $mapping->billedVia : ''));
        }

        $withGeneric = static fn(string $note): string => trim($note . ($genericNote !== '' ? ($note !== '' ? ' · ' : '') . $genericNote : ''));

        if ($needed > 0.0) {
            return new PeriodFinding($period, ReconciliationStatus::Partial, $matches, $lowest, $needed, $withGeneric(sprintf('%s von %d berechnet', $this->formatQuantity($period->quantity - $needed), $period->quantity)));
        }

        if ($lowest instanceof Money && $lowest->lessThan($period->unitFee)) {
            return new PeriodFinding($period, ReconciliationStatus::Underpriced, $matches, $lowest, 0.0, $withGeneric('Netto/Stück ' . $lowest->format() . ' unter Einkauf ' . $period->unitFee->format()));
        }

        return new PeriodFinding($period, ReconciliationStatus::Covered, $matches, $lowest, 0.0, $genericNote);
    }

    private function formatQuantity(float $quantity): string {
        return rtrim(rtrim(number_format($quantity, 2, ',', ''), '0'), ',');
    }
}
