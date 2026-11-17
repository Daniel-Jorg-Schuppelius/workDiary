<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Http\Requests\Customer\UpdateCustomerDeadlineRequest;
use App\Models\Customer\{Customer, CustomerDeadline};
use App\Models\Platform\User;
use App\Services\Customer\Deadlines\{CustomerDeadlineOverview, CustomerDeadlineRuleSets, CustomerDeadlineService};
use App\Support\{Formats, SortableQuery, Sqid};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Kundenfristen (Feature 197, MVP-1632/1633): Übersicht mit Filtern, Ampel
 * und Export (CSV/XLSX) sowie der Dialog einer Frist mit Verlauf und den
 * Schritten erledigt, verlängert, vorangefordert, entfällt, wieder öffnen.
 */
class CustomerDeadlineController extends Controller {
    use WritesReportCsv;

    private const int PER_PAGE = 50;

    /** @var array<string, string> */
    private const array SORTS = [
        'due_on' => 'customer_deadlines.due_on',
        'period' => 'customer_deadlines.period_key',
        'kind' => 'customer_deadlines.kind',
        'status' => 'customer_deadlines.status',
    ];

    public function __construct(
        private readonly CustomerDeadlineOverview $overview,
        private readonly CustomerDeadlineRuleSets $ruleSets,
        private readonly CustomerDeadlineService $deadlines,
    ) {}

    public function index(Request $request): View|Response {
        Gate::authorize('viewAny', CustomerDeadline::class);
        $organization = $this->currentOrganization();
        $viewer = $this->authUser();
        $filters = $this->filters($request);
        $today = CarbonImmutable::today();

        $query = $this->overview->query($organization, $viewer, $filters, $today);
        [$sort, $dir] = SortableQuery::apply($query, $request, self::SORTS, 'due_on', 'asc');
        $query->orderBy('customer_deadlines.id');

        if (in_array($request->string('export')->toString(), ['csv', 'xlsx'], true)) {
            return $this->export($request, $query->with(['customer:id,name,number', 'responsible:id,name'])->get(), $filters);
        }

        $available = $this->ruleSets->available($organization);
        $kinds = [];
        foreach ($available as $ruleSet) {
            $kinds += $ruleSet->kinds();
        }
        $warnDays = [];
        foreach (array_keys($available) as $key) {
            $warnDays[$key] = $this->ruleSets->warnDays($organization, $key);
        }

        return view('customer-deadlines.index', [
            'deadlines' => $query->with(['customer:id,name,number', 'responsible:id,name'])->paginate(self::PER_PAGE)->withQueryString(),
            'counts' => $this->overview->counts($organization, $viewer, $filters, $today),
            'filters' => $filters,
            'ruleSets' => array_map(static fn ($ruleSet): string => $ruleSet->label(), $available),
            'enabledRuleSets' => $this->ruleSets->enabledKeys($organization),
            'kinds' => $kinds,
            'warnDays' => $warnDays,
            'members' => User::query()->where('organization_id', $organization->id)->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
            'overview' => $this->overview,
            'organization' => $organization,
            'today' => $today,
            'sort' => $sort,
            'dir' => $dir,
            'canManage' => Gate::allows('manage', CustomerDeadline::class),
        ]);
    }

    /** Dialog einer Frist: Angaben, Verlauf und — mit Recht — die Schritte. */
    public function edit(CustomerDeadline $customerDeadline): View {
        $this->assertSameOrganization($customerDeadline);
        Gate::authorize('view', $customerDeadline);
        $organization = $this->currentOrganization();
        $customerDeadline->load(['customer:id,name,number', 'responsible:id,name', 'completer:id,name', 'diaryEntry', 'journal.actor']);

        return view('customer-deadlines._dialog', [
            'deadline' => $customerDeadline,
            'kindLabel' => $this->overview->kindLabel($organization, $customerDeadline),
            'ruleSetLabel' => $this->overview->ruleSetLabel($organization, $customerDeadline->rule_set),
            'canUpdate' => Gate::allows('update', $customerDeadline),
        ]);
    }

    public function update(UpdateCustomerDeadlineRequest $request, CustomerDeadline $customerDeadline): RedirectResponse {
        $this->assertSameOrganization($customerDeadline);
        Gate::authorize('update', $customerDeadline);
        $actor = $this->authUser();
        $text = StringHelper::trimToNull($request->input('text'));
        $date = static fn (string $field): CarbonImmutable => CarbonImmutable::parse((string) $request->input($field))->startOfDay();

        match ($request->string('action')->toString()) {
            'complete' => $this->deadlines->complete($customerDeadline, $actor, $text),
            'extend' => $this->deadlines->extend($customerDeadline, $date('due_on'), (string) $text, $actor),
            'advance' => $this->deadlines->requestAdvance($customerDeadline, $date('requested_on'), $date('due_on'), $actor),
            'waive' => $this->deadlines->waive($customerDeadline, (string) $text, $actor),
            default => $this->deadlines->reopen($customerDeadline, (string) $text, $actor),
        };

        return back()->with('success', __('customer_deadline.flash.' . $request->string('action')->toString()));
    }

    protected function exportNeedsReportPermission(): bool {
        // Fachliste der Kunden, nicht im Menü „Auswertungen“: das Leserecht der Seite deckt den Export.
        return false;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, CustomerDeadline>  $deadlines
     * @param  array<string, mixed>  $filters
     */
    private function export(Request $request, \Illuminate\Database\Eloquent\Collection $deadlines, array $filters): Response {
        $organization = $this->currentOrganization();
        $rows = [[
            (string) __('customer_deadline.column.customer_number'),
            (string) __('customer_deadline.column.customer'),
            (string) __('customer_deadline.column.rule_set'),
            (string) __('customer_deadline.column.kind'),
            (string) __('customer_deadline.column.period'),
            (string) __('customer_deadline.column.due_on'),
            (string) __('customer_deadline.column.original_due_on'),
            (string) __('customer_deadline.column.status'),
            (string) __('customer_deadline.column.responsible'),
            (string) __('customer_deadline.column.completed_at'),
            (string) __('customer_deadline.column.region_assumed'),
            (string) __('customer_deadline.column.note'),
        ]];
        foreach ($deadlines as $deadline) {
            $rows[] = [
                (string) ($deadline->customer->number ?? ''),
                (string) ($deadline->customer->name ?? ''),
                $this->overview->ruleSetLabel($organization, $deadline->rule_set),
                $this->overview->kindLabel($organization, $deadline),
                $deadline->period_key->key(),
                $deadline->due_on->format(Formats::date()),
                $deadline->original_due_on->format(Formats::date()),
                $deadline->status->label(),
                (string) ($deadline->responsible->name ?? ''),
                $deadline->completed_at?->format(Formats::date()) ?? '',
                $deadline->is_region_assumed ? (string) __('customer_deadline.yes') : '',
                (string) ($deadline->extension_reason ?? $deadline->note ?? ''),
            ];
        }

        return $this->csvWithMetadata($rows, 'kundenfristen.csv', 'customer_deadlines', $filters, $request);
    }

    /** @return array{rule_set: string|null, kind: string|null, responsible: string|null, responsible_id: int|null, month: string|null, status: string, customer_id: int|null} */
    private function filters(Request $request): array {
        $responsible = StringHelper::trimToNull($request->query('responsible'));
        $responsibleId = $responsible !== null && ! in_array($responsible, ['mine', 'none'], true) ? Sqid::decode(User::class, $responsible) : null;
        $status = $request->string('status')->toString();
        $customer = StringHelper::trimToNull($request->query('customer'));
        $month = StringHelper::trimToNull($request->query('month'));

        return [
            'rule_set' => StringHelper::trimToNull($request->query('rule_set')),
            'kind' => StringHelper::trimToNull($request->query('kind')),
            'responsible' => $responsible !== null && in_array($responsible, ['mine', 'none'], true) ? $responsible : ($responsibleId !== null ? $responsible : null),
            'responsible_id' => $responsibleId,
            'month' => $month !== null && preg_match('/^\d{4}-\d{2}$/', $month) === 1 ? $month : null,
            'status' => in_array($status, CustomerDeadlineOverview::STATUS_FILTERS, true) ? $status : 'open',
            'customer_id' => $customer !== null ? Sqid::decode(Customer::class, $customer) : null,
        ];
    }
}
