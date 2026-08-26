<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionRunController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommissionRunRequest;
use App\Models\Sales\CommissionSettlementRun;
use App\Services\Sales\CommissionSettlementService;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Http\{RedirectResponse, Response};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Provisions-Abrechnungslaeufe (Feature 146, MVP-729): Lauf anlegen →
 * Vorschau ansehen → schliessen (festgeschrieben) → CSV fuer die
 * Lohnabrechnung.
 *
 * Nach dem Schliessen gibt es keinen Weg zurueck; Korrekturen kommen als
 * Rueckrechnung in den Lauf der Folgeperiode. Eine Auszahlung findet in
 * WorkDiary bewusst nicht statt.
 */
class CommissionRunController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly CommissionSettlementService $settlement) {}

    public function index(): View {
        Gate::authorize('viewAny', CommissionSettlementRun::class);

        return view('sales.commission-runs.index', [
            'runs' => CommissionSettlementRun::query()
                ->with('closer:id,name')
                ->orderByDesc('period_start')
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString(),
            'canManage' => Gate::allows('create', CommissionSettlementRun::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', CommissionSettlementRun::class);

        $lastMonth = Carbon::today()->subMonthNoOverflow();

        return view('sales.commission-runs._form_dialog', [
            'defaultStart' => $lastMonth->copy()->startOfMonth(),
            'defaultEnd' => $lastMonth->copy()->endOfMonth(),
            'currencies' => CurrencyCode::cases(),
        ]);
    }

    public function store(CreateCommissionRunRequest $request): RedirectResponse {
        Gate::authorize('create', CommissionSettlementRun::class);

        $data = $request->validated();

        try {
            $run = $this->settlement->createRun(
                $this->currentOrganization(),
                Carbon::parse((string) $data['period_start'])->startOfDay(),
                Carbon::parse((string) $data['period_end'])->startOfDay(),
                CurrencyCode::from((string) $data['currency']),
                $data['period'] ?? null,
                Auth::user(),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('commission-runs.show', $run)->with('success', __('commission.flash.run_created'));
    }

    public function show(CommissionSettlementRun $run): View {
        Gate::authorize('view', $run);

        $rows = $this->settlement->rowsOf($run);

        return view('sales.commission-runs.show', [
            'run' => $run,
            'rows' => $rows,
            'totals' => $this->settlement->totals($rows, $run->currency),
            'perUser' => $this->settlement->perUser($rows, $run->currency),
            'canManage' => Gate::allows('create', CommissionSettlementRun::class),
        ]);
    }

    public function close(CommissionSettlementRun $run): RedirectResponse {
        Gate::authorize('close', $run);

        try {
            $this->settlement->close($run, Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('commission-runs.show', $run)->with('success', __('commission.flash.run_closed'));
    }

    public function destroy(CommissionSettlementRun $run): RedirectResponse {
        Gate::authorize('delete', $run);

        $run->delete();

        return redirect()->route('commission-runs.index')->with('success', __('commission.flash.run_deleted'));
    }

    public function export(CommissionSettlementRun $run): Response {
        Gate::authorize('export', $run);

        return response($this->settlement->exportCsv($run), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="provisionen-' . $run->period . '.csv"',
        ]);
    }
}
