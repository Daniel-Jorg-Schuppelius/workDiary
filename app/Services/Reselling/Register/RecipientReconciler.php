<?php
/*
 * Created on   : Mon Sep 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecipientReconciler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\PeriodStatus;
use App\Models\{Customer, Organization};
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Services\Reselling\Marketplace\{MarketplaceCompany, NameTokenMatcher, ProductNameMatcher};
use App\Services\Reselling\Mirror\{InvoiceMirror, MirrorLine};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Abgleich je Rechnungsempfänger (Feature 152): stellt die fälligen Perioden
 * aller Abos eines Empfängers (Kunde samt seiner Endkunden) den Lizenz-
 * positionen seiner Rechnungen gegenüber — als Bilanz je Produkt in
 * Lizenzmonaten. Die Bilanz trennt „nur nicht zugeordnet" (freie Positionen
 * vorhanden) von „nie abgerechnet" (mehr Perioden als Positionen) und
 * „zu viel abgerechnet" (mehr Positionen als Perioden). Je offener Periode
 * stehen die Positionen desselben Produkts mit ihrem Verbrauch daneben, damit
 * ein hartnäckiger Fall ohne Blick ins Quellsystem auflösbar ist. Drei
 * Fallen, die je Empfänger unsichtbar wären, werden mitgezeigt: die Rechnung
 * ging an einen anderen Empfänger (Schwesterfirma), die Rechnung wurde
 * storniert, der Endkunde steht im Rechnungstext, sein Abo aber noch ohne
 * Halter im Posteingang. Positionen kommen aus allen Spiegelquellen
 * ({@see InvoiceMirror}); `contact` ist der Empfängerschlüssel der Quelle.
 *
 * @phpstan-type LineRow array{line: MirrorLine, product: string, months: float, licences: float, per_licence: float, linked: float, free: float, periods: list<string>, recipient: string|null, recipient_id: int|null, contact: string, gap: bool}
 * @phpstan-type Candidate array{row: LineRow, distance: int}
 * @phpstan-type PeriodRow array{period: ResalePeriod, subscription: ResaleSubscription, required: float, covered: float, needed: float, product: string, candidates: list<Candidate>, taken: list<Candidate>, foreign: list<Candidate>, voided: list<Candidate>}
 * @phpstan-type CreditNoteRow array{line: MirrorLine, months: float, licences: float, per_licence: float, linked: float, periods: list<string>}
 * @phpstan-type InboxHint array{company: string, subscriptions: list<ResaleSubscription>, mentions: int}
 * @phpstan-type ProductRow array{key: string, label: string, term: int, subscriptions: int, periods: int, required: float, covered: float, invoiced: float, free: float, missing: float, surplus: float, gap_since: CarbonImmutable|null}
 * @phpstan-type OverviewRow array{customer: Customer|null, name: string, subscriptions: int, open: int, partial: int, proposed: int, free: float, missing: float, surplus: float, lines: int}
 */
final class RecipientReconciler {
    private readonly InvoiceMirror $mirror;

    private readonly PeriodLinker $linker;

    public function __construct(?InvoiceMirror $mirror = null, ?PeriodLinker $linker = null, private readonly ProductNameMatcher $matcher = new ProductNameMatcher()) {
        $this->mirror = $mirror ?? app(InvoiceMirror::class);
        $this->linker = $linker ?? new PeriodLinker($this->mirror);
    }

    /**
     * Alle Rechnungsempfänger mit Abos oder Lizenzpositionen, Probleme zuerst.
     *
     * @return list<OverviewRow>
     */
    public function overview(Organization $organization, ?CarbonImmutable $reference = null): array {
        $reference ??= ResalePeriod::today();
        $subscriptions = $this->subscriptions($organization)->with('periods.links')->get();
        /** @var array<int, Collection<int, ResaleSubscription>> $byCustomer */
        $byCustomer = [];
        foreach ($subscriptions as $subscription) {
            $billedTo = $subscription->billedTo();
            if ($billedTo !== null) {
                $byCustomer[$billedTo->id] ??= collect();
                $byCustomer[$billedTo->id]->push($subscription);
            }
        }
        $lines = $this->mirror->linesFor($organization, null);
        /** @var array<int|string, Collection<int, MirrorLine>> $linesByRecipient Kunden-ID oder Empfängerschlüssel der Quelle */
        $linesByRecipient = [];
        foreach ($lines as $line) {
            $key = $line->recipientCustomerId ?? $line->recipientKey;
            $linesByRecipient[$key] ??= collect();
            $linesByRecipient[$key]->push($line);
        }
        $linkedMonths = $this->linker->consumed($lines);

        $customerIds = array_values(array_unique(array_merge(array_keys($byCustomer), array_filter(array_keys($linesByRecipient), 'is_int'))));
        $customers = Customer::query()->whereIn('id', $customerIds)->get(['id', 'name'])->keyBy('id');

        $rows = [];
        foreach (array_unique(array_merge(array_keys($byCustomer), array_keys($linesByRecipient))) as $key) {
            $customer = is_int($key) ? $customers->get($key) : null;
            $recipientLines = $linesByRecipient[$key] ?? collect();
            $analysis = $this->analyze($byCustomer[$key] ?? collect(), $recipientLines, $linkedMonths, $reference);
            $firstLine = $recipientLines->first();
            $name = $customer !== null ? $customer->name : ($firstLine !== null ? (string) $firstLine->recipientName : '');
            $rows[] = [
                'customer' => $customer,
                'name' => $name !== '' ? $name : '—',
                'subscriptions' => ($byCustomer[$key] ?? collect())->count(),
                'open' => $analysis['open'],
                'partial' => $analysis['partial'],
                'proposed' => $analysis['proposed'],
                'free' => $analysis['free'],
                'missing' => $analysis['missing'],
                'surplus' => $analysis['surplus'],
                'lines' => $recipientLines->count(),
            ];
        }
        // Offene Perioden zuerst (fehlender Umsatz), dann freie Positionen, dann Name.
        usort($rows, static fn(array $a, array $b): int => (($b['open'] + $b['partial']) <=> ($a['open'] + $a['partial']))
            ?: ($b['free'] <=> $a['free'])
            ?: strcmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * Abgleich eines Empfängers: Bilanz je Produkt, fällige Perioden mit
     * Kandidaten, alle Lizenzpositionen mit Verbrauch.
     *
     * Gutschrift-Positionen des Empfängers (`credit_notes`) stehen daneben —
     * als negative Bezüge nur von Hand, nie heuristisch (Review 2026-09-10, A3).
     *
     * @return array{subscriptions: Collection<int, ResaleSubscription>, has_source: bool, pending: int, inbox: list<InboxHint>, credit_notes: list<CreditNoteRow>, products: list<ProductRow>, periods: list<PeriodRow>, lines: list<LineRow>, open: int, partial: int, proposed: int, free: float, missing: float, surplus: float}
     */
    public function forCustomer(Organization $organization, Customer $customer, ?CarbonImmutable $reference = null): array {
        $reference ??= ResalePeriod::today();
        $all = $this->subscriptions($organization)->with(['periods.links', 'periods.subscription'])->get();
        $subscriptions = $all->filter(static fn(ResaleSubscription $s): bool => $s->billedTo()?->id === $customer->id)->values();
        // Alle Lizenzpositionen der Organisation: eigene tragen die Bilanz, fremde
        // zeigen Rechnungen an Schwesterfirmen, stornierte den Grund einer Lücke.
        $lines = collect();
        $others = collect();
        foreach ($this->mirror->linesFor($organization, null) as $line) {
            if ($line->recipientCustomerId === $customer->id) {
                $lines->push($line);
            } else {
                $others->push($line);
            }
        }
        // Verwandte Empfänger: Name teilt ein Kern-Token mit dem Empfänger, den
        // Firmennamen seiner Abos oder seinen Endkunden („EcoTec Service" ↔
        // „EcoTec - HLSK"). Ihre stornierten Rechnungen werden mitgezeigt.
        $baseTokens = self::tokens($customer->name);
        $relatedKeys = $baseTokens;
        foreach ($subscriptions as $subscription) {
            $relatedKeys += self::holderTokens($subscription);
        }
        $allVoided = $this->mirror->voidedLines($organization, null);
        $recipientTokens = $this->recipientTokens($others->merge($allVoided), $customer->id);
        $voided = $allVoided->filter(static fn(MirrorLine $line): bool => $line->recipientCustomerId === $customer->id
            || array_intersect_key($recipientTokens[$line->recipientKey] ?? [], $relatedKeys) !== [])->values();
        $pending = $this->mirror->pendingCount($organization, [$customer->id]);
        $linkedMonths = $this->linker->consumed($lines->merge($others));
        $inbox = $this->inboxHints($organization, $lines);

        return ['subscriptions' => $subscriptions, 'has_source' => $this->mirror->coversRecipient($organization, $customer), 'pending' => $pending, 'inbox' => $inbox, 'credit_notes' => $this->creditNoteRows($organization, $customer)]
            + $this->analyze($subscriptions, $lines, $linkedMonths, $reference, $others, $voided, $recipientTokens, $baseTokens, $customer->id);
    }

    /**
     * Gutschrift-Lizenzpositionen des Empfängers mit Lizenzmonaten (Betrag)
     * und dem, was davon schon als negativer Bezug hängt.
     *
     * @return list<CreditNoteRow>
     */
    private function creditNoteRows(Organization $organization, Customer $customer): array {
        $creditLines = $this->mirror->creditNoteLines($organization, [$customer->id]);
        $consumed = $this->linker->consumed($creditLines);
        $rows = [];
        foreach ($creditLines as $line) {
            $split = LicenseMonths::split($line);
            $rows[] = [
                'line' => $line,
                'months' => $split['licences'] * $split['months'],
                'licences' => $split['licences'],
                'per_licence' => $split['months'],
                'linked' => abs($consumed[$line->identity()]['months'] ?? 0.0),
                'periods' => $consumed[$line->identity()]['periods'] ?? [],
            ];
        }
        usort($rows, static fn(array $a, array $b): int => ($b['line']->voucherDate <=> $a['line']->voucherDate) ?: ($a['line']->position <=> $b['line']->position));

        return $rows;
    }

    /**
     * Kern-Tokens (≥ 4 Zeichen) des Empfängernamens je fremdem Empfängerschlüssel.
     *
     * @param  Collection<int, MirrorLine>  $lines
     * @return array<string, array<string, true>>
     */
    private function recipientTokens(Collection $lines, int $ownCustomerId): array {
        $tokens = [];
        foreach ($lines as $line) {
            if ($line->recipientCustomerId === $ownCustomerId || isset($tokens[$line->recipientKey])) {
                continue;
            }
            $set = self::tokens((string) $line->recipientName);
            if ($set !== []) {
                $tokens[$line->recipientKey] = $set;
            }
        }

        return $tokens;
    }

    /** @return array<string, true> */
    private static function holderTokens(ResaleSubscription $subscription): array {
        $tokens = self::tokens((string) $subscription->company_name);
        if ($subscription->foreignCustomer !== null) {
            $tokens += self::tokens($subscription->foreignCustomer->name);
        }

        return $tokens;
    }

    /** @return array<string, true> */
    private static function tokens(string $name): array {
        $tokens = [];
        foreach (NameTokenMatcher::significantTokens($name) as $token) {
            if (mb_strlen($token) >= 4) {
                $tokens[$token] = true;
            }
        }

        return $tokens;
    }

    /**
     * Abos ohne Halter, deren Firmenname in den Rechnungstexten dieses
     * Empfängers vorkommt — der Partner rechnet den Endkunden ab, das Abo
     * wartet aber noch im Posteingang.
     *
     * @param  Collection<int, MirrorLine>  $lines
     * @return list<InboxHint>
     */
    private function inboxHints(Organization $organization, Collection $lines): array {
        $texts = [];
        foreach ($lines as $line) {
            $voucher = $line->sourceKey . ':' . $line->voucherKey;
            $texts[$voucher] ??= trim((string) $line->voucherText);
            $texts[$voucher] .= ' ' . $line->text();
        }
        if ($texts === []) {
            return [];
        }
        /** @var array<string, InboxHint> $hints */
        $hints = [];
        $unassigned = ResaleSubscription::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->unassigned()->orderBy('label')->get();
        foreach ($unassigned as $subscription) {
            $name = trim((string) $subscription->company_name);
            if ($name === '') {
                continue;
            }
            $key = MarketplaceCompany::normalizeName($name);
            if (! isset($hints[$key])) {
                $mentions = 0;
                foreach ($texts as $text) {
                    if (NameTokenMatcher::matches($name, $text)) {
                        $mentions++;
                    }
                }
                if ($mentions === 0) {
                    continue;
                }
                $hints[$key] = ['company' => $name, 'subscriptions' => [], 'mentions' => $mentions];
            }
            $hints[$key]['subscriptions'][] = $subscription;
        }
        $rows = array_values($hints);
        usort($rows, static fn(array $a, array $b): int => ($b['mentions'] <=> $a['mentions']) ?: strcmp($a['company'], $b['company']));

        return $rows;
    }

    /**
     * Produktschlüssel einer Position — dieselbe Regel wie
     * {@see ResaleSubscription::productKey()}: Lexoffice-Artikel `art:<id>`;
     * lokale Artikel und Positionen ohne Artikel laufen über das Abo, dessen
     * Artikel bzw. Name zur Position passt, sonst über den normalisierten Namen.
     *
     * @param  array<int, string>  $productBySubscription
     * @param  Collection<int, ResaleSubscription>  $subscriptions
     */
    private function lineProductKey(MirrorLine $line, array $productBySubscription, Collection $subscriptions): string {
        $lexofficeArticleId = $line->lexofficeArticleId();
        if ($lexofficeArticleId !== null) {
            return 'art:' . $lexofficeArticleId;
        }
        foreach ($subscriptions as $subscription) {
            if (isset($productBySubscription[$subscription->id]) && LinkProposer::matchesProductOf($subscription, $line, $this->matcher)) {
                return $productBySubscription[$subscription->id];
            }
        }

        return 'name:' . ProductNameMatcher::normalize($line->label());
    }

    /**
     * @param  Collection<int, ResaleSubscription>  $subscriptions
     * @param  Collection<int, MirrorLine>  $lines
     * @param  array<string, array{months: float, periods: list<string>}>  $linkedMonths
     * @param  Collection<int, MirrorLine>  $others  Lizenzpositionen anderer Empfänger
     * @param  Collection<int, MirrorLine>  $voided  stornierte Lizenzpositionen dieses Empfängers und verwandter Empfänger
     * @param  array<string, array<string, true>>  $recipientTokens  Empfängerschlüssel → Kern-Tokens des Empfängernamens
     * @param  array<string, true>  $baseTokens  Kern-Tokens des Empfängers selbst
     * @return array{products: list<ProductRow>, periods: list<PeriodRow>, lines: list<LineRow>, open: int, partial: int, proposed: int, free: float, missing: float, surplus: float}
     */
    private function analyze(Collection $subscriptions, Collection $lines, array $linkedMonths, CarbonImmutable $reference, ?Collection $others = null, ?Collection $voided = null, array $recipientTokens = [], array $baseTokens = [], ?int $ownCustomerId = null): array {
        /** @var array<int, string> $articleNames Lexoffice-Artikel-ID → Name */
        $articleNames = [];
        foreach ($lines as $line) {
            $lexofficeArticleId = $line->lexofficeArticleId();
            if ($lexofficeArticleId !== null && $line->articleName !== null) {
                $articleNames[$lexofficeArticleId] = $line->articleName;
            }
        }
        /** @var array<int, string> $productBySubscription */
        $productBySubscription = [];
        /** @var array<string, string> $labels */
        $labels = [];
        foreach ($subscriptions as $subscription) {
            $key = $subscription->productKey($articleNames);
            $productBySubscription[$subscription->id] = $key;
            $labels[$key] ??= $subscription->lexofficeArticle !== null ? $subscription->lexofficeArticle->name : $subscription->label;
        }
        $productOf = fn(MirrorLine $line): string => $this->lineProductKey($line, $productBySubscription, $subscriptions);

        /** @var list<LineRow> $lineRows */
        $lineRows = [];
        /** @var array<string, float> $invoiced */
        $invoiced = [];
        /** @var array<string, float> $freeByProduct */
        $freeByProduct = [];
        foreach ($lines as $line) {
            $split = LicenseMonths::split($line);
            $months = $split['licences'] * $split['months'];
            $identity = $line->identity();
            $linked = $linkedMonths[$identity]['months'] ?? 0.0;
            $key = $productOf($line);
            $labels[$key] ??= $line->label();
            $lineRow = ['line' => $line, 'product' => $key, 'months' => $months, 'licences' => $split['licences'], 'per_licence' => $split['months'], 'linked' => $linked, 'free' => max(0.0, $months - $linked), 'periods' => $linkedMonths[$identity]['periods'] ?? [], 'recipient' => null, 'recipient_id' => null, 'contact' => $line->recipientKey, 'gap' => false];
            $lineRows[] = $lineRow;
            $invoiced[$key] = ($invoiced[$key] ?? 0.0) + $months;
            $freeByProduct[$key] = ($freeByProduct[$key] ?? 0.0) + $lineRow['free'];
        }
        usort($lineRows, static fn(array $a, array $b): int => ($b['line']->voucherDate <=> $a['line']->voucherDate) ?: strcmp($a['line']->sourceKey, $b['line']->sourceKey) ?: ($a['line']->morphId <=> $b['line']->morphId));
        /** @var list<LineRow> $otherRows Freie Positionen anderer Empfänger */
        $otherRows = [];
        foreach ($others ?? collect() as $line) {
            $split = LicenseMonths::split($line);
            $months = $split['licences'] * $split['months'];
            $identity = $line->identity();
            $linked = $linkedMonths[$identity]['months'] ?? 0.0;
            if ($months - $linked <= 0.001) {
                continue;
            }
            $recipient = trim((string) $line->recipientName);
            $otherRows[] = ['line' => $line, 'product' => $productOf($line), 'months' => $months, 'licences' => $split['licences'], 'per_licence' => $split['months'], 'linked' => $linked, 'free' => $months - $linked, 'periods' => $linkedMonths[$identity]['periods'] ?? [], 'recipient' => $recipient !== '' ? $recipient : null, 'recipient_id' => $line->recipientCustomerId, 'contact' => $line->recipientKey, 'gap' => false];
        }
        /** @var list<LineRow> $voidedRows */
        $voidedRows = [];
        foreach ($voided ?? collect() as $line) {
            $split = LicenseMonths::split($line);
            $own = $ownCustomerId !== null && $line->recipientCustomerId === $ownCustomerId;
            $recipient = trim((string) $line->recipientName);
            $voidedRows[] = ['line' => $line, 'product' => $productOf($line), 'months' => $split['licences'] * $split['months'], 'licences' => $split['licences'], 'per_licence' => $split['months'], 'linked' => 0.0, 'free' => 0.0, 'periods' => [], 'recipient' => $own || $recipient === '' ? null : $recipient, 'recipient_id' => null, 'contact' => $line->recipientKey, 'gap' => false];
        }

        /** @var array<string, int> $subscriptionCount */
        $subscriptionCount = [];
        /** @var array<string, int> $termByProduct Monate je Lizenz und Periode (Intervall des Abos) */
        $termByProduct = [];
        /** @var array<string, int> $periodCount */
        $periodCount = [];
        /** @var array<string, float> $requiredByProduct */
        $requiredByProduct = [];
        /** @var array<string, float> $coveredByProduct */
        $coveredByProduct = [];
        /** @var list<PeriodRow> $periodRows */
        $periodRows = [];
        $open = $partial = $proposed = 0;
        foreach ($subscriptions as $subscription) {
            $key = $productBySubscription[$subscription->id];
            $subscriptionCount[$key] = ($subscriptionCount[$key] ?? 0) + 1;
            foreach ($subscription->periods as $period) {
                if ($period->starts_on->greaterThan($reference) || in_array($period->status, [PeriodStatus::Waived, PeriodStatus::Disputed], true)) {
                    continue;
                }
                $required = $period->requiredMonths();
                $covered = $period->coveredMonths();
                $termByProduct[$key] ??= $period->termMonths();
                $periodCount[$key] = ($periodCount[$key] ?? 0) + 1;
                $requiredByProduct[$key] = ($requiredByProduct[$key] ?? 0.0) + $required;
                $coveredByProduct[$key] = ($coveredByProduct[$key] ?? 0.0) + $covered;
                if ($period->status === PeriodStatus::Billed && $period->isProposedOnly()) {
                    $proposed++;
                }
                $needed = max(0.0, $required - $covered);
                if ($needed <= 0.001) {
                    continue;
                }
                if ($period->status === PeriodStatus::Partial) {
                    $partial++;
                } else {
                    $open++;
                }
                $periodRows[] = ['period' => $period, 'subscription' => $subscription, 'required' => $required, 'covered' => $covered, 'needed' => $needed, 'product' => $key, 'candidates' => [], 'taken' => [], 'foreign' => [], 'voided' => []];
            }
        }
        usort($periodRows, static fn(array $a, array $b): int => ($a['period']->starts_on <=> $b['period']->starts_on) ?: ($a['subscription']->id <=> $b['subscription']->id));

        foreach ($periodRows as $index => $row) {
            $start = $row['period']->starts_on;
            foreach ($lineRows as $lineRow) {
                if ($lineRow['product'] !== $row['product']) {
                    continue;
                }
                // Bezug = Beginn des Leistungszeitraums, sonst Rechnungsdatum; nur im Fenster der
                // Periode (bei Mehrperioden-Positionen verlängert) — oder der Leistungszeitraum
                // deckt den Periodenbeginn.
                $date = LicenseMonths::referenceDate($lineRow['line']);
                if ($date === null || ! LinkProposer::inWindow($row['period'], $lineRow['line'])) {
                    continue;
                }
                $distance = (int) abs($date->diffInDays($start));
                $periodRows[$index][$lineRow['free'] > 0.001 ? 'candidates' : 'taken'][] = ['row' => $lineRow, 'distance' => $distance];
            }
            // Verwandt je Periode: Empfänger, Firmenname des Abos oder sein Endkunde
            // teilen ein Kern-Token mit dem Namen des anderen Empfängers.
            $periodTokens = $baseTokens + self::holderTokens($row['subscription']);
            // Unverwandter Empfänger, dessen freie Positionen in der Laufzeit der Periode
            // zusammen genau die Periode ergeben (Delta Allround: 3 × 12 für Utes 3er-Vertrag):
            // ein früherer Kontoinhaber. Nur bei mehr als einer Lizenz — „12 Monat" passt überall.
            /** @var array<string, float> $sumsByContact */
            $sumsByContact = [];
            if ($row['required'] > 12.001) {
                foreach ($otherRows as $lineRow) {
                    if ($lineRow['product'] !== $row['product']) {
                        continue;
                    }
                    $date = LicenseMonths::referenceDate($lineRow['line']);
                    if ($date === null || abs($date->diffInDays($start)) > LinkProposer::WINDOW_BEFORE) {
                        continue;
                    }
                    $sumsByContact[$lineRow['contact']] = ($sumsByContact[$lineRow['contact']] ?? 0.0) + $lineRow['free'];
                }
            }
            // Summe = ganze Periode (nicht nur der Rest), dicht am Periodenbeginn — sonst
            // trifft jede Firma mit zufällig gleich vielen Lizenzen.
            $formerHolders = array_keys(array_filter($sumsByContact, static fn(float $sum): bool => abs($sum - $row['required']) < 0.001));
            foreach ([['foreign', $otherRows], ['voided', $voidedRows]] as [$bucket, $rows]) {
                foreach ($rows as $lineRow) {
                    if ($lineRow['product'] !== $row['product']) {
                        continue;
                    }
                    $date = LicenseMonths::referenceDate($lineRow['line']);
                    if ($date === null || ! LinkProposer::inWindow($row['period'], $lineRow['line'])) {
                        continue;
                    }
                    $distance = (int) abs($date->diffInDays($start));
                    $related = isset($recipientTokens[$lineRow['contact']]) && array_intersect_key($recipientTokens[$lineRow['contact']], $periodTokens) !== [];
                    $ownLine = $ownCustomerId !== null && $lineRow['line']->recipientCustomerId === $ownCustomerId;
                    // Stornos nur dicht am Periodenbeginn — ein Storno aus dem übernächsten Jahr erklärt nichts.
                    if ($bucket === 'voided' && ((! $related && ! $ownLine) || $distance > LinkProposer::WINDOW_BEFORE)) {
                        continue;
                    }
                    // Fremde Positionen nur von verwandten Empfängern — oder von einem
                    // früheren Kontoinhaber (Summe seiner Positionen = Periode) — oder, wenn
                    // der Empfänger selbst nichts Freies hat, bei exakt passender ungewöhnlicher
                    // Menge (mehr als eine Lizenz) dicht am Periodenbeginn: Rechnung an die
                    // falsche Firma. „12 Monat" passt sonst überall.
                    $former = in_array($lineRow['contact'], $formerHolders, true) && $distance <= LinkProposer::WINDOW_BEFORE;
                    if ($bucket === 'foreign' && ! $related && ! $former && ($periodRows[$index]['candidates'] !== [] || $row['needed'] <= 12.001 || abs($lineRow['free'] - $row['needed']) > 0.001 || $distance > LinkProposer::SHARED_NEAREST_DAYS)) {
                        continue;
                    }
                    $periodRows[$index][$bucket][] = ['row' => $lineRow, 'distance' => $distance];
                }
            }
            foreach (['candidates', 'taken', 'foreign', 'voided'] as $bucket) {
                usort($periodRows[$index][$bucket], static fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);
            }
        }

        // Freie Position, deren Bezugsdatum keine Periode des Produkts (welchen Status auch immer)
        // enthält: ab dort fehlt ein Abo im Register — typisch ein Vertrag, den der Anbieter-Export
        // nicht mehr liefert (nur gekündigte Verträge exportiert, laufende fehlen).
        /** @var array<string, list<array{0: CarbonImmutable, 1: CarbonImmutable}>> $ranges */
        $ranges = [];
        foreach ($subscriptions as $subscription) {
            $key = $productBySubscription[$subscription->id];
            foreach ($subscription->periods as $period) {
                $ranges[$key][] = [$period->starts_on->subDays(LinkProposer::WINDOW_BEFORE), $period->ends_on];
            }
        }
        /** @var array<string, CarbonImmutable> $gapSince */
        $gapSince = [];
        foreach ($lineRows as $index => $lineRow) {
            if ($lineRow['free'] <= 0.001) {
                continue;
            }
            $date = LicenseMonths::referenceDate($lineRow['line']);
            if ($date === null) {
                continue;
            }
            $covered = false;
            foreach ($ranges[$lineRow['product']] ?? [] as [$from, $to]) {
                if (! $date->lessThan($from) && ! $date->greaterThan($to)) {
                    $covered = true;
                    break;
                }
            }
            if ($covered) {
                continue;
            }
            $lineRows[$index]['gap'] = true;
            if (! isset($gapSince[$lineRow['product']]) || $date->lessThan($gapSince[$lineRow['product']])) {
                $gapSince[$lineRow['product']] = $date;
            }
        }

        /** @var list<ProductRow> $productRows */
        $productRows = [];
        $free = $missing = $surplus = 0.0;
        foreach ($labels as $key => $label) {
            $productRequired = $requiredByProduct[$key] ?? 0.0;
            $productCovered = $coveredByProduct[$key] ?? 0.0;
            $productFree = $freeByProduct[$key] ?? 0.0;
            $openMonths = max(0.0, $productRequired - $productCovered);
            $productMissing = max(0.0, $openMonths - $productFree);
            $productSurplus = max(0.0, $productFree - $openMonths);
            $productRows[] = [
                'key' => $key,
                'label' => $label,
                'term' => $termByProduct[$key] ?? 12,
                'subscriptions' => $subscriptionCount[$key] ?? 0,
                'periods' => $periodCount[$key] ?? 0,
                'required' => $productRequired,
                'covered' => $productCovered,
                'invoiced' => $invoiced[$key] ?? 0.0,
                'free' => $productFree,
                'missing' => $productMissing,
                'surplus' => $productSurplus,
                'gap_since' => $gapSince[$key] ?? null,
            ];
            $free += $productFree;
            $missing += $productMissing;
            $surplus += $productSurplus;
        }
        usort($productRows, static fn(array $a, array $b): int => (($b['missing'] + $b['surplus']) <=> ($a['missing'] + $a['surplus'])) ?: strcmp($a['label'], $b['label']));

        return ['products' => $productRows, 'periods' => $periodRows, 'lines' => $lineRows, 'open' => $open, 'partial' => $partial, 'proposed' => $proposed, 'free' => $free, 'missing' => $missing, 'surplus' => $surplus];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<ResaleSubscription> */
    private function subscriptions(Organization $organization): \Illuminate\Database\Eloquent\Builder {
        return ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('is_own_holding', false)
            ->where(static fn($q) => $q->whereNotNull('customer_id')->orWhereNotNull('foreign_customer_id'))
            ->with(['customer:id,name', 'foreignCustomer:id,name,customer_id', 'foreignCustomer.customer:id,name', 'lexofficeArticle:id,name', 'article:id,name'])
            ->orderBy('label')->orderBy('starts_on');
    }
}
