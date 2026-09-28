<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostAllocationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Finance\{CostAllocationKey, CostCenter};
use App\Services\Accounting\{CostAllocationService, FiscalCalendar};
use App\Support\{Sqid, Tz};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Umlageschlüssel zwischen Kostenstellen je Geschäftsjahr (MVP-982). */
class CostAllocationController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly CostAllocationService $allocations,
        private readonly FiscalCalendar $calendar,
    ) {}

    public function index(Request $request): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $year = $this->year($request);

        return view('reports.accounting.allocations', [
            'year' => $year,
            'keys' => $this->allocations->keysFor($organization, $year)->groupBy('source_cost_center_id'),
            'canEdit' => Gate::allows(Permission::AccountingLedgerPrepare->value),
        ]);
    }

    public function form(Request $request): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        return view('reports.accounting._allocation_dialog', [
            'year' => $this->year($request),
            'costCenters' => CostCenter::query()->where('organization_id', $organization->id)->where('active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'source' => ['required', 'string'],
            'target' => ['required', 'string', 'different:source'],
            'share_percent' => ['required', 'numeric', 'gt:0', 'max:100'],
        ]);
        $source = CostCenter::query()->where('organization_id', $organization->id)->findOrFail(Sqid::decodeOrNumeric(CostCenter::class, (string) $data['source']));
        $target = CostCenter::query()->where('organization_id', $organization->id)->findOrFail(Sqid::decodeOrNumeric(CostCenter::class, (string) $data['target']));
        /** @var \App\Models\Platform\User $user */
        $user = $request->user();
        $this->allocations->save($organization, (int) $data['year'], $source, $target, NumberHelper::normalizeDecimalString((string) $data['share_percent']), $user);

        return redirect()->route('reports.accounting.allocations.index', ['year' => (int) $data['year']])->with('status', __('accounting.allocation.flash.saved'));
    }

    public function destroy(CostAllocationKey $allocation): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $year = $allocation->fiscal_year;
        $allocation->delete();

        return redirect()->route('reports.accounting.allocations.index', ['year' => $year])->with('status', __('accounting.allocation.flash.removed'));
    }

    private function year(Request $request): int {
        $requested = (int) $request->input('year', 0);

        return $requested >= 2000 && $requested <= 2100
            ? $requested
            : $this->calendar->fiscalYearOf(Tz::now(), $this->calendar->startMonth($this->currentOrganizationOrAbort()));
    }
}
