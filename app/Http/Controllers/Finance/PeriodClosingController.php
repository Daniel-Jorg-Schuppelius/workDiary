<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodClosingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Accounting\{AccountingFiscalYear, AccountingPeriod};
use App\Services\Accounting\{LedgerDatevExportService, OpeningBalanceImportService, PeriodClosingService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Periodenabschluss (Feature 125, MVP-677).
 *
 * Schließen und Wiedereröffnen sind getrennte Rechte — die Wiedereröffnung
 * hebt eine Festschreibung auf und verlangt zusätzlich eine Begründung.
 */
class PeriodClosingController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly PeriodClosingService $closing,
        private readonly OpeningBalanceImportService $openingBalances,
        private readonly LedgerDatevExportService $datev,
    ) {}

    public function index(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $years = AccountingFiscalYear::query()
            ->where('organization_id', $organization->id)
            ->with(['periods' => fn ($query) => $query->orderBy('sequence')])
            ->orderByDesc('starts_on')
            ->get();

        return view('finance.accounting.closing', [
            'years' => $years,
            'canClose' => Gate::allows(Permission::AccountingLedgerClose->value),
            'canReopen' => Gate::allows(Permission::AccountingLedgerReopen->value),
        ]);
    }

    /** Prüfstand einer Periode, bevor sie geschlossen wird. */
    public function preflight(AccountingPeriod $period): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerClose->value), 403);
        $this->assertSameOrganization($period);

        return view('finance.accounting._closing_dialog', [
            'period' => $period,
            'report' => $this->closing->preflight($period),
        ]);
    }

    public function softClose(Request $request, AccountingPeriod $period): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerClose->value), 403);
        $this->assertSameOrganization($period);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $this->closing->softClose($period, $actor);

        return back()->with('status', __('accounting.closing.flash.soft_closed'));
    }

    public function close(Request $request, AccountingPeriod $period): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerClose->value), 403);
        $this->assertSameOrganization($period);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $this->closing->close($period, $actor);

        return back()->with('status', __('accounting.closing.flash.closed'));
    }

    public function reopenForm(AccountingPeriod $period): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerReopen->value), 403);
        $this->assertSameOrganization($period);

        return view('finance.accounting._reopen_dialog', ['period' => $period]);
    }

    public function reopen(Request $request, AccountingPeriod $period): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerReopen->value), 403);
        $this->assertSameOrganization($period);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validate(['reopen_reason' => ['required', 'string', 'max:500']]);
        $this->closing->reopen($period, $actor, (string) $data['reopen_reason']);

        return back()->with('status', __('accounting.closing.flash.reopened'));
    }

    public function closeYear(Request $request, AccountingFiscalYear $year): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerClose->value), 403);
        abort_unless((int) $year->organization_id === (int) $this->currentOrganizationOrAbort()->id, 404);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $this->closing->closeFiscalYear($year, $actor);

        return back()->with('status', __('accounting.closing.flash.year_closed'));
    }

    /** Startsalden als Probelauf prüfen oder übernehmen. */
    public function importOpeningBalances(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerClose->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $path = $request->file('file')?->getRealPath();
        abort_if($path === null || $path === false, 422);

        if ($request->boolean('dry_run', true)) {
            $result = $this->openingBalances->dryRun($organization, $path);

            return back()->with('status', __('accounting.opening.flash.dry_run', [
                'lines' => count($result['lines']),
                'debit' => $result['debit'],
                'credit' => $result['credit'],
                'errors' => count($result['errors']),
            ]));
        }

        $entry = $this->openingBalances->import($organization, $path, $actor);

        return back()->with('status', __('accounting.opening.flash.imported', ['no' => (string) $entry->journal_no]));
    }

    /** DATEV-Übergabe aus den Festbuchungen des globalen Zeitraums. */
    public function datevExport(Request $request): \Symfony\Component\HttpFoundation\Response {
        abort_unless(Gate::allows(Permission::AccountingLedgerClose->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $result = $this->datev->build(
            $organization,
            \Carbon\CarbonImmutable::parse((string) $data['from']),
            \Carbon\CarbonImmutable::parse((string) $data['to']),
        );

        return response($result['csv'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="datev-journal-' . $data['from'] . '_' . $data['to'] . '.csv"',
        ]);
    }

    private function assertSameOrganization(AccountingPeriod $period): void {
        abort_unless((int) $period->organization_id === (int) $this->currentOrganizationOrAbort()->id, 404);
    }
}
