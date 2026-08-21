<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterBillingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SaveMeterBillingAgreementRequest;
use App\Models\{Asset, Customer, Project};
use App\Models\Metering\{MeterBillingAgreement, MeterBillingRun};
use App\Services\Metering\MeterBillingService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Zählerstands-Faktura (Feature 116, MVP-605).
 *
 * Der Lauf erzeugt ausschließlich Entwürfe; die Oberfläche zeigt deshalb
 * neben den Vereinbarungen vor allem die **übersprungenen** Läufe — sie sind
 * die Arbeit, die wirklich anfällt.
 */
class MeterBillingController extends Controller {
    public function __construct(private readonly MeterBillingService $billing) {}

    public function index(): View {
        $this->authorizeBilling();

        return view('metering.index', [
            'agreements' => MeterBillingAgreement::query()
                ->with(['customer:id,name,company', 'asset:id,name', 'project:id,name'])
                ->orderBy('next_run_on')
                ->paginate(50),
            // Übersprungene Läufe der letzten 12 Monate: fehlende Ablesungen
            // sind der Grund, warum eine Rechnung ausbleibt.
            'skipped' => MeterBillingRun::query()
                ->with(['agreement.customer:id,name,company', 'agreement.asset:id,name'])
                ->whereNotNull('skipped_reason')
                ->whereDate('period_end', '>=', now()->subYear()->toDateString())
                ->orderByDesc('period_end')
                ->limit(50)
                ->get(),
        ]);
    }

    public function form(?MeterBillingAgreement $agreement = null): View {
        $this->authorizeBilling();

        return view('metering._form_dialog', [
            'agreement' => $agreement,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'company']),
            'assets' => Asset::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveMeterBillingAgreementRequest $request): RedirectResponse {
        $this->authorizeBilling();

        MeterBillingAgreement::query()->create($request->validated() + [
            'organization_id' => $request->user()?->organization_id,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('metering.index')->with('status', __('metering.created'));
    }

    public function update(SaveMeterBillingAgreementRequest $request, MeterBillingAgreement $agreement): RedirectResponse {
        $this->authorizeBilling();
        $agreement->update($request->validated());

        return redirect()->route('metering.index')->with('status', __('metering.updated'));
    }

    /** Einzelne Vereinbarung sofort abrechnen (Nachlauf ohne auf den Scheduler zu warten). */
    public function run(Request $request, MeterBillingAgreement $agreement): RedirectResponse {
        $this->authorizeBilling();
        $result = $this->billing->runAgreement($agreement);

        if ($result['blocked'] > 0) {
            return back()->with('error', __('metering.blocked_external'));
        }

        return back()->with('status', __('metering.run_done', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]));
    }

    private function authorizeBilling(): void {
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);
    }
}
