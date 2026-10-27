<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseAccountOverview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\{PeriodStatus, SubscriptionStatus};
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResaleAccountAllocation, ResalePeriod, ResalePlanLink, ResaleSubscription};
use App\Services\Reselling\Plan\{PlanAudit, PlanLine, PlanTemplate, RecurringPlans};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Collection;

/**
 * Sichten auf die Lizenzkonten (Feature 185, MVP-1400): Übersicht je Konto
 * und Detail mit Aufteilung je Produkt, Raster Produkt × Halter × Lizenzjahr,
 * Verträgen samt Vorgängern und Prüffällen an den Bezügen.
 *
 * @phpstan-type HolderShare array{label: string, quantity: int}
 * @phpstan-type ProductRow array{key: string, label: string, quantity: int, holders: array<string, HolderShare>, allocated: int, conflict: int, domain: bool}
 * @phpstan-type GridCell array{period: ResalePeriod, vouchers: list<string>}
 * @phpstan-type GridRow array{subscription: ResaleSubscription, product: string, holder: string, cells: array<int, list<GridCell>>}
 * @phpstan-type OverviewRow array{group: LicenseAccountGroup, name: string, customer: \App\Models\Customer\Customer|null, partner: \App\Models\Customer\Customer|null, contracts: int, licences: int, products: list<HolderShare>, products_more: int, domains: int, holders: array<string, HolderShare>, open: int, conflicts: int}
 */
final class LicenseAccountOverview {
    /** Produktzeilen je Konto in der Übersicht; der Rest steht als „+ n weitere“, Domains nur als Summe (Pilot 2026-10-27: 68 Domainzeilen in einer Zeile). */
    public const OVERVIEW_PRODUCT_LINES = 5;

    public function __construct(
        private readonly LicenseAccounts $accounts,
        private readonly LinkAudit $audit,
        private readonly RecurringPlans $plans,
        private readonly PlanAudit $planAudit,
    ) {}

    /** @return list<OverviewRow> */
    public function overview(Organization $organization, ?CarbonImmutable $reference = null): array {
        $reference ??= ResalePeriod::today();
        $groups = $this->accounts->groups($organization);
        $openBySubscription = ResalePeriod::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])
            ->where('starts_on', '<', DateRange::dayAfter($reference))
            ->selectRaw('subscription_id, COUNT(*) AS n')
            ->groupBy('subscription_id')
            ->pluck('n', 'subscription_id');
        $allocations = ResaleAccountAllocation::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->get()
            ->groupBy(static fn(ResaleAccountAllocation $a): string => ResaleSubscription::accountKeyOf($a->provider, $a->provider_account));

        $rows = [];
        foreach ($groups as $group) {
            $this->attachAssignments($group);
            $products = $this->productRows($group, $allocations->get($group->key, collect()), $reference);
            $holders = [];
            $licences = 0;
            $listed = [];
            $domains = 0;
            foreach ($products as $product) {
                $licences += $product['quantity'];
                foreach ($product['holders'] as $key => $share) {
                    $holders[$key] ??= ['label' => $share['label'], 'quantity' => 0];
                    $holders[$key]['quantity'] += $share['quantity'];
                }
                if ($product['domain']) {
                    $domains += $product['quantity'];
                } else {
                    $listed[] = ['label' => $product['label'], 'quantity' => $product['quantity']];
                }
            }
            $open = 0;
            foreach ($group->subscriptions as $subscription) {
                if (! $subscription->is_own_holding) {
                    $open += (int) ($openBySubscription[$subscription->id] ?? 0);
                }
            }
            $rows[] = [
                'group' => $group,
                'name' => $group->name(),
                'customer' => $group->customer(),
                'partner' => $group->partner(),
                'contracts' => $group->headContracts()->count(),
                'licences' => $licences,
                'products' => array_slice($listed, 0, self::OVERVIEW_PRODUCT_LINES),
                'products_more' => max(0, count($listed) - self::OVERVIEW_PRODUCT_LINES),
                'domains' => $domains,
                'holders' => $holders,
                'open' => $open,
                'conflicts' => (int) array_sum(array_map(static fn(array $p): int => $p['conflict'] > 0 ? 1 : 0, $products)),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => ($b['open'] <=> $a['open']) ?: ($b['conflicts'] <=> $a['conflicts']) ?: strcmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * @return array{group: LicenseAccountGroup, allocations: Collection<int, ResaleAccountAllocation>, products: list<ProductRow>, years: list<int>, rows: list<GridRow>, findings: list<array<string, mixed>>, due: list<ResalePeriod>, plan: array{available: bool, templates: list<array{template: PlanTemplate, lines: list<array{line: PlanLine, links: list<ResalePlanLink>}>}>, findings: list<array<string, mixed>>}}
     */
    public function detail(Organization $organization, LicenseAccountGroup $group, ?CarbonImmutable $reference = null): array {
        $reference ??= ResalePeriod::today();
        $this->attachAssignments($group);
        $loaded = ResaleSubscription::query()->withoutGlobalScopes()
            ->whereIn('id', $group->subscriptions->pluck('id')->all())
            ->with(['periods.links', 'periods.decidedBy', 'periods.purchases'])
            ->get()->keyBy('id');
        foreach ($group->subscriptions as $subscription) {
            $withPeriods = $loaded->get($subscription->id);
            $subscription->setRelation('periods', $withPeriods !== null ? $withPeriods->periods : collect());
        }
        $allocations = ResaleAccountAllocation::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->forAccount($group->provider, $group->account)
            ->with(['customer:id,name', 'foreignCustomer:id,name,customer_id'])
            ->orderBy('product_label')->orderBy('valid_from')
            ->get();

        $years = [];
        $rows = [];
        $due = [];
        foreach ($this->gridOrder($group) as $subscription) {
            $cells = [];
            foreach ($subscription->periods as $period) {
                $year = (int) $period->starts_on->format('Y');
                $years[$year] = true;
                $cells[$year][] = ['period' => $period, 'vouchers' => array_values(array_unique(array_filter($period->links->pluck('voucher_number')->all())))];
                if (! $subscription->is_own_holding && in_array($period->status, [PeriodStatus::Open, PeriodStatus::Partial], true) && ! $period->starts_on->greaterThan($reference)) {
                    $due[] = $period;
                }
            }
            $rows[] = ['subscription' => $subscription, 'product' => $subscription->productName(), 'holder' => $subscription->holderLabel(), 'cells' => $cells];
        }
        ksort($years);
        usort($due, static fn(ResalePeriod $a, ResalePeriod $b): int => $a->starts_on <=> $b->starts_on);

        return [
            'group' => $group,
            'allocations' => $allocations,
            'products' => $this->productRows($group, $allocations, $reference),
            'economics' => $this->economics($group, $reference),
            'years' => array_keys($years),
            'rows' => $rows,
            'findings' => $this->audit->forSubscriptions($organization, $group->subscriptions),
            'due' => $due,
            'plan' => $this->planFor($organization, $group),
        ];
    }

    /**
     * Serienvorlagen des Kontos (Feature 185, MVP-1411/1412): Vorlagen der
     * Rechnungsempfänger des Kontos mit ihren Lizenzpositionen und den
     * Zuordnungen zu Abos des Kontos; dazu die Prüffälle Bestand ↔ Plan.
     *
     * @return array{available: bool, templates: list<array{template: PlanTemplate, lines: list<array{line: PlanLine, links: list<ResalePlanLink>}>}>, findings: list<array<string, mixed>>}
     */
    private function planFor(Organization $organization, LicenseAccountGroup $group): array {
        if (! $this->plans->availableFor($organization)) {
            return ['available' => false, 'templates' => [], 'findings' => []];
        }
        $subscriptionIds = array_values(array_map('intval', $group->subscriptions->pluck('id')->all()));
        // Vorlagen eines Partners (Halter ist Endkunde) nur über Zuordnungen: sonst stünden alle Vorlagen des
        // Partners in jedem Endkunden-Konto, und ein beendetes Altabo zöge sie in ein fremdes Konto (Pilot 2026-10-27).
        $recipientIds = $group->subscriptions
            ->filter(static fn(ResaleSubscription $s): bool => $s->foreign_customer_id === null && ! $s->is_own_holding && $s->status->isPlanning())
            ->map(static fn(ResaleSubscription $s): ?int => $s->billedTo()?->id)->filter()->unique()->values()->all();
        $links = ResalePlanLink::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereIn('subscription_id', $subscriptionIds)->with('subscription.customer', 'subscription.foreignCustomer')->get();
        $linkedKeys = $links->pluck('plan_key')->unique()->all();
        /** @var array<string, list<ResalePlanLink>> $linksByLine */
        $linksByLine = [];
        foreach ($links as $link) {
            $linksByLine[$link->lineKey()][] = $link;
        }
        $templates = [];
        foreach ($this->plans->templatesFor($organization) as $template) {
            if (! in_array($template->recipientCustomerId, $recipientIds, true) && ! in_array($template->key, $linkedKeys, true)) {
                continue;
            }
            $lines = [];
            foreach ($template->licenceLines() as $line) {
                $lines[] = ['line' => $line, 'links' => $linksByLine[ResalePlanLink::lineKeyOf($template->key, $line->position)] ?? []];
            }
            $templates[] = ['template' => $template, 'lines' => $lines];
        }

        return ['available' => true, 'templates' => $templates, 'findings' => $this->planAudit->findings($organization, $subscriptionIds)];
    }

    /**
     * Soll-/Ist-Einkauf und Marge je Produkt (Feature 185, MVP-1421): fällige
     * Perioden der Verträge aus den letzten zwölf Monaten; Ist-Einkauf aus den
     * Einkaufsbelegen, ohne Beleg zählt der Soll-Einkauf. Marge = Soll-Verkauf
     * der fremd gehaltenen Perioden − Einkauf.
     *
     * @return array<string, array{expected_purchase: Money|null, actual_purchase: Money|null, expected_sale: Money|null, margin: Money|null, with_actual: int, periods: int}>
     */
    private function economics(LicenseAccountGroup $group, CarbonImmutable $reference): array {
        $since = $reference->subYear();
        $rows = [];
        foreach ($group->contracts() as $contract) {
            $key = $contract->productKey();
            $rows[$key] ??= ['expected_purchase' => null, 'actual_purchase' => null, 'expected_sale' => null, 'margin' => null, 'with_actual' => 0, 'periods' => 0, 'purchase' => 0.0, 'currency' => $contract->currency];
            foreach ($contract->periods as $period) {
                // Fällig und in den letzten zwölf Monaten (noch) laufend.
                if ($period->starts_on->greaterThan($reference) || $period->ends_on->lessThan($since)) {
                    continue;
                }
                $rows[$key]['periods']++;
                $expected = $period->expected_purchase;
                $actual = $period->actualPurchase();
                if ($expected !== null) {
                    $rows[$key]['expected_purchase'] = ($rows[$key]['expected_purchase'] ?? Money::zero($expected->getCurrency()))->plus($expected);
                }
                if ($actual !== null) {
                    $rows[$key]['with_actual']++;
                    $rows[$key]['actual_purchase'] = ($rows[$key]['actual_purchase'] ?? Money::zero($contract->currency))->plus(Money::ofFloat($actual, $contract->currency, 2));
                }
                $rows[$key]['purchase'] += $actual ?? $expected?->toFloat() ?? 0.0;
                if (! $contract->is_own_holding && $period->expected_sale !== null) {
                    $rows[$key]['expected_sale'] = ($rows[$key]['expected_sale'] ?? Money::zero($period->expected_sale->getCurrency()))->plus($period->expected_sale);
                }
            }
        }
        $economics = [];
        foreach ($rows as $key => $row) {
            if ($row['periods'] === 0) {
                continue;
            }
            $sale = $row['expected_sale'];
            $economics[$key] = [
                'expected_purchase' => $row['expected_purchase'],
                'actual_purchase' => $row['actual_purchase'],
                'expected_sale' => $sale,
                'margin' => $sale !== null ? $sale->minus(Money::ofFloat($row['purchase'], $sale->getCurrency(), 2)) : null,
                'with_actual' => $row['with_actual'],
                'periods' => $row['periods'],
            ];
        }

        return $economics;
    }

    /** Abtretungen des Kontos an ihre Verträge hängen (eine Relation, keine Abfrage je Vertrag). */
    private function attachAssignments(LicenseAccountGroup $group): void {
        $assignments = $group->assignments()->groupBy('parent_id');
        foreach ($group->contracts() as $contract) {
            $contract->setRelation('assignments', ($assignments->get($contract->id) ?? collect())->sortBy('starts_on')->values());
        }
    }

    /**
     * Bestand je Produkt am Stichtag: Lizenzen der laufenden Verträge, verteilt
     * auf Vertragshalter (Rest) und Abtretungen; dazu die gepflegte Aufteilung
     * und ein Konflikt, wenn mehr aufgeteilt ist als vorhanden.
     *
     * @param  Collection<int, ResaleAccountAllocation>  $allocations
     * @return list<ProductRow>
     */
    private function productRows(LicenseAccountGroup $group, Collection $allocations, CarbonImmutable $reference): array {
        $products = [];
        foreach ($group->contracts() as $contract) {
            if (! $this->isRunning($contract, $reference)) {
                continue;
            }
            $key = $contract->productKey();
            $products[$key] ??= ['key' => $key, 'label' => $contract->productName(), 'quantity' => 0, 'holders' => [], 'allocated' => 0, 'conflict' => 0, 'domain' => $contract->isDomain()];
            $products[$key]['quantity'] += (int) $contract->quantity;
            $this->addShare($products[$key]['holders'], $contract->holderKey(), $contract->holderLabel(), $contract->billableQuantityOn($reference));
            foreach ($contract->assignments as $assignment) {
                if ($this->isRunning($assignment, $reference)) {
                    $this->addShare($products[$key]['holders'], $assignment->holderKey(), $assignment->holderLabel(), $assignment->quantity);
                }
            }
        }
        foreach ($allocations as $allocation) {
            if (! $allocation->coversDay($reference)) {
                continue;
            }
            $productKey = (string) $allocation->product_key;
            $products[$productKey] ??= ['key' => $productKey, 'label' => (string) $allocation->product_label, 'quantity' => 0, 'holders' => [], 'allocated' => 0, 'conflict' => 0, 'domain' => false];
            $products[$productKey]['allocated'] += (int) $allocation->quantity;
        }
        foreach ($products as &$product) {
            $product['conflict'] = (int) max(0, $product['allocated'] - $product['quantity']);
            uasort($product['holders'], static fn(array $a, array $b): int => ($b['quantity'] <=> $a['quantity']) ?: strcmp($a['label'], $b['label']));
        }
        unset($product);
        $rows = array_values($products);
        usort($rows, static fn(array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $rows;
    }

    /** @param  array<string, HolderShare>  $holders */
    private function addShare(array &$holders, string $key, string $label, int $quantity): void {
        if ($quantity <= 0) {
            return;
        }
        $holders[$key] ??= ['label' => $label, 'quantity' => 0];
        $holders[$key]['quantity'] += $quantity;
    }

    private function isRunning(ResaleSubscription $subscription, CarbonImmutable $reference): bool {
        if (in_array($subscription->status, [SubscriptionStatus::Superseded, SubscriptionStatus::Ended], true)) {
            return false;
        }

        return ! $subscription->starts_on->greaterThan($reference) && ($subscription->ends_on === null || ! $subscription->ends_on->lessThan($reference));
    }

    /**
     * Zeilen des Rasters: je Produkt der Vertrag mit seinen Abtretungen,
     * Vorgänger hinter ihrem Nachfolger.
     *
     * @return list<ResaleSubscription>
     */
    private function gridOrder(LicenseAccountGroup $group): array {
        $contracts = $group->contracts()->sort(
            static fn(ResaleSubscription $a, ResaleSubscription $b): int => strcmp($a->productName(), $b->productName()) ?: strcmp($a->holderLabel(), $b->holderLabel()) ?: ($a->starts_on <=> $b->starts_on),
        )->values();
        $rows = [];
        foreach ($contracts as $contract) {
            $rows[] = $contract;
            foreach ($contract->assignments as $assignment) {
                $rows[] = $assignment;
            }
        }

        return $rows;
    }
}
