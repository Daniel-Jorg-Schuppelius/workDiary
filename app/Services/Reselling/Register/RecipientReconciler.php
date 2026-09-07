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
use App\Models\{Customer, ExternalReference, LexofficeVoucher, LexofficeVoucherLine, Organization};
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Reselling\Marketplace\{MarketplaceCompany, NameTokenMatcher, ProductNameMatcher};
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
 * ein hartnäckiger Fall ohne Blick nach Lexoffice auflösbar ist. Drei
 * Fallen, die je Empfänger unsichtbar wären, werden mitgezeigt: die Rechnung
 * ging an einen anderen Empfänger (Schwesterfirma), die Rechnung wurde
 * storniert, der Endkunde steht im Rechnungstext, sein Abo aber noch ohne
 * Halter im Posteingang.
 *
 * @phpstan-type LineRow array{line: LexofficeVoucherLine, product: string, months: float, licences: float, per_licence: float, linked: float, free: float, periods: list<string>, recipient: string|null, recipient_id: int|null, contact: string}
 * @phpstan-type Candidate array{row: LineRow, distance: int}
 * @phpstan-type PeriodRow array{period: ResalePeriod, subscription: ResaleSubscription, required: float, covered: float, needed: float, product: string, candidates: list<Candidate>, taken: list<Candidate>, foreign: list<Candidate>, voided: list<Candidate>}
 * @phpstan-type InboxHint array{company: string, subscriptions: list<ResaleSubscription>, mentions: int}
 * @phpstan-type ProductRow array{key: string, label: string, subscriptions: int, periods: int, required: float, covered: float, invoiced: float, free: float, missing: float, surplus: float}
 * @phpstan-type OverviewRow array{customer: Customer|null, name: string, subscriptions: int, open: int, partial: int, proposed: int, free: float, missing: float, surplus: float, lines: int}
 */
final class RecipientReconciler {
    public function __construct(private readonly LicenseArticleClassifier $classifier = new LicenseArticleClassifier(), private readonly ProductNameMatcher $matcher = new ProductNameMatcher()) {}

    /**
     * Alle Rechnungsempfänger mit Abos oder Lizenzpositionen, Probleme zuerst.
     *
     * @return list<OverviewRow>
     */
    public function overview(Organization $organization, ?CarbonImmutable $reference = null): array {
        $reference ??= CarbonImmutable::today();
        $subscriptions = $this->subscriptions($organization)->with('periods.links')->get();
        $contactMap = $this->contactMap($organization);
        /** @var array<int, Collection<int, ResaleSubscription>> $byCustomer */
        $byCustomer = [];
        foreach ($subscriptions as $subscription) {
            $billedTo = $subscription->billedTo();
            if ($billedTo !== null) {
                $byCustomer[$billedTo->id] ??= collect();
                $byCustomer[$billedTo->id]->push($subscription);
            }
        }
        $lines = $this->licenseLines($organization, []);
        /** @var array<int|string, Collection<int, LexofficeVoucherLine>> $linesByRecipient Kunden-ID oder Kontakt-ID */
        $linesByRecipient = [];
        foreach ($lines as $line) {
            $contact = (string) $line->voucher->contact_external_id;
            $key = $contactMap['byContact'][$contact] ?? $line->voucher->customer_id ?? ('contact:' . $contact);
            $linesByRecipient[$key] ??= collect();
            $linesByRecipient[$key]->push($line);
        }
        $linkedMonths = $this->linkedMonths($organization, self::ids($lines));

        $customerIds = array_values(array_unique(array_merge(array_keys($byCustomer), array_filter(array_keys($linesByRecipient), 'is_int'))));
        $customers = Customer::query()->whereIn('id', $customerIds)->get(['id', 'name'])->keyBy('id');

        $rows = [];
        foreach (array_unique(array_merge(array_keys($byCustomer), array_keys($linesByRecipient))) as $key) {
            $customer = is_int($key) ? $customers->get($key) : null;
            $recipientLines = $linesByRecipient[$key] ?? collect();
            $analysis = $this->analyze($byCustomer[$key] ?? collect(), $recipientLines, $linkedMonths, $reference);
            $firstLine = $recipientLines->first();
            $name = $customer !== null ? $customer->name : ($firstLine !== null ? (string) $firstLine->voucher->recipient_name : '');
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
     * @return array{subscriptions: Collection<int, ResaleSubscription>, contacts: list<string>, pending: int, inbox: list<InboxHint>, products: list<ProductRow>, periods: list<PeriodRow>, lines: list<LineRow>, open: int, partial: int, proposed: int, free: float, missing: float, surplus: float}
     */
    public function forCustomer(Organization $organization, Customer $customer, ?CarbonImmutable $reference = null): array {
        $reference ??= CarbonImmutable::today();
        $all = $this->subscriptions($organization)->with(['periods.links', 'periods.subscription'])->get();
        $subscriptions = $all->filter(static fn(ResaleSubscription $s): bool => $s->billedTo()?->id === $customer->id)->values();
        $contactMap = $this->contactMap($organization)['byContact'];
        $contacts = [];
        foreach ($contactMap as $contact => $customerId) {
            if ($customerId === $customer->id) {
                $contacts[] = (string) $contact;
            }
        }
        $own = array_flip($contacts);
        $names = $this->recipientNames($organization, $contactMap);
        $contactTokens = $this->contactTokens($names, $own);
        $related = $this->relatedContacts($customer, $subscriptions, $contactTokens);
        // Alle Lizenzpositionen der Organisation: eigene tragen die Bilanz, fremde
        // zeigen Rechnungen an Schwesterfirmen, stornierte den Grund einer Lücke.
        $lines = collect();
        $others = collect();
        foreach ($this->licenseLines($organization, []) as $line) {
            if (isset($own[(string) $line->voucher->contact_external_id])) {
                $lines->push($line);
            } else {
                $others->push($line);
            }
        }
        $voided = $this->licenseLines($organization, array_merge($contacts, $related), true);
        $pending = $contacts === [] ? 0 : LexofficeVoucher::query()->whereIn('contact_external_id', $contacts)
            ->where('voucher_type', 'invoice')->where('archived', false)->whereNotIn('voucher_status', ['draft', 'voided'])
            ->whereNull('lines_synced_at')->count();
        $linkedMonths = $this->linkedMonths($organization, array_merge(self::ids($lines), self::ids($others)));
        $inbox = $this->inboxHints($organization, $lines);

        return ['subscriptions' => $subscriptions, 'contacts' => $contacts, 'pending' => $pending, 'inbox' => $inbox]
            + $this->analyze($subscriptions, $lines, $linkedMonths, $reference, $others, $voided, $names, $contactTokens, self::tokens($customer->name), $own, $contactMap);
    }

    /**
     * Kern-Tokens (≥ 4 Zeichen) je fremdem Lexoffice-Kontakt.
     *
     * @param  array<string, string>  $names
     * @param  array<string, int>  $own
     * @return array<string, array<string, true>>
     */
    private function contactTokens(array $names, array $own): array {
        $tokens = [];
        foreach ($names as $contact => $name) {
            if (isset($own[$contact])) {
                continue;
            }
            $set = self::tokens($name);
            if ($set !== []) {
                $tokens[(string) $contact] = $set;
            }
        }

        return $tokens;
    }

    /**
     * Verwandte Empfänger: Kontakte, deren Name ein Kern-Token mit dem
     * Empfänger, den Firmennamen seiner Abos oder seinen Endkunden teilt
     * („EcoTec Service" ↔ „EcoTec - HLSK", „Steuerbüro Kaik" ↔ „Steuerberater
     * C. Kaik"). Ihre stornierten Rechnungen werden mitgezeigt.
     *
     * @param  Collection<int, ResaleSubscription>  $subscriptions
     * @param  array<string, array<string, true>>  $contactTokens
     * @return list<string>
     */
    private function relatedContacts(Customer $customer, Collection $subscriptions, array $contactTokens): array {
        $keys = self::tokens($customer->name);
        foreach ($subscriptions as $subscription) {
            $keys += self::holderTokens($subscription);
        }
        $contacts = [];
        foreach ($contactTokens as $contact => $tokens) {
            if (array_intersect_key($tokens, $keys) !== []) {
                $contacts[] = $contact;
            }
        }

        return $contacts;
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
     * @param  Collection<int, LexofficeVoucherLine>  $lines
     * @return list<InboxHint>
     */
    private function inboxHints(Organization $organization, Collection $lines): array {
        $texts = [];
        foreach ($lines as $line) {
            $texts[$line->voucher_id] ??= trim((string) $line->voucher->voucher_text);
            $texts[$line->voucher_id] .= ' ' . $line->text();
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
     * Anzeigename je Lexoffice-Kontakt: der verknüpfte Kunde, sonst der
     * Empfängername des letzten Belegs.
     *
     * @param  array<string, int>  $contactMap
     * @return array<string, string>
     */
    private function recipientNames(Organization $organization, array $contactMap): array {
        $names = [];
        $customers = Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereIn('id', array_values(array_unique($contactMap)))->pluck('name', 'id');
        foreach ($contactMap as $contact => $customerId) {
            $name = $customers->get($customerId);
            if (is_string($name)) {
                $names[$contact] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  Collection<int, ResaleSubscription>  $subscriptions
     * @param  Collection<int, LexofficeVoucherLine>  $lines
     * @param  array<int, array{months: float, periods: list<string>}>  $linkedMonths
     * @param  Collection<int, LexofficeVoucherLine>  $others  Lizenzpositionen anderer Empfänger
     * @param  Collection<int, LexofficeVoucherLine>  $voided  stornierte Lizenzpositionen dieses Empfängers
     * @param  array<string, string>  $names  Kontakt → Empfängername
     * @param  array<string, array<string, true>>  $contactTokens  Kontakt → Kern-Tokens des Empfängernamens
     * @param  array<string, true>  $baseTokens  Kern-Tokens des Empfängers selbst
     * @param  array<string, int>  $own  eigene Kontakte
     * @param  array<string, int>  $customerIds  Kontakt → Kunde (für „Halter auf diesen Kunden setzen")
     * @return array{products: list<ProductRow>, periods: list<PeriodRow>, lines: list<LineRow>, open: int, partial: int, proposed: int, free: float, missing: float, surplus: float}
     */
    private function analyze(Collection $subscriptions, Collection $lines, array $linkedMonths, CarbonImmutable $reference, ?Collection $others = null, ?Collection $voided = null, array $names = [], array $contactTokens = [], array $baseTokens = [], array $own = [], array $customerIds = []): array {
        /** @var array<int, string> $articleNames */
        $articleNames = [];
        foreach ($lines as $line) {
            if ($line->article !== null) {
                $articleNames[$line->article->id] = $line->article->name;
            }
        }
        /** @var array<int, string> $productBySubscription */
        $productBySubscription = [];
        /** @var array<string, string> $labels */
        $labels = [];
        foreach ($subscriptions as $subscription) {
            $key = $this->subscriptionProduct($subscription, $articleNames);
            $productBySubscription[$subscription->id] = $key;
            $labels[$key] ??= $subscription->lexofficeArticle !== null ? $subscription->lexofficeArticle->name : $subscription->label;
        }

        /** @var list<LineRow> $lineRows */
        $lineRows = [];
        /** @var array<string, float> $invoiced */
        $invoiced = [];
        /** @var array<string, float> $freeByProduct */
        $freeByProduct = [];
        foreach ($lines as $line) {
            $split = LicenseMonths::split($line);
            $months = $split['licences'] * $split['months'];
            $linked = $linkedMonths[$line->id]['months'] ?? 0.0;
            $key = 'art:' . $line->lexoffice_article_id;
            $labels[$key] ??= $line->article !== null ? $line->article->name : $line->name;
            $lineRow = ['line' => $line, 'product' => $key, 'months' => $months, 'licences' => $split['licences'], 'per_licence' => $split['months'], 'linked' => $linked, 'free' => max(0.0, $months - $linked), 'periods' => $linkedMonths[$line->id]['periods'] ?? [], 'recipient' => null, 'recipient_id' => null, 'contact' => (string) $line->voucher->contact_external_id];
            $lineRows[] = $lineRow;
            $invoiced[$key] = ($invoiced[$key] ?? 0.0) + $months;
            $freeByProduct[$key] = ($freeByProduct[$key] ?? 0.0) + $lineRow['free'];
        }
        usort($lineRows, static fn(array $a, array $b): int => ($b['line']->voucher->voucher_date <=> $a['line']->voucher->voucher_date) ?: ($a['line']->id <=> $b['line']->id));
        /** @var list<LineRow> $otherRows Freie Positionen anderer Empfänger */
        $otherRows = [];
        foreach ($others ?? collect() as $line) {
            $split = LicenseMonths::split($line);
            $months = $split['licences'] * $split['months'];
            $linked = $linkedMonths[$line->id]['months'] ?? 0.0;
            if ($months - $linked <= 0.001) {
                continue;
            }
            $contact = (string) $line->voucher->contact_external_id;
            $recipient = $names[$contact] ?? trim((string) $line->voucher->recipient_name);
            $otherRows[] = ['line' => $line, 'product' => 'art:' . $line->lexoffice_article_id, 'months' => $months, 'licences' => $split['licences'], 'per_licence' => $split['months'], 'linked' => $linked, 'free' => $months - $linked, 'periods' => $linkedMonths[$line->id]['periods'] ?? [], 'recipient' => $recipient !== '' ? $recipient : null, 'recipient_id' => $customerIds[$contact] ?? null, 'contact' => $contact];
        }
        /** @var list<LineRow> $voidedRows */
        $voidedRows = [];
        foreach ($voided ?? collect() as $line) {
            $contact = (string) $line->voucher->contact_external_id;
            $split = LicenseMonths::split($line);
            $voidedRows[] = ['line' => $line, 'product' => 'art:' . $line->lexoffice_article_id, 'months' => $split['licences'] * $split['months'], 'licences' => $split['licences'], 'per_licence' => $split['months'], 'linked' => 0.0, 'free' => 0.0, 'periods' => [], 'recipient' => isset($own[$contact]) ? null : ($names[$contact] ?? null), 'recipient_id' => null, 'contact' => $contact];
        }

        /** @var array<string, int> $subscriptionCount */
        $subscriptionCount = [];
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
            $windowStart = $start->subDays(LinkProposer::WINDOW_BEFORE);
            $windowEnd = $start->addDays(LinkProposer::WINDOW_AFTER);
            foreach ($lineRows as $lineRow) {
                if ($lineRow['product'] !== $row['product']) {
                    continue;
                }
                // Bezug = Beginn des Leistungszeitraums, sonst Rechnungsdatum; nur im Fenster der Periode.
                $date = LicenseMonths::referenceDate($lineRow['line']);
                if ($date === null || $date->lessThan($windowStart) || $date->greaterThan($windowEnd)) {
                    continue;
                }
                $distance = (int) abs($date->diffInDays($start));
                $periodRows[$index][$lineRow['free'] > 0.001 ? 'candidates' : 'taken'][] = ['row' => $lineRow, 'distance' => $distance];
            }
            // Verwandt je Periode: Empfänger, Firmenname des Abos oder sein Endkunde
            // teilen ein Kern-Token mit dem Namen des anderen Empfängers.
            $periodTokens = $baseTokens + self::holderTokens($row['subscription']);
            foreach ([['foreign', $otherRows], ['voided', $voidedRows]] as [$bucket, $rows]) {
                foreach ($rows as $lineRow) {
                    if ($lineRow['product'] !== $row['product']) {
                        continue;
                    }
                    $date = LicenseMonths::referenceDate($lineRow['line']);
                    if ($date === null || $date->lessThan($windowStart) || $date->greaterThan($windowEnd)) {
                        continue;
                    }
                    $distance = (int) abs($date->diffInDays($start));
                    $related = isset($contactTokens[$lineRow['contact']]) && array_intersect_key($contactTokens[$lineRow['contact']], $periodTokens) !== [];
                    // Stornos nur dicht am Periodenbeginn — ein Storno aus dem übernächsten Jahr erklärt nichts.
                    if ($bucket === 'voided' && ((! $related && ! isset($own[$lineRow['contact']])) || $distance > LinkProposer::WINDOW_BEFORE)) {
                        continue;
                    }
                    // Fremde Positionen nur von verwandten Empfängern — oder, wenn der
                    // Empfänger selbst nichts Freies hat, bei exakt passender ungewöhnlicher
                    // Menge (mehr als eine Lizenz) dicht am Periodenbeginn: Rechnung an die
                    // falsche Firma. „12 Monat" passt sonst überall.
                    if ($bucket === 'foreign' && ! $related && ($periodRows[$index]['candidates'] !== [] || $row['needed'] <= 12.001 || abs($lineRow['free'] - $row['needed']) > 0.001 || $distance > LinkProposer::SHARED_NEAREST_DAYS)) {
                        continue;
                    }
                    $periodRows[$index][$bucket][] = ['row' => $lineRow, 'distance' => $distance];
                }
            }
            foreach (['candidates', 'taken', 'foreign', 'voided'] as $bucket) {
                usort($periodRows[$index][$bucket], static fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);
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
                'subscriptions' => $subscriptionCount[$key] ?? 0,
                'periods' => $periodCount[$key] ?? 0,
                'required' => $productRequired,
                'covered' => $productCovered,
                'invoiced' => $invoiced[$key] ?? 0.0,
                'free' => $productFree,
                'missing' => $productMissing,
                'surplus' => $productSurplus,
            ];
            $free += $productFree;
            $missing += $productMissing;
            $surplus += $productSurplus;
        }
        usort($productRows, static fn(array $a, array $b): int => (($b['missing'] + $b['surplus']) <=> ($a['missing'] + $a['surplus'])) ?: strcmp($a['label'], $b['label']));

        return ['products' => $productRows, 'periods' => $periodRows, 'lines' => $lineRows, 'open' => $open, 'partial' => $partial, 'proposed' => $proposed, 'free' => $free, 'missing' => $missing, 'surplus' => $surplus];
    }

    /**
     * Produktschlüssel eines Abos: sein Lexoffice-Artikel, sonst der Artikel
     * des Empfängers, dessen Name zum Abo passt, sonst der Abo-Name.
     *
     * @param  array<int, string>  $articleNames
     */
    private function subscriptionProduct(ResaleSubscription $subscription, array $articleNames): string {
        if ($subscription->lexoffice_article_id !== null) {
            return 'art:' . $subscription->lexoffice_article_id;
        }
        foreach ($articleNames as $id => $name) {
            if ($this->matcher->matches($subscription->label, $name)) {
                return 'art:' . $id;
            }
        }

        return 'name:' . ProductNameMatcher::normalize($subscription->label);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<ResaleSubscription> */
    private function subscriptions(Organization $organization): \Illuminate\Database\Eloquent\Builder {
        return ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('is_own_holding', false)
            ->where(static fn($q) => $q->whereNotNull('customer_id')->orWhereNotNull('foreign_customer_id'))
            ->with(['customer:id,name', 'foreignCustomer:id,name,customer_id', 'foreignCustomer.customer:id,name', 'lexofficeArticle:id,name'])
            ->orderBy('label')->orderBy('starts_on');
    }

    /**
     * Lexoffice-Kontakt → Kunde (Rechnungsempfänger).
     *
     * @return array{byContact: array<string, int>}
     */
    private function contactMap(Organization $organization): array {
        $byContact = [];
        foreach (ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', (new Customer)->getMorphClass())
            ->get(['referenceable_id', 'external_id']) as $reference) {
            $byContact[(string) $reference->external_id] = (int) $reference->referenceable_id;
        }

        return ['byContact' => $byContact];
    }

    /**
     * Lizenzpositionen (Artikel laut Einstufung) aller Rechnungen der Kontakte —
     * ohne Kontaktliste alle Lizenzpositionen der Organisation.
     *
     * @param  list<string>  $contacts
     * @return Collection<int, LexofficeVoucherLine>
     */
    private function licenseLines(Organization $organization, array $contacts, bool $voided = false): Collection {
        $articleIds = \App\Models\LexofficeArticle::query()->withoutGlobalScopes()->where('organization_id', $organization->id)
            ->get(['id', 'name', 'resale_role'])
            ->filter(fn(\App\Models\LexofficeArticle $a): bool => $this->classifier->isLicense($a))
            ->pluck('id')->all();
        if ($articleIds === []) {
            return collect();
        }

        return LexofficeVoucherLine::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('lexoffice_article_id', $articleIds)
            ->whereHas('voucher', static function ($q) use ($contacts, $voided): void {
                $q->where('voucher_type', 'invoice')->where('archived', false);
                $voided ? $q->where('voucher_status', 'voided') : $q->whereNotIn('voucher_status', ['draft', 'voided']);
                if ($contacts !== []) {
                    $q->whereIn('contact_external_id', $contacts);
                }
            })
            ->with(['voucher:id,external_id,contact_external_id,customer_id,voucher_number,voucher_date,voucher_status,service_starts_on,service_ends_on,voucher_text,recipient_name', 'article:id,name,unit_name,resale_role'])
            ->get();
    }

    /**
     * Verbrauch je Position über alle Perioden (Vorschläge eingeschlossen).
     *
     * @param  list<int>  $lineIds
     * @return array<int, array{months: float, periods: list<string>}>
     */
    private function linkedMonths(Organization $organization, array $lineIds): array {
        if ($lineIds === []) {
            return [];
        }
        $linked = [];
        $links = ResalePeriodLink::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('linkable_type', (new LexofficeVoucherLine)->getMorphClass())
            ->whereIn('linkable_id', $lineIds)
            ->with(['period:id,starts_on,ends_on', 'subscription:id,label,customer_id,foreign_customer_id,is_own_holding', 'subscription.customer:id,name', 'subscription.foreignCustomer:id,name'])
            ->get();
        foreach ($links as $link) {
            $id = (int) $link->linkable_id;
            $linked[$id] ??= ['months' => 0.0, 'periods' => []];
            $linked[$id]['months'] += (float) $link->months;
            // Halter statt Produkt: bei Partnern mit mehreren Endkunden ist das die Information, die fehlt.
            $linked[$id]['periods'][] = ($link->subscription !== null ? $link->subscription->holderLabel() . ' · ' : '') . $link->period->label();
        }

        return $linked;
    }

    /**
     * @param  Collection<int, LexofficeVoucherLine>  $lines
     * @return list<int>
     */
    private static function ids(Collection $lines): array {
        $ids = [];
        foreach ($lines as $line) {
            $ids[] = $line->id;
        }

        return $ids;
    }
}
