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
 *
 * @phpstan-type LineMatch array{line: InvoiceLine, quantity: float, exact?: bool, months?: float, monthly?: bool, annual_unit?: Money}
 * @phpstan-type PeriodState array{key: string, period: BillingPeriod, needed: float, matches: list<LineMatch>, lowest: ?Money, generic: list<string>}
 */ final class MarketplaceReconciler {
    private ArticleCatalog $catalog;

    /** @var list<string> Editionen des laufenden Abgleichs */
    private array $editions = [];

    public function __construct(
        private readonly ProductNameMatcher $matcher = new ProductNameMatcher(),
    ) {
        $this->catalog = ArticleCatalog::empty();
    }

    /**
     * Produkttext einer Position: der Artikelname aus dem eigenen Stamm, wenn
     * die Position aus einem Artikel stammt, sonst der Positionstext.
     */
    private function productText(InvoiceLine $line): string {
        $article = $this->catalog->forLine($line);

        return $article === null ? $line->text() : $article->name . ' ' . $line->text();
    }

    /**
     * Ist die Position eine Lizenzposition? Der eigene Artikelstamm entscheidet
     * verbindlich: Stammt die Position aus einem Artikel, zählt nur, ob DER
     * ARTIKEL ein Microsoft-Produkt ist („Business Support" bleibt Support,
     * auch wenn die Beschreibung „Microsoft 365" nennt). Ohne Artikel
     * entscheidet der Positionstext.
     */
    private function isLicenseLine(InvoiceLine $line): bool {
        if ($line->headerOnly) {
            return false;
        }
        $article = $this->catalog->forLine($line);
        if ($article !== null) {
            return $this->matcher->looksLikeMicrosoftProduct($article->name) || $this->matchesAnyEdition($article->name);
        }

        return $this->matcher->looksLikeMicrosoftProduct($line->text());
    }

    /**
     * @param  list<MarketplaceEntitlement>  $entitlements
     * @param  array<string, ContactMapping>  $mappings  Firmen-Schlüssel → Zuordnung
     */
    public function reconcile(array $entitlements, array $mappings, InvoiceLineSource $source, ReconciliationOptions $options, ?InvoiceLinePool $pool = null, ?ArticleCatalog $catalog = null): ReconciliationReport {
        $pool ??= new InvoiceLinePool($source);
        $this->catalog = $catalog ?? ArticleCatalog::empty();
        $this->editions = array_values(array_unique(array_map(static fn(MarketplaceEntitlement $e): string => $e->edition, $entitlements)));
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

        // Zuteilung in Pässen über ALLE Perioden: erst Positionen mit erkannter
        // Edition an die Periode, deren Beginn ihrem Belegdatum am nächsten liegt;
        // dann Reste an jede Periode im Fenster (Mehrjahresblöcke, Nachberechnung);
        // danach dasselbe für allgemeine Microsoft-Positionen. So schluckt eine
        // frühere, offene Periode nicht die Rechnung, die klar zur nächsten gehört.
        $states = [];
        foreach ($queue as $entry) {
            $states[] = ['key' => $entry['key'], 'period' => $entry['period'], 'needed' => (float) $entry['period']->licenseMonths(), 'matches' => [], 'lowest' => null, 'generic' => []];
        }
        $ownerCount = [];
        foreach ($resolvedMappings as $mapping) {
            foreach ($mapping->contactIds as $contactId) {
                $ownerCount[$contactId] = ($ownerCount[$contactId] ?? 0) + 1;
            }
        }
        $nearest = $this->nearestPeriods($states, $lines);
        foreach ([[true, true], [true, false], [false, true], [false, false]] as [$exactOnly, $nearestOnly]) {
            if (! $exactOnly && $options->strictProducts) {
                break;
            }
            foreach ($states as $index => $state) {
                if ($state['needed'] <= 0.0) {
                    continue;
                }
                $states[$index] = $this->allocate($index, $state, $resolvedMappings[$state['key']], $lines, $remaining, $options, $exactOnly, $nearestOnly, $nearest, $ownerCount);
            }
        }

        /** @var array<string, list<PeriodFinding>> $findingsByCompany */
        $findingsByCompany = [];
        foreach ($states as $state) {
            $mapping = $resolvedMappings[$state['key']];
            $findingsByCompany[$state['key']][] = $this->finding($state, $mapping, $lines, $options);
        }
        // Hinweis: $resolvedMappings/$findingsByCompany sind über den ursprünglichen
        // (ggf. int-)Schlüssel adressiert; PHP normalisiert "100001" und 100001 gleich.

        $companies = [];
        $extrasClaimed = [];
        $linesClaimed = [];

        // Geteilte Kontakte (Partner): Diagnosezeilen der Firma zuordnen, deren
        // Name im Belegtext steht; ohne Nennung bleibt die Zeile beim Partner.
        /** @var array<string, list<string>> $ownersByContact */
        $ownersByContact = [];
        foreach ($resolvedMappings as $key => $mapping) {
            foreach ($mapping->contactIds as $contactId) {
                $ownersByContact[$contactId][] = (string) $key;
            }
        }
        /** @var array<string, string> $mentionOwner Zeilen-Schlüssel → Firmen-Schlüssel laut Belegtext */
        $mentionOwner = [];
        foreach ($lines as $lineKey => $line) {
            $owners = $ownersByContact[$line->contactId] ?? [];
            if (count($owners) < 2) {
                continue;
            }
            $haystack = ' ' . ProductNameMatcher::normalize($line->fullText()) . ' ';
            foreach ($owners as $ownerKey) {
                $name = $resolvedMappings[$ownerKey]->company->normalizedName();
                if ($name !== '' && str_contains($haystack, ' ' . $name . ' ')) {
                    $mentionOwner[$lineKey] = $ownerKey;
                    break;
                }
            }
        }
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
                    $shared = count($ownersByContact[$contactId] ?? []) > 1;
                    $mentioned = $mentionOwner[$lineKey] ?? null;
                    if (! isset($linesClaimed[$lineKey]) && (! $shared || $mentioned === null || $mentioned === (string) $key)) {
                        $seen[] = ['line' => $line, 'remaining' => $remaining[$lineKey], 'shared' => $shared && $mentioned === null, 'microsoft' => $this->isLicenseLine($line)];
                        $linesClaimed[$lineKey] = true;
                    }
                    if ($line->headerOnly || $remaining[$lineKey] < 1.0 || isset($extrasClaimed[$lineKey])) {
                        continue;
                    }
                    if ($this->isLicenseLine($line)) {
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
     * Je Position: Index des Zustands (Periode), dessen Beginn dem Belegdatum
     * am nächsten liegt — getrennt nach exaktem Produkt und allgemein, jeweils
     * nur unter den Perioden der Firmen, die den Kontakt der Position nutzen.
     *
     * @param  list<PeriodState>  $states
     * @param  array<string, InvoiceLine>  $lines
     * @return array<string, array{exact: ?int, any: ?int}>
     */
    private function nearestPeriods(array $states, array $lines): array {
        $nearest = [];
        foreach ($lines as $lineKey => $line) {
            if (! $this->isLicenseLine($line)) {
                continue;
            }
            $text = $this->productText($line);
            $bestExact = null;
            $bestAny = null;
            foreach ($states as $index => $state) {
                $distance = abs($line->voucherDate->diffInDays($state['period']->startsOn));
                if ($bestAny === null || $distance < $bestAny[1]) {
                    $bestAny = [$index, $distance];
                }
                if ($this->matcher->matches($state['period']->entitlement->edition, $text) && ($bestExact === null || $distance < $bestExact[1])) {
                    $bestExact = [$index, $distance];
                }
            }
            $nearest[$lineKey] = ['exact' => $bestExact[0] ?? null, 'any' => $bestAny[0] ?? null];
        }

        return $nearest;
    }

    /**
     * Ein Pass für eine Periode: passende Positionen verbrauchen.
     *
     * @param  PeriodState  $state
     * @param  array<string, InvoiceLine>  $lines
     * @param  array<string, float>  $remaining
     * @param  array<string, array{exact: ?int, any: ?int}>  $nearest
     * @param  array<string, int>  $ownerCount
     * @return PeriodState
     */
    private function allocate(int $index, array $state, ContactMapping $mapping, array $lines, array &$remaining, ReconciliationOptions $options, bool $exactOnly, bool $nearestOnly, array $nearest, array $ownerCount): array {
        $period = $state['period'];
        $windowStart = $period->startsOn->subDays($options->windowBefore);
        $windowEnd = $period->startsOn->addDays($options->windowAfter);
        $contactIds = array_flip($mapping->contactIds);
        $companyName = $mapping->company->normalizedName();

        $candidates = [];
        foreach ($lines as $key => $line) {
            if (! isset($contactIds[$line->contactId]) || $line->headerOnly || $remaining[$key] <= 0.0) {
                continue;
            }
            if ($line->voucherDate->lessThan($windowStart) || $line->voucherDate->greaterThan($windowEnd)) {
                continue;
            }
            if (! $this->isLicenseLine($line)) {
                continue; // eigene Leistung, Hardware, Domain — nie eine Lizenzposition
            }
            $text = $this->productText($line);
            $exact = $this->matcher->matches($period->entitlement->edition, $text);
            if ($exactOnly && ! $exact) {
                continue;
            }
            if (! $exactOnly && ($exact || $this->matchesAnyEdition($text))) {
                // Allgemeine Stufe nur für Positionen OHNE erkennbare Edition
                // („Microsoft 365 Lizenzen"). Eine klar andere Edition (Exchange
                // für eine Standard-Periode) bleibt liegen, statt als „unter
                // Einkauf" in der falschen Periode zu landen.
                continue;
            }
            $mentions = $companyName !== '' && str_contains(' ' . ProductNameMatcher::normalize($line->fullText()) . ' ', ' ' . $companyName . ' ');
            // Allgemeine Positionen eines geteilten Partnerkontakts nur bei Nennung der Firma —
            // sonst wandern die Apps-Zeilen des einen Endkunden in die Premium-Periode des anderen.
            if (! $exact && ($ownerCount[$line->contactId] ?? 1) > 1 && ! $mentions) {
                continue;
            }
            if ($nearestOnly && ($nearest[$key][$exact ? 'exact' : 'any'] ?? null) !== $index) {
                continue;
            }
            $candidates[] = ['key' => $key, 'line' => $line, 'exact' => $exact, 'mentions' => $mentions ? 0 : 1, 'distance' => abs($line->voucherDate->diffInDays($period->startsOn))];
        }
        usort($candidates, static fn(array $a, array $b): int => $a['mentions'] <=> $b['mentions'] ?: $a['distance'] <=> $b['distance'] ?: $a['line']->position <=> $b['line']->position);

        $termMonths = $period->termMonths();
        foreach ($candidates as $candidate) {
            if ($state['needed'] <= 0.0) {
                break;
            }
            $line = $candidate['line'];
            $monthly = $this->isMonthlyPriced($line, $period);
            $monthsPerUnit = $monthly ? 1 : $termMonths;
            $availableMonths = $remaining[$candidate['key']] * $monthsPerUnit;
            $takeMonths = min($availableMonths, $state['needed']);
            if ($takeMonths <= 0.0) {
                continue;
            }
            $remaining[$candidate['key']] -= $takeMonths / $monthsPerUnit;
            $state['needed'] -= $takeMonths;
            $annualUnit = $monthly ? $line->unitNet->times($termMonths) : $line->unitNet;
            $state['matches'][] = ['line' => $line, 'quantity' => $takeMonths / $termMonths, 'months' => $takeMonths, 'exact' => $candidate['exact'], 'monthly' => $monthly, 'annual_unit' => $annualUnit];
            if (! $candidate['exact']) {
                $state['generic'][] = $line->name;
            }
            $state['lowest'] = $state['lowest'] === null || $annualUnit->lessThan($state['lowest']) ? $annualUnit : $state['lowest'];
        }

        return $state;
    }

    /**
     * @param  PeriodState  $state
     * @param  array<string, InvoiceLine>  $lines
     */
    private function finding(array $state, ContactMapping $mapping, array $lines, ReconciliationOptions $options): PeriodFinding {
        $period = $state['period'];
        $matches = $state['matches'];
        $lowest = $state['lowest'];
        $neededMonths = $state['needed'];
        $termMonths = $period->termMonths();
        $needed = $neededMonths / $termMonths; // offener Anteil in Lizenzen
        $genericNote = $state['generic'] === [] ? '' : 'Produkt nur allgemein erkannt: ' . implode(', ', array_unique($state['generic']));
        $withGeneric = static fn(string $note): string => trim($note . ($genericNote !== '' ? ($note !== '' ? ' · ' : '') . $genericNote : ''));

        if ($matches === []) {
            $windowStart = $period->startsOn->subDays($options->windowBefore);
            $windowEnd = $period->startsOn->addDays($options->windowAfter);
            $contactIds = array_flip($mapping->contactIds);
            // „Nur Betrag" nur für Belege OHNE Positionen (Buchungsbelege): Bei
            // einer Rechnung mit Positionen wissen wir, dass keine davon eine
            // Lizenz ist — eine Support- oder Hardware-Rechnung deckt nichts.
            foreach ($lines as $line) {
                if (! isset($contactIds[$line->contactId]) || ! $line->headerOnly) {
                    continue;
                }
                if ($line->voucherDate->lessThan($windowStart) || $line->voucherDate->greaterThan($windowEnd)) {
                    continue;
                }
                if ($line->netTotal()->greaterThanOrEqual($period->fee())) {
                    $label = $line->voucherNumber !== '' ? $line->voucherNumber : $line->voucherId;

                    return new PeriodFinding($period, ReconciliationStatus::CoveredByAmount, [['line' => $line, 'quantity' => 0.0, 'exact' => false]], null, $needed, 'Buchungsbeleg ' . $label . ' ohne Positionen im Fenster, Betrag deckt die Gebühr');
                }
            }

            return new PeriodFinding($period, ReconciliationStatus::Missing, [], null, $needed, 'Keine Rechnung ' . $windowStart->format('d.m.Y') . ' – ' . $windowEnd->format('d.m.Y') . ($mapping->isBilledViaPartner() ? ' bei ' . $mapping->billedVia : ''));
        }

        if ($needed > 0.005) {
            return new PeriodFinding($period, ReconciliationStatus::Partial, $matches, $lowest, $needed, $withGeneric(sprintf('%s von %d Lizenzen berechnet (%s von %d Lizenzmonaten)', $this->formatQuantity($period->quantity - $needed), $period->quantity, $this->formatQuantity($period->licenseMonths() - $neededMonths), $period->licenseMonths())));
        }

        if ($lowest instanceof Money && $lowest->lessThan($period->unitFee)) {
            return new PeriodFinding($period, ReconciliationStatus::Underpriced, $matches, $lowest, 0.0, $withGeneric('Netto/Stück ' . $lowest->format() . ' unter Einkauf ' . $period->unitFee->format()));
        }

        return new PeriodFinding($period, ReconciliationStatus::Covered, $matches, $lowest, 0.0, $genericNote);
    }

    /** Nennt der Text irgendeine Edition des Laufs? Dann ist die Position nicht „allgemein". */
    private function matchesAnyEdition(string $text): bool {
        foreach ($this->editions as $edition) {
            if ($this->matcher->matches($edition, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Monatspreis-Erkennung: Der Stückpreis der Position liegt deutlich unter
     * dem jährlichen Einkaufspreis je Lizenz — dann ist die Menge in Monaten.
     */
    private function isMonthlyPriced(InvoiceLine $line, BillingPeriod $period): bool {
        if ($period->termMonths() <= 1) {
            return false;
        }
        // Artikel mit Einheit „Monat" entscheidet; sonst der Preis.
        $article = $this->catalog->forLine($line);
        if ($article !== null && $article->unit !== '') {
            return $article->isMonthly();
        }

        return $line->unitNet->lessThan($period->unitFee->times(0.5));
    }

    private function formatQuantity(float $quantity): string {
        return rtrim(rtrim(number_format($quantity, 2, ',', ''), '0'), ',');
    }
}
