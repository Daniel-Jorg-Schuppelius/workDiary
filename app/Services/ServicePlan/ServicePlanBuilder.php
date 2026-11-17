<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Customer\CustomerDeadlineStatus;
use App\Enums\Diary\Status;
use App\Enums\Period\PeriodKind;
use App\Enums\ServicePlan\ServicePlanCellState;
use App\Models\Customer\{Customer, CustomerDeadline, CustomerDeadlineProfile, CustomerService};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Models\ServicePlan\{CustomerServicePeriodNote, CustomerServiceWaiver};
use App\Services\Customer\Deadlines\Contracts\CustomerDeadlineRuleSet;
use App\Services\Customer\Deadlines\CustomerDeadlineRuleSets;
use App\Services\Fields\{FieldDefinition, FieldValues};
use App\Services\ServicePlan\Dto\{ServiceKey, ServicePlanCell, ServicePlanQuery, ServicePlanResult, ServicePlanRow};
use App\Support\Period\PeriodKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Leistungsplan je Kunde und Periode (Feature 199, MVP-1638/1639) — eine Sicht
 * auf Aufträge, keine zweite Statustabelle.
 *
 * Jahressicht: Zeilen = Kunden mit einer jährlichen Leistung im
 * Leistungsumfang (`customer_services`), Spalten = Leistungen. Periodensicht:
 * Zeilen = Kunde × Leistung der Periodenart Monat, Quartal oder Halbjahr,
 * Spalten = die zwölf Monate (ein Quartal überspannt drei, ein Halbjahr sechs).
 *
 * Eine Zelle zeigt den Auftrag der Periode (gleicher Kunde, Eintragsart,
 * Produktgruppe — eine Leistung ohne Produktgruppe nimmt jede —, Periode,
 * nicht storniert). Ohne Auftrag: „entfällt“, wenn von Hand markiert, sonst
 * „fehlt“ bei einer Pflichtleistung und still bei einer Wahlleistung.
 * Fälligkeit: Auftrag, sonst früheste Kundenfrist, deren Art der Regelsatz
 * der Leistung zuordnet und deren Periode in der Zellperiode endet (eine
 * Quartalsfrist erscheint in der Periodensicht im letzten Quartalsmonat);
 * unter dem Jahr zuletzt das Periodenende. Zuständig ist die Person am
 * Auftrag, ohne Auftrag die zuständige Person der Fristmerkmale.
 */
final class ServicePlanBuilder {
    /** Periodenarten der Periodensicht. */
    private const array PERIOD_KINDS = [PeriodKind::Month, PeriodKind::Quarter, PeriodKind::HalfYear];

    /** @var array<string, bool> Regelsatz|Art|Slug|Produktgruppe → ordnet zu */
    private array $completes = [];

    public function __construct(
        private readonly ServicePlanCatalog $catalog,
        private readonly CustomerDeadlineRuleSets $ruleSets,
    ) {}

    public function build(Organization $organization, ServicePlanQuery $query): ServicePlanResult {
        $orgId = (int) $organization->id;
        $kinds = $query->isYearView() ? [PeriodKind::Year] : self::PERIOD_KINDS;
        $yearStart = CarbonImmutable::create($query->year, 1, 1, 0, 0, 0) ?? CarbonImmutable::today()->startOfYear();
        $yearEnd = $yearStart->endOfYear()->startOfDay();

        $allServices = CustomerService::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $orgId)
            ->whereIn('period_kind', array_map(static fn (PeriodKind $kind): string => $kind->value, $kinds))
            ->validBetween($yearStart, $yearEnd)
            ->when($query->customerIds !== null, static fn (Builder $q) => $q->whereIn('customer_id', $query->customerIds))
            ->orderBy('valid_from')
            ->orderBy('id')
            ->get(['id', 'customer_id', 'entry_type_id', 'product_group_code', 'is_mandatory', 'period_kind', 'valid_from', 'valid_until']);

        $available = [];
        foreach ($allServices as $service) {
            $key = new ServiceKey((int) $service->entry_type_id, $service->product_group_code);
            $available[$key->key()] ??= $this->catalog->column($orgId, $key, $service->period_kind);
        }
        $available = ServicePlanCatalog::sorted(array_values($available));

        $selected = array_map(static fn (ServiceKey $key): string => $key->key(), $query->services);
        $services = $selected === []
            ? $allServices
            : $allServices->filter(static fn (CustomerService $s): bool => in_array((new ServiceKey((int) $s->entry_type_id, $s->product_group_code))->key(), $selected, true))->values();

        $customers = $this->customers($orgId, array_values(array_unique($services->pluck('customer_id')->map(static fn (mixed $id): int => (int) $id)->all())), $query);
        $customerIds = array_keys($customers);
        $services = $services->filter(static fn (CustomerService $s): bool => isset($customers[(int) $s->customer_id]))->values();

        $periodKeys = $this->periodKeys($query->year, $kinds);
        $entryTypeIds = array_values(array_unique($services->pluck('entry_type_id')->map(static fn (mixed $id): int => (int) $id)->all()));
        $orders = $this->orders($orgId, $customerIds, $entryTypeIds, $periodKeys);
        [$waivers, $notes] = $this->annotations($orgId, $customerIds, $periodKeys);
        $deadlines = $this->deadlines($orgId, $customerIds, $query->year);
        $day = $query->today->lessThan($yearEnd) ? $query->today : $yearEnd;
        $profileResponsible = $this->profileResponsibles($orgId, $customerIds, $day);
        $ruleSets = $this->ruleSets->available($organization);

        // Leistungen je Kunde und Schlüssel (mehrere Fassungen mit eigener Gültigkeit sind möglich).
        /** @var array<int, array<string, list<CustomerService>>> $byCustomer */
        $byCustomer = [];
        foreach ($services as $service) {
            $key = new ServiceKey((int) $service->entry_type_id, $service->product_group_code);
            $group = $query->isYearView() ? $key->key() : $key->key() . '|' . $service->period_kind->value;
            $byCustomer[(int) $service->customer_id][$group][] = $service;
        }

        $context = compact('orgId', 'orders', 'waivers', 'notes', 'deadlines', 'profileResponsible', 'ruleSets');
        $rows = [];
        $columns = [];
        foreach ($customers as $customerId => $customer) {
            if (! isset($byCustomer[$customerId])) {
                continue;
            }
            if ($query->isYearView()) {
                $cells = [];
                foreach ($byCustomer[$customerId] as $group => $versions) {
                    $key = ServiceKey::fromKey($group) ?? new ServiceKey((int) $versions[0]->entry_type_id, $versions[0]->product_group_code);
                    $columns[$key->key()] ??= $this->catalog->column($orgId, $key, PeriodKind::Year);
                    $cells[$key->key()] = $this->cell($customerId, $key, PeriodKey::of(PeriodKind::Year, $query->year), $versions, $query, $context, 1);
                }
                $rows[] = new ServicePlanRow($customerId, (string) $customer->name, $customer->number, $cells, null, $profileResponsible[$customerId] ?? null);

                continue;
            }
            foreach ($byCustomer[$customerId] as $versions) {
                $first = $versions[0];
                $key = new ServiceKey((int) $first->entry_type_id, $first->product_group_code);
                $kind = $first->period_kind;
                $column = $this->catalog->column($orgId, $key, $kind);
                $columns[$key->key() . '|' . $kind->value] ??= $column;
                $cells = [];
                for ($ordinal = 1; $ordinal <= $kind->perYear(); $ordinal++) {
                    $period = PeriodKey::of($kind, $query->year, $ordinal);
                    $cells[$period->key()] = $this->cell($customerId, $key, $period, $versions, $query, $context, $kind->months());
                }
                $rows[] = new ServicePlanRow($customerId, (string) $customer->name, $customer->number, $cells, $column, $profileResponsible[$customerId] ?? null);
            }
        }

        $rows = array_values(array_filter($rows, fn (ServicePlanRow $row): bool => $this->keepRow($row, $query)));
        $counts = array_fill_keys(ServicePlanCellState::FILTERS, 0);
        foreach ($rows as $row) {
            foreach ($row->cells as $cell) {
                foreach (ServicePlanCellState::FILTERS as $filter) {
                    if ($cell->matches($filter)) {
                        $counts[$filter]++;
                    }
                }
            }
        }
        if ($query->state !== null && in_array($query->state, ServicePlanCellState::FILTERS, true)) {
            $rows = array_values(array_filter($rows, static function (ServicePlanRow $row) use ($query): bool {
                foreach ($row->cells as $cell) {
                    if ($cell->matches((string) $query->state)) {
                        return true;
                    }
                }

                return false;
            }));
        }
        if (! $query->isYearView()) {
            usort($rows, static fn (ServicePlanRow $a, ServicePlanRow $b): int => [mb_strtolower($a->customerName), $a->customerId, $a->service->sort ?? 0, mb_strtolower($a->service->label ?? '')]
                <=> [mb_strtolower($b->customerName), $b->customerId, $b->service->sort ?? 0, mb_strtolower($b->service->label ?? '')]);
        }

        [$traitFields, $traits] = $query->isYearView() ? [[], []] : $this->traits($orgId, $ruleSets[(string) $query->ruleSet] ?? null, $customerIds, $day);

        return new ServicePlanResult(
            query: $query,
            columns: ServicePlanCatalog::sorted(array_values($columns)),
            rows: $rows,
            counts: $counts,
            available: $available,
            responsibleNames: $this->names($rows, $profileResponsible),
            traitFields: $traitFields,
            traits: $traits,
        );
    }

    /**
     * Eine Zelle: Auftrag, sonst „entfällt“/„fehlt“/still; außerhalb der Gültigkeit `None`.
     *
     * @param  list<CustomerService>  $versions
     * @param  array<string, mixed>  $context
     */
    private function cell(int $customerId, ServiceKey $key, PeriodKey $period, array $versions, ServicePlanQuery $query, array $context, int $span): ServicePlanCell {
        $version = null;
        foreach ($versions as $candidate) {
            if ($candidate->coversPeriod($period)) {
                $version = $candidate;
            }
        }
        if ($version === null) {
            return new ServicePlanCell($customerId, $key, $period, ServicePlanCellState::None, false, span: $span);
        }

        /** @var array<int, array<int, array<string, list<array<string, mixed>>>>> $orders */
        $orders = $context['orders'];
        $order = null;
        foreach ($orders[$customerId][$key->entryTypeId][$period->key()] ?? [] as $candidate) {
            if ($key->covers($key->entryTypeId, $candidate['product_group'])) {
                $order = $candidate;

                break;
            }
        }
        $annotationKey = $customerId . '|' . $key->key() . '|' . $period->key();
        /** @var CustomerServiceWaiver|null $waiver */
        $waiver = $context['waivers'][$annotationKey] ?? null;
        /** @var CustomerServicePeriodNote|null $note */
        $note = $context['notes'][$annotationKey] ?? null;

        $state = match (true) {
            $order !== null => ServicePlanCellState::fromOrderStatus($order['status']),
            $waiver !== null => ServicePlanCellState::Waived,
            $version->is_mandatory => ServicePlanCellState::Missing,
            default => ServicePlanCellState::Optional,
        };

        $deadline = $this->deadline($context, $customerId, $key, $period, $order['product_group'] ?? $key->productGroup);
        $due = $order['due'] ?? ($deadline !== null ? CarbonImmutable::instance($deadline->due_on)->startOfDay() : null);
        if ($due === null && $period->kind !== PeriodKind::Year) {
            $due = $period->end();
        }
        $responsible = $order !== null ? $order['assigned'] : ($context['profileResponsible'][$customerId] ?? null);
        $hidden = $query->visibleToIds !== null && ($responsible === null || ! in_array($responsible, $query->visibleToIds, true));

        return new ServicePlanCell(
            customerId: $customerId,
            service: $key,
            period: $period,
            state: $state,
            mandatory: (bool) $version->is_mandatory,
            due: $due,
            overdue: $state->isPending() && $due !== null && $due->lessThan($query->today),
            responsibleId: $responsible,
            orderId: $order['id'] ?? null,
            orderTitle: $order['title'] ?? null,
            orderStatus: $order['status'] ?? null,
            orderAssigneeId: $order['assigned'] ?? null,
            waiverReason: $waiver?->reason,
            waiverBy: $waiver?->created_by,
            waivedAt: $waiver?->created_at !== null ? CarbonImmutable::instance($waiver->created_at) : null,
            documentsReceivedOn: $note?->documents_received_on !== null ? CarbonImmutable::instance($note->documents_received_on)->startOfDay() : null,
            note: $note?->note,
            advanceRequestedOn: $deadline?->advance_requested_on !== null ? CarbonImmutable::instance($deadline->advance_requested_on)->startOfDay() : null,
            deadlineId: $deadline?->id,
            hidden: $hidden,
            span: $span,
        );
    }

    /**
     * Früheste Kundenfrist, deren Art der Regelsatz der Leistung zuordnet und
     * deren Periode in der Zellperiode endet (gleich lang oder länger).
     *
     * @param  array<string, mixed>  $context
     */
    private function deadline(array $context, int $customerId, ServiceKey $key, PeriodKey $period, ?string $productGroup): ?CustomerDeadline {
        $slug = $this->catalog->entryTypeSlug((int) $context['orgId'], $key->entryTypeId);
        if ($slug === null) {
            return null;
        }
        /** @var array<string, CustomerDeadlineRuleSet> $ruleSets */
        $ruleSets = $context['ruleSets'];
        $best = null;
        /** @var CustomerDeadline $deadline */
        foreach ($context['deadlines'][$customerId] ?? [] as $deadline) {
            $deadlinePeriod = $deadline->period_key;
            if ($deadlinePeriod->kind->months() < $period->kind->months()
                || $deadlinePeriod->end()->lessThan($period->start())
                || $deadlinePeriod->end()->greaterThan($period->end())) {
                continue;
            }
            $ruleSet = $ruleSets[$deadline->rule_set] ?? null;
            if ($ruleSet === null || ! $this->completes($ruleSet, $deadline->kind, $slug, $productGroup)) {
                continue;
            }
            if ($best === null || $deadline->due_on->lessThan($best->due_on)) {
                $best = $deadline;
            }
        }

        return $best;
    }

    private function completes(CustomerDeadlineRuleSet $ruleSet, string $kind, string $slug, ?string $productGroup): bool {
        $memo = $ruleSet->key() . '|' . $kind . '|' . $slug . '|' . ($productGroup ?? '');

        return $this->completes[$memo] ??= $ruleSet->completes($kind, $slug, $productGroup);
    }

    private function keepRow(ServicePlanRow $row, ServicePlanQuery $query): bool {
        if ($query->visibleToIds !== null && ! $row->hasVisibleCell()) {
            return false;
        }
        if ($query->responsibleIds === null && ! $query->unassigned) {
            return true;
        }
        foreach ($row->cells as $cell) {
            if (! $cell->counts()) {
                continue;
            }
            if ($query->unassigned ? $cell->responsibleId === null : in_array($cell->responsibleId, $query->responsibleIds ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kunden der Leistungen nach Suche und Kundengruppe, ohne archivierte, nach Name.
     *
     * @param  list<int>  $ids
     * @return array<int, Customer>
     */
    private function customers(int $orgId, array $ids, ServicePlanQuery $query): array {
        if ($ids === []) {
            return [];
        }
        $search = trim((string) $query->search);
        $customers = Customer::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $orgId)
            ->whereIn('id', $ids)
            ->whereNull('archived_at')
            ->when($search !== '', static fn (Builder $q) => $q->where(static fn (Builder $inner) => $inner->whereLikeEscaped('name', mb_strtolower($search), 'both', 'and', true)->orWhereLikeEscaped('number', mb_strtolower($search), 'both', true)))
            ->when($query->customerGroupId !== null, static fn (Builder $q) => $q->whereHas('classifications', static fn (Builder $c) => $c->where('classifications.id', $query->customerGroupId)))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'number']);

        $out = [];
        foreach ($customers as $customer) {
            $out[(int) $customer->id] = $customer;
        }

        return $out;
    }

    /**
     * Periodenschlüssel des Jahres zu den Arten.
     *
     * @param  list<PeriodKind>  $kinds
     * @return list<string>
     */
    private function periodKeys(int $year, array $kinds): array {
        $keys = [];
        foreach ($kinds as $kind) {
            for ($ordinal = 1; $ordinal <= $kind->perYear(); $ordinal++) {
                $keys[] = PeriodKey::of($kind, $year, $ordinal)->key();
            }
        }

        return $keys;
    }

    /**
     * Aufträge der Perioden (nicht storniert) als leichte Zeilen mit
     * Produktgruppe: Kunde → Eintragsart → Periode → Liste.
     *
     * @param  list<int>  $customerIds
     * @param  list<int>  $entryTypeIds
     * @param  list<string>  $periodKeys
     * @return array<int, array<int, array<string, list<array{id: int, title: string|null, status: Status, assigned: int|null, due: CarbonImmutable|null, product_group: string|null}>>>>
     */
    private function orders(int $orgId, array $customerIds, array $entryTypeIds, array $periodKeys): array {
        if ($customerIds === [] || $entryTypeIds === []) {
            return [];
        }
        $productGroups = DB::table('classifiables')
            ->join('classifications', 'classifications.id', '=', 'classifiables.classification_id')
            ->where('classifiables.classifiable_type', (new DiaryEntry)->getMorphClass())
            ->where('classifications.domain', ClassificationDomain::ProductGroup->value)
            ->groupBy('classifiables.classifiable_id')
            ->select(['classifiables.classifiable_id', DB::raw('MIN(classifications.code) as product_group')]);

        $out = [];
        foreach (array_chunk($customerIds, 500) as $chunk) {
            $rows = DB::table('diary_entries')
                ->leftJoinSub($productGroups, 'pg', 'pg.classifiable_id', '=', 'diary_entries.id')
                ->where('diary_entries.organization_id', $orgId)
                ->whereIn('diary_entries.customer_id', $chunk)
                ->whereIn('diary_entries.entry_type_id', $entryTypeIds)
                ->whereIn('diary_entries.period_key', $periodKeys)
                ->where('diary_entries.status', '!=', Status::Cancelled->value)
                ->orderBy('diary_entries.id')
                ->get(['diary_entries.id', 'diary_entries.customer_id', 'diary_entries.entry_type_id', 'diary_entries.period_key', 'diary_entries.status', 'diary_entries.assigned_user_id', 'diary_entries.due_date', 'diary_entries.title', 'pg.product_group']);
            foreach ($rows as $row) {
                $status = Status::tryFrom((int) $row->status);
                $period = PeriodKey::tryParse(is_string($row->period_key) ? $row->period_key : null);
                if ($status === null || $period === null) {
                    continue;
                }
                $out[(int) $row->customer_id][(int) $row->entry_type_id][$period->key()][] = [
                    'id' => (int) $row->id,
                    'title' => is_string($row->title) ? $row->title : null,
                    'status' => $status,
                    'assigned' => $row->assigned_user_id !== null ? (int) $row->assigned_user_id : null,
                    'due' => is_string($row->due_date) && $row->due_date !== '' ? CarbonImmutable::parse($row->due_date)->startOfDay() : null,
                    'product_group' => is_string($row->product_group) && $row->product_group !== '' ? $row->product_group : null,
                ];
            }
        }

        return $out;
    }

    /**
     * „Entfällt“ und Unterlagen/Bemerkung je Kunde|Leistung|Periode.
     *
     * @param  list<int>  $customerIds
     * @param  list<string>  $periodKeys
     * @return array{0: array<string, CustomerServiceWaiver>, 1: array<string, CustomerServicePeriodNote>}
     */
    private function annotations(int $orgId, array $customerIds, array $periodKeys): array {
        $waivers = [];
        $notes = [];
        if ($customerIds === []) {
            return [$waivers, $notes];
        }
        foreach (array_chunk($customerIds, 500) as $chunk) {
            foreach (CustomerServiceWaiver::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $orgId)->whereIn('customer_id', $chunk)->whereIn('period_key', $periodKeys)->get() as $waiver) {
                $waivers[$waiver->customer_id . '|' . $waiver->serviceKey()->key() . '|' . $waiver->period_key->key()] = $waiver;
            }
            foreach (CustomerServicePeriodNote::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $orgId)->whereIn('customer_id', $chunk)->whereIn('period_key', $periodKeys)->get() as $note) {
                $notes[$note->customer_id . '|' . $note->serviceKey()->key() . '|' . $note->period_key->key()] = $note;
            }
        }

        return [$waivers, $notes];
    }

    /**
     * Kundenfristen der Perioden des Jahres (ohne entfallene) je Kunde.
     *
     * @param  list<int>  $customerIds
     * @return array<int, list<CustomerDeadline>>
     */
    private function deadlines(int $orgId, array $customerIds, int $year): array {
        $out = [];
        if ($customerIds === []) {
            return $out;
        }
        $keys = $this->periodKeys($year, [PeriodKind::Year, ...self::PERIOD_KINDS]);
        foreach (array_chunk($customerIds, 500) as $chunk) {
            $deadlines = CustomerDeadline::query()->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $orgId)
                ->whereIn('customer_id', $chunk)
                ->whereIn('period_key', $keys)
                ->where('status', '!=', CustomerDeadlineStatus::Waived->value)
                ->orderBy('due_on')
                ->orderBy('id')
                ->get(['id', 'customer_id', 'rule_set', 'kind', 'period_key', 'due_on', 'advance_requested_on', 'status']);
            foreach ($deadlines as $deadline) {
                $out[(int) $deadline->customer_id][] = $deadline;
            }
        }

        return $out;
    }

    /**
     * Zuständige Person der Fristmerkmale am Tag (erste gesetzte je Kunde).
     *
     * @param  list<int>  $customerIds
     * @return array<int, int>
     */
    private function profileResponsibles(int $orgId, array $customerIds, CarbonImmutable $day): array {
        $out = [];
        if ($customerIds === []) {
            return $out;
        }
        foreach (array_chunk($customerIds, 500) as $chunk) {
            $profiles = CustomerDeadlineProfile::query()->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $orgId)
                ->whereIn('customer_id', $chunk)
                ->whereNotNull('responsible_user_id')
                ->validOn($day)
                ->orderBy('rule_set')
                ->orderBy('id')
                ->get(['customer_id', 'responsible_user_id']);
            foreach ($profiles as $profile) {
                $out[(int) $profile->customer_id] ??= (int) $profile->responsible_user_id;
            }
        }

        return $out;
    }

    /**
     * Zusatzspalten: Merkmale des Regelsatzes (Felder mit `listed`) und die am Tag geltenden Werte.
     *
     * @param  list<int>  $customerIds
     * @return array{0: list<FieldDefinition>, 1: array<int, FieldValues>}
     */
    private function traits(int $orgId, ?CustomerDeadlineRuleSet $ruleSet, array $customerIds, CarbonImmutable $day): array {
        if ($ruleSet === null) {
            return [[], []];
        }
        $fields = array_values(array_filter($ruleSet->traitSchema()->all(), static fn (FieldDefinition $field): bool => $field->listed));
        $values = [];
        foreach (array_chunk($customerIds, 500) as $chunk) {
            $profiles = CustomerDeadlineProfile::query()->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $orgId)
                ->whereIn('customer_id', $chunk)
                ->where('rule_set', $ruleSet->key())
                ->validOn($day)
                ->orderBy('valid_from')
                ->get();
            foreach ($profiles as $profile) {
                $values[(int) $profile->customer_id] = $profile->traitValues();
            }
        }

        return [$fields, $values];
    }

    /**
     * Namen der zuständigen Personen der Zeilen.
     *
     * @param  list<ServicePlanRow>  $rows
     * @param  array<int, int>  $profileResponsible
     * @return array<int, string>
     */
    private function names(array $rows, array $profileResponsible): array {
        $ids = array_values($profileResponsible);
        foreach ($rows as $row) {
            foreach ($row->cells as $cell) {
                if ($cell->responsibleId !== null) {
                    $ids[] = $cell->responsibleId;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }
        $names = [];
        foreach (User::query()->whereIn('id', $ids)->get(['id', 'name']) as $user) {
            $names[(int) $user->id] = (string) $user->name;
        }

        return $names;
    }
}
