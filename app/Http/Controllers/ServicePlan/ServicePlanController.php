<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\ServicePlan;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Period\PeriodKind;
use App\Enums\ServicePlan\ServicePlanCellState;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\Classification\Classification;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Models\ServicePlan\CustomerServiceWaiver;
use App\Services\Classification\ClassificationResolver;
use App\Services\Customer\Deadlines\CustomerDeadlineRuleSets;
use App\Services\Delegation\ActsForResolver;
use App\Services\ServicePlan\Dto\{ServiceKey, ServicePlanCell, ServicePlanQuery, ServicePlanResult, ServicePlanRow};
use App\Services\ServicePlan\{ServicePlanBuilder, ServicePlanPeriodCreator};
use App\Support\{Formats, Locales, Sqid};
use App\Support\Period\PeriodKey;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Leistungsplan je Kunde und Periode (Feature 199, MVP-1638/1639): Jahressicht
 * Kunde × Leistung und Periodensicht Kunde × Monat, beide mit Zählkacheln,
 * Filtern (zuständige Person, Stand, Kundengruppe, Suche, Leistungen als
 * gespeicherte Ansicht) und Export (CSV/XLSX). Ohne `diary.servicePlan.view`
 * zeigt der Plan nur die eigenen Zellen und die der heute vertretenen Personen.
 */
class ServicePlanController extends Controller {
    use WritesReportCsv;

    private const int PER_PAGE = 100;

    /** Jahresauswahl: so viele Jahre vor und nach dem laufenden. */
    private const int YEAR_RANGE = 3;

    public function __construct(
        private readonly ServicePlanBuilder $builder,
        private readonly CustomerDeadlineRuleSets $ruleSets,
        private readonly ActsForResolver $actsFor,
        private readonly ClassificationResolver $classifications,
    ) {}

    /** Jahressicht (MVP-1638). */
    public function index(Request $request): View|Response {
        return $this->render($request, ServicePlanQuery::VIEW_YEAR);
    }

    /** Periodensicht (MVP-1639). */
    public function periods(Request $request): View|Response {
        return $this->render($request, ServicePlanQuery::VIEW_PERIODS);
    }

    /** Dialog „Perioden des Jahres anlegen“: Leistungen unter dem Jahr aus dem Leistungsumfang. */
    public function createPeriodsDialog(Request $request): View {
        Gate::authorize('manage', CustomerServiceWaiver::class);
        $year = (int) $request->integer('year', CarbonImmutable::today()->year);
        $year = $year >= 2000 && $year <= 2100 ? $year : CarbonImmutable::today()->year;
        $result = $this->builder->build($this->currentOrganization(), new ServicePlanQuery(ServicePlanQuery::VIEW_PERIODS, $year, CarbonImmutable::today()));

        return view('service-plan._create_periods_dialog', [
            'services' => $result->columns,
            'year' => $year,
        ]);
    }

    /** „Perioden des Jahres anlegen“ für eine Leistung über die gesammelte Folgeperiode (Feature 198). */
    public function createPeriods(Request $request, ServicePlanPeriodCreator $creator): RedirectResponse {
        Gate::authorize('manage', CustomerServiceWaiver::class);
        // Die Auswahl trägt Leistung und Periodenart zusammen („<leistung>|<art>“).
        [$serviceParam, $kind] = array_pad(explode('|', (string) $request->input('service'), 2), 2, null);
        $request->merge(['service' => $serviceParam, 'kind' => $kind]);
        $data = $request->validate([
            'service' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::enum(PeriodKind::class)->except([PeriodKind::Year])],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);
        $service = ServiceKey::fromParam($data['service']);
        abort_if($service === null, 404);

        $result = $creator->createYear($this->currentOrganization(), $service, PeriodKind::from((string) $data['kind']), (int) $data['year'], $this->authUser());

        return back()->with('success', __('service_plan.flash.periods_created', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'without' => $result['without_source'],
        ]));
    }

    protected function exportNeedsReportPermission(): bool {
        // Fachliste des Auftragsbuchs, nicht im Menü „Auswertungen“: das Leserecht der Seite deckt den Export.
        return false;
    }

    private function render(Request $request, string $view): View|Response {
        Gate::authorize('viewAny', CustomerServiceWaiver::class);
        $organization = $this->currentOrganization();
        $viewer = $this->authUser();
        $viewAll = Gate::allows('viewAll', CustomerServiceWaiver::class);
        $filters = $this->filters($request, $view, $organization);
        $query = $this->query($filters, $view, $viewer, $viewAll);
        $result = $this->builder->build($organization, $query);

        if (in_array($request->string('export')->toString(), ['csv', 'xlsx'], true)) {
            return $this->export($request, $result, $filters);
        }

        $page = max(1, (int) $request->integer('page', 1));
        $rows = new LengthAwarePaginator(array_slice($result->rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE), count($result->rows), self::PER_PAGE, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view($view === ServicePlanQuery::VIEW_YEAR ? 'service-plan.index' : 'service-plan.periods', [
            'result' => $result,
            'rows' => $rows,
            'filters' => $filters,
            'linkParams' => $this->linkParams($filters),
            'years' => $this->years(),
            'members' => User::query()->where('organization_id', $organization->id)->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
            'customerGroups' => $this->classifications->list((int) $organization->id, ClassificationDomain::CustomerGroup),
            'ruleSets' => array_map(static fn ($ruleSet): string => $ruleSet->label(), $this->ruleSets->enabled($organization)),
            'derived' => $view === ServicePlanQuery::VIEW_PERIODS ? $this->derivedTraits($organization, $filters['rule_set'], array_values($rows->items())) : [],
            'viewAll' => $viewAll,
            'canManage' => Gate::allows('manage', CustomerServiceWaiver::class),
            'today' => $query->today,
            'months' => $this->monthLabels($query->year),
        ]);
    }

    /**
     * @return array{year: int, year_param: string, responsible: string|null, state: string|null, group: string|null, group_id: int|null, q: string|null, services: list<string>, rule_set: string|null, customer: string|null, customer_id: int|null}
     */
    private function filters(Request $request, string $view, Organization $organization): array {
        $current = CarbonImmutable::today()->year;
        $yearParam = StringHelper::trimToNull($request->query('year')) ?? ($view === ServicePlanQuery::VIEW_YEAR ? 'previous' : 'current');
        $year = match (true) {
            $yearParam === 'previous' => $current - 1,
            $yearParam === 'current' => $current,
            preg_match('/^\d{4}$/', $yearParam) === 1 && abs((int) $yearParam - $current) <= 10 => (int) $yearParam,
            default => $current,
        };
        if (! in_array($yearParam, ['previous', 'current'], true)) {
            $yearParam = (string) $year;
        }

        $responsible = StringHelper::trimToNull($request->query('responsible'));
        if ($responsible !== null && ! in_array($responsible, ['mine', 'none'], true) && Sqid::decode(User::class, $responsible) === null) {
            $responsible = null;
        }
        $state = $request->string('state')->toString();
        $group = StringHelper::trimToNull($request->query('group'));
        $groupId = $group !== null ? Sqid::decode(Classification::class, $group) : null;
        $customer = StringHelper::trimToNull($request->query('customer'));
        $customerId = $customer !== null ? Sqid::decode(Customer::class, $customer) : null;

        $services = [];
        foreach ((array) $request->query('services', []) as $value) {
            if (ServiceKey::fromParam($value) !== null) {
                $services[] = (string) $value;
            }
        }

        $ruleSet = StringHelper::trimToNull($request->query('rule_set'));
        $enabled = array_keys($this->ruleSets->enabled($organization));
        if ($ruleSet === null || ! in_array($ruleSet, $enabled, true)) {
            $ruleSet = $enabled[0] ?? null;
        }

        return [
            'year' => $year,
            'year_param' => $yearParam,
            'responsible' => $responsible,
            'state' => in_array($state, ServicePlanCellState::FILTERS, true) ? $state : null,
            'group' => $groupId !== null ? $group : null,
            'group_id' => $groupId,
            'q' => StringHelper::trimToNull($request->query('q')),
            'services' => array_values(array_unique($services)),
            'rule_set' => $ruleSet,
            'customer' => $customerId !== null ? $customer : null,
            'customer_id' => $customerId,
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function query(array $filters, string $view, User $viewer, bool $viewAll): ServicePlanQuery {
        $responsibleIds = match (true) {
            $filters['responsible'] === 'mine' => $this->actsFor->assigneeIds($viewer),
            $filters['responsible'] !== null && $filters['responsible'] !== 'none' => [(int) Sqid::decode(User::class, (string) $filters['responsible'])],
            default => null,
        };

        return new ServicePlanQuery(
            view: $view,
            year: (int) $filters['year'],
            today: CarbonImmutable::today(),
            responsibleIds: $responsibleIds,
            unassigned: $filters['responsible'] === 'none',
            visibleToIds: $viewAll ? null : $this->actsFor->assigneeIds($viewer),
            state: is_string($filters['state']) ? $filters['state'] : null,
            customerGroupId: is_int($filters['group_id']) ? $filters['group_id'] : null,
            search: is_string($filters['q']) ? $filters['q'] : null,
            services: array_values(array_filter(array_map(static fn (string $value): ?ServiceKey => ServiceKey::fromParam($value), (array) $filters['services']))),
            customerIds: is_int($filters['customer_id']) ? [$filters['customer_id']] : null,
            ruleSet: is_string($filters['rule_set']) ? $filters['rule_set'] : null,
        );
    }

    /**
     * Filter für Links (Kacheln, Sortierung, Export) — ohne Seite.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function linkParams(array $filters): array {
        return array_filter([
            'year' => $filters['year_param'],
            'responsible' => $filters['responsible'],
            'state' => $filters['state'],
            'group' => $filters['group'],
            'q' => $filters['q'],
            'services' => $filters['services'] !== [] ? $filters['services'] : null,
            'rule_set' => $filters['rule_set'],
            'customer' => $filters['customer'],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @return array<int, string> Monat (1–12) → Kurzname in der aktiven Sprache */
    private function monthLabels(int $year): array {
        $labels = [];
        for ($month = 1; $month <= 12; $month++) {
            $start = PeriodKey::of(PeriodKind::Month, $year, $month)->start();
            $localized = $start->locale(Locales::carbon(app()->getLocale()));
            $labels[$month] = ($localized instanceof CarbonImmutable ? $localized : $start)->translatedFormat('M');
        }

        return $labels;
    }

    /** @return array<int|string, string> Wert → Bezeichnung (Vorjahr und laufendes Jahr relativ, damit gespeicherte Ansichten mitwandern) */
    private function years(): array {
        $current = CarbonImmutable::today()->year;
        $years = [
            'previous' => (string) __('service_plan.filter.previous_year', ['year' => $current - 1]),
            'current' => (string) __('service_plan.filter.current_year', ['year' => $current]),
        ];
        for ($year = $current + 1; $year >= $current - self::YEAR_RANGE; $year--) {
            if ($year !== $current && $year !== $current - 1) {
                $years[(string) $year] = (string) $year;
            }
        }

        return $years;
    }

    /**
     * Merkmale aus anderen Quellen (Pflegegrad) für die Kunden der Seite.
     *
     * @param  list<ServicePlanRow>  $rows
     * @return array{labels: list<string>, values: array<int, array<string, string>>}
     */
    private function derivedTraits(Organization $organization, ?string $ruleSetKey, array $rows): array {
        $ruleSet = $ruleSetKey !== null ? $this->ruleSets->get($organization, $ruleSetKey) : null;
        if ($ruleSet === null || $rows === []) {
            return ['labels' => [], 'values' => []];
        }
        $ids = array_values(array_unique(array_map(static fn (ServicePlanRow $row): int => $row->customerId, $rows)));
        $labels = [];
        $values = [];
        foreach (Customer::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organization->id)->whereIn('id', $ids)->get() as $customer) {
            $values[(int) $customer->id] = $ruleSet->derivedTraits($customer);
            foreach (array_keys($values[(int) $customer->id]) as $label) {
                $labels[$label] = true;
            }
        }

        return ['labels' => array_keys($labels), 'values' => $values];
    }

    /** @param  array<string, mixed>  $filters */
    private function export(Request $request, ServicePlanResult $result, array $filters): Response {
        $query = $result->query;
        $header = [(string) __('service_plan.column.customer_number'), (string) __('service_plan.column.customer')];
        $rows = [];
        if ($query->isYearView()) {
            foreach ($result->columns as $column) {
                foreach (['state', 'due', 'responsible', 'documents', 'note'] as $part) {
                    $header[] = $column->label . ': ' . __('service_plan.column.' . $part);
                }
            }
            foreach ($result->rows as $row) {
                $line = [(string) $row->customerNumber, $row->customerName];
                foreach ($result->columns as $column) {
                    $cell = $row->cells[$column->service->key()] ?? null;
                    array_push($line, ...$this->exportCell($result, $cell));
                }
                $rows[] = $line;
            }
        } else {
            $header[] = (string) __('service_plan.column.service');
            $header[] = (string) __('service_plan.column.responsible');
            foreach ($result->traitFields as $field) {
                $header[] = $field->label;
            }
            for ($month = 1; $month <= 12; $month++) {
                $header[] = PeriodKey::of(PeriodKind::Month, $query->year, $month)->label();
            }
            foreach ($result->rows as $row) {
                $line = [(string) $row->customerNumber, $row->customerName, (string) ($row->service->label ?? ''), (string) ($result->responsibleName($row->responsibleId) ?? '')];
                $values = $result->traitsOf($row->customerId);
                foreach ($result->traitFields as $field) {
                    $line[] = $values !== null ? $values->display($field) : '';
                }
                $byMonth = array_fill(1, 12, '');
                foreach ($row->cells as $cell) {
                    $byMonth[$cell->period->end()->month] = $this->exportCell($result, $cell)[0];
                }
                array_push($line, ...array_values($byMonth));
                $rows[] = $line;
            }
        }

        $name = $query->isYearView() ? 'leistungsplan-' . $query->year . '.csv' : 'leistungsplan-perioden-' . $query->year . '.csv';

        return $this->csvWithMetadata([$header, ...$rows], $name, $query->isYearView() ? 'service_plan_year' : 'service_plan_periods', $filters, $request);
    }

    /** @return list<string> Stand, Fälligkeit, zuständig, Unterlagen da, Bemerkung */
    private function exportCell(ServicePlanResult $result, ?ServicePlanCell $cell): array {
        if ($cell === null || ! $cell->counts()) {
            return ['', '', '', '', ''];
        }

        return [
            $cell->state->label() . ($cell->overdue ? ' (' . __('service_plan.overdue') . ')' : ''),
            $cell->due?->format(Formats::date()) ?? '',
            (string) ($result->responsibleName($cell->responsibleId) ?? ''),
            $cell->documentsReceivedOn?->format(Formats::date()) ?? '',
            (string) ($cell->waiverReason ?? $cell->note ?? ''),
        ];
    }
}
