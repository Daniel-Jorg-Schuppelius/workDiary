<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\AssetFinance\{AssetFinanceContract, AssetFinanceCostSnapshot, AssetFinanceDeadline, AssetFinanceRateSchedule, AssetFinanceReportSnapshot, AssetFinanceUsageLimit};
use App\Services\AssetFinance\AssetFinanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Leasingberichte (MVP-277/279): Bestand, Restlaufzeiten, Fristen, Kosten
 * (Soll/Referenz-Ist), Limit-Überschreitungen und Rückgaberisiken —
 * Snapshots frieren den Stand ein (P2). Exporte enthalten Datenstand und
 * Abgrenzung (keine Bilanzierung, W11).
 */
class AssetFinanceReportController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly AssetFinanceService $service) {}

    public function index(): View {
        Gate::authorize('viewAny', AssetFinanceContract::class);

        return view('asset-finance.reports', array_merge($this->aggregate(), [
            'snapshots' => AssetFinanceReportSnapshot::query()->latest()->limit(10)->get(),
        ]));
    }

    public function snapshot(Request $request): RedirectResponse {
        Gate::authorize('viewAny', AssetFinanceContract::class);

        $actor = $request->user() ?? abort(401);

        AssetFinanceReportSnapshot::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'payload' => $this->aggregate(),
            'created_by' => $actor->id,
        ]);

        return back()->with('status', __('Berichts-Snapshot eingefroren.'));
    }

    /** Soll-/Ist-Snapshot je Vertrag (MVP-277, finance-Recht). */
    public function costSnapshot(Request $request, AssetFinanceContract $contract): RedirectResponse {
        Gate::authorize('finance', $contract);

        $actor = $request->user() ?? abort(401);

        AssetFinanceCostSnapshot::query()->create([
            'organization_id' => $contract->organization_id,
            'asset_finance_contract_id' => $contract->id,
            'period_start' => $contract->starts_on->toDateString(),
            'period_end' => now()->toDateString(),
            'payload' => array_merge($this->service->projection($contract), [
                'source' => 'workdiary.asset_finance',
                'disclaimer' => (string) __('Referenzwerte ohne bilanzielle oder steuerliche Aussage.'),
            ]),
            'created_by' => $actor->id,
        ]);

        return back()->with('status', __('Soll-/Ist-Snapshot eingefroren.'));
    }

    /** @return array<string, mixed> */
    private function aggregate(): array {
        $contracts = AssetFinanceContract::query()->with('usageLimits')->get();
        $open = $contracts->filter(fn (AssetFinanceContract $c) => $c->status->isOpen());

        $endingSoon = $open->filter(
            fn (AssetFinanceContract $c) => $c->ends_on !== null && $c->ends_on <= now()->addMonths(6),
        );

        $overruns = $contracts->flatMap(
            fn (AssetFinanceContract $c) => $c->usageLimits
                ->filter(fn (AssetFinanceUsageLimit $l) => $l->overrun() > 0)
                ->map(fn (AssetFinanceUsageLimit $l) => [
                    'contract' => $c->number,
                    'kind' => $l->kind->label(),
                    'overrun' => $l->overrun(),
                ]),
        )->values();

        $plannedTotal = round((float) AssetFinanceRateSchedule::query()->sum('amount'), 2);
        $referencedTotal = round((float) AssetFinanceRateSchedule::query()->where('status', 'paid')->sum('amount'), 2);

        return [
            'contractCount' => $contracts->count(),
            'openCount' => $open->count(),
            'endingSoon' => $endingSoon->map(fn (AssetFinanceContract $c) => [
                'number' => $c->number,
                'partner' => $c->partner_name,
                'ends_on' => $c->ends_on?->toDateString(),
                'status' => $c->status->value,
            ])->values()->all(),
            'openDeadlines' => AssetFinanceDeadline::query()->open()->count(),
            'missedDeadlines' => AssetFinanceDeadline::query()->where('status', 'missed')->count(),
            'plannedTotal' => $plannedTotal,
            'referencedTotal' => $referencedTotal,
            'openTotal' => round($plannedTotal - $referencedTotal, 2),
            'overruns' => $overruns->all(),
            'byStatus' => $contracts->groupBy(fn (AssetFinanceContract $c) => $c->status->value)->map->count()->all(),
            'disclaimer' => (string) __('Datenstand :date — operative Referenzwerte, keine Bilanzierung (HGB/IFRS 16) und keine steuerliche Zurechnung.', ['date' => now()->format('d.m.Y H:i')]),
        ];
    }
}
