<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingBudgetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Http\Requests\Accounting\SaveAccountingBudgetRequest;
use App\Models\Accounting\AccountingAccount;
use App\Models\{CostCenter, Organization};
use App\Services\Accounting\{AccountingBudgetService, FiscalCalendar};
use App\Support\{Sqid, Tz};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Budgetpflege je Konto (Feature 142, MVP-709).
 *
 * Lesen wie die übrigen Finanzberichte (`finance.accounting.view`), Pflege
 * mit dem Buchhaltungs-Schreibrecht (`finance.accounting.prepare`). Die
 * Werte selbst schreibt ausschließlich {@see AccountingBudgetService}.
 */
class AccountingBudgetController extends Controller {
    use ResolvesCurrentOrganization;
    use WritesReportCsv;

    public function __construct(
        private readonly AccountingBudgetService $budgets,
        private readonly FiscalCalendar $calendar,
    ) {}

    public function index(Request $request): View|SymfonyResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $years = $this->budgets->fiscalYears($organization, Tz::now());
        $year = $this->year($request, $organization);
        $costCenters = $this->costCenters($organization);
        $costCenter = $this->costCenter($request, $costCenters);
        $data = $this->budgets->matrix($organization, $year, $costCenter?->id);

        if (in_array((string) $request->query('export', ''), ['csv', 'xlsx'], true)) {
            $header = [(string) __('accounting.ledger.column.number'), (string) __('accounting.ledger.column.name'), (string) __('accounting.budget.column.year_value')];
            foreach ($data['months'] as $month) {
                $header[] = $month->translatedFormat('M Y');
            }
            $header[] = (string) __('accounting.bwa.column.total');
            $rows = [$header];
            foreach ($data['rows'] as $row) {
                $line = [(string) $row['account']->number, (string) $row['account']->name, (string) ($row['year'] ?? '')];
                foreach ($data['months'] as $month) {
                    $line[] = (string) ($row['months'][$month->month] ?? '');
                }
                $line[] = (string) $row['total'];
                $rows[] = $line;
            }

            return $this->csvWithMetadata($rows, 'accounting-budget-' . $year . '.csv', 'accounting.budget', [
                'fiscal_year' => $year,
                'cost_center' => $costCenter?->code,
            ], $request);
        }

        return view('reports.accounting.budget', $data + [
            'year' => $year,
            'years' => $years,
            'costCenters' => $costCenters,
            'costCenter' => $costCenter,
            'canEdit' => Gate::allows(Permission::AccountingLedgerPrepare->value),
        ]);
    }

    /** Bearbeiten-Dialog (Jahreswert oder Monatswerte) eines Kontos. */
    public function form(Request $request, AccountingAccount $account): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_if((int) $account->organization_id !== (int) $organization->id, 404);

        $year = $this->year($request, $organization);
        $costCenter = $this->costCenter($request, $this->costCenters($organization));
        $matrix = $this->budgets->matrix($organization, $year, $costCenter?->id);
        $row = collect($matrix['rows'])->first(fn (array $r): bool => (int) $r['account']->id === (int) $account->id);

        return view('reports.accounting._budget_dialog', [
            'account' => $account,
            'year' => $year,
            'costCenter' => $costCenter,
            'months' => $matrix['months'],
            'row' => $row,
        ]);
    }

    public function update(SaveAccountingBudgetRequest $request, AccountingAccount $account): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort();
        abort_if((int) $account->organization_id !== (int) $organization->id, 404);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validated();
        $costCenterId = isset($data['cost_center']) ? (int) $data['cost_center'] : null;
        $this->budgets->save($organization, $account, (int) $data['fiscal_year'], $costCenterId, [
            'mode' => (string) $data['mode'],
            'year_amount' => $data['year_amount'] ?? null,
            'months' => $data['months'] ?? [],
            'note' => $data['note'] ?? null,
        ], $actor);

        return redirect()
            ->route('reports.accounting.budget.index', array_filter([
                'year' => (int) $data['fiscal_year'],
                'cost_center' => $costCenterId !== null ? Sqid::encode(CostCenter::class, $costCenterId) : null,
            ]))
            ->with('status', __('accounting.budget.flash.saved', ['account' => $account->displayLabel()]));
    }

    /** Vorjahr-Ist als Budget des gewählten Jahres übernehmen. */
    public function copyPreviousYear(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $year = $this->year($request, $organization);
        $costCenter = $this->costCenter($request, $this->costCenters($organization));

        $count = $this->budgets->copyPreviousYearActuals($organization, $year, $costCenter?->id, $actor);

        return redirect()
            ->route('reports.accounting.budget.index', array_filter(['year' => $year, 'cost_center' => $costCenter?->sqid]))
            ->with('status', __('accounting.budget.flash.copied', ['count' => $count, 'year' => $year - 1]));
    }

    private function year(Request $request, Organization $organization): int {
        $requested = (int) $request->input('year', 0);
        if ($requested >= 2000 && $requested <= 2100) {
            return $requested;
        }

        return $this->calendar->fiscalYearOf(Tz::now(), $this->calendar->startMonth($organization));
    }

    /** @return Collection<int, CostCenter> */
    private function costCenters(Organization $organization): Collection {
        return CostCenter::query()
            ->where('organization_id', $organization->id)
            ->orderBy('code')
            ->get();
    }

    /** @param Collection<int, CostCenter> $costCenters */
    private function costCenter(Request $request, Collection $costCenters): ?CostCenter {
        $raw = (string) $request->input('cost_center', '');
        if ($raw === '') {
            return null;
        }

        $costCenter = $costCenters->firstWhere('id', (int) Sqid::decodeOrNumeric(CostCenter::class, $raw));

        return $costCenter instanceof CostCenter ? $costCenter : null;
    }
}
