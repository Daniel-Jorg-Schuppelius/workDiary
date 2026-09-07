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
use App\Services\Reselling\Marketplace\ProductNameMatcher;
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
 * ein hartnäckiger Fall ohne Blick nach Lexoffice auflösbar ist.
 *
 * @phpstan-type LineRow array{line: LexofficeVoucherLine, product: string, months: float, linked: float, free: float, periods: list<string>}
 * @phpstan-type Candidate array{row: LineRow, distance: int}
 * @phpstan-type PeriodRow array{period: ResalePeriod, subscription: ResaleSubscription, required: float, covered: float, needed: float, product: string, candidates: list<Candidate>, taken: list<Candidate>}
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
     * @return array{subscriptions: Collection<int, ResaleSubscription>, contacts: list<string>, pending: int, products: list<ProductRow>, periods: list<PeriodRow>, lines: list<LineRow>, open: int, partial: int, proposed: int, free: float, missing: float, surplus: float}
     */
    public function forCustomer(Organization $organization, Customer $customer, ?CarbonImmutable $reference = null): array {
        $reference ??= CarbonImmutable::today();
        $subscriptions = $this->subscriptions($organization)->with(['periods.links', 'periods.subscription'])->get()
            ->filter(static fn(ResaleSubscription $s): bool => $s->billedTo()?->id === $customer->id)->values();
        $contacts = [];
        foreach ($this->contactMap($organization)['byContact'] as $contact => $customerId) {
            if ($customerId === $customer->id) {
                $contacts[] = (string) $contact;
            }
        }
        $lines = $this->licenseLines($organization, $contacts);
        $pending = $contacts === [] ? 0 : LexofficeVoucher::query()->whereIn('contact_external_id', $contacts)
            ->where('voucher_type', 'invoice')->where('archived', false)->whereNotIn('voucher_status', ['draft', 'voided'])
            ->whereNull('lines_synced_at')->count();
        $linkedMonths = $this->linkedMonths($organization, self::ids($lines));

        return ['subscriptions' => $subscriptions, 'contacts' => $contacts, 'pending' => $pending] + $this->analyze($subscriptions, $lines, $linkedMonths, $reference);
    }

    /**
     * @param  Collection<int, ResaleSubscription>  $subscriptions
     * @param  Collection<int, LexofficeVoucherLine>  $lines
     * @param  array<int, array{months: float, periods: list<string>}>  $linkedMonths
     * @return array{products: list<ProductRow>, periods: list<PeriodRow>, lines: list<LineRow>, open: int, partial: int, proposed: int, free: float, missing: float, surplus: float}
     */
    private function analyze(Collection $subscriptions, Collection $lines, array $linkedMonths, CarbonImmutable $reference): array {
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
            $months = LicenseMonths::ofLine($line);
            $linked = $linkedMonths[$line->id]['months'] ?? 0.0;
            $key = 'art:' . $line->lexoffice_article_id;
            $labels[$key] ??= $line->article !== null ? $line->article->name : $line->name;
            $lineRow = ['line' => $line, 'product' => $key, 'months' => $months, 'linked' => $linked, 'free' => max(0.0, $months - $linked), 'periods' => $linkedMonths[$line->id]['periods'] ?? []];
            $lineRows[] = $lineRow;
            $invoiced[$key] = ($invoiced[$key] ?? 0.0) + $months;
            $freeByProduct[$key] = ($freeByProduct[$key] ?? 0.0) + $lineRow['free'];
        }
        usort($lineRows, static fn(array $a, array $b): int => ($b['line']->voucher->voucher_date <=> $a['line']->voucher->voucher_date) ?: ($a['line']->id <=> $b['line']->id));

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
                $periodRows[] = ['period' => $period, 'subscription' => $subscription, 'required' => $required, 'covered' => $covered, 'needed' => $needed, 'product' => $key, 'candidates' => [], 'taken' => []];
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
                $date = $lineRow['line']->voucher->voucher_date;
                if ($date === null) {
                    continue;
                }
                $date = CarbonImmutable::instance($date);
                $distance = (int) abs($date->diffInDays($start));
                if ($lineRow['free'] > 0.001) {
                    $periodRows[$index]['candidates'][] = ['row' => $lineRow, 'distance' => $distance];
                } elseif (! $date->lessThan($windowStart) && ! $date->greaterThan($windowEnd)) {
                    $periodRows[$index]['taken'][] = ['row' => $lineRow, 'distance' => $distance];
                }
            }
            usort($periodRows[$index]['candidates'], static fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);
            usort($periodRows[$index]['taken'], static fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);
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
    private function licenseLines(Organization $organization, array $contacts): Collection {
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
            ->whereHas('voucher', static function ($q) use ($contacts): void {
                $q->where('voucher_type', 'invoice')->where('archived', false)->whereNotIn('voucher_status', ['draft', 'voided']);
                if ($contacts !== []) {
                    $q->whereIn('contact_external_id', $contacts);
                }
            })
            ->with(['voucher:id,external_id,contact_external_id,customer_id,voucher_number,voucher_date,voucher_text,recipient_name', 'article:id,name,unit_name,resale_role'])
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
