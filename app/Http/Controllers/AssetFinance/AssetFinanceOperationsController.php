<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceOperationsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\AssetFinance;

use App\Enums\AssetFinance\{AssetFinanceDeadlineKind, AssetFinanceEndKind, AssetFinanceUsageLimitKind};
use App\Http\Controllers\Controller;
use App\Models\AssetFinance\{AssetFinanceContract, AssetFinanceDeadline, AssetFinanceEndProcess, AssetFinanceOption, AssetFinanceRateSchedule, AssetFinanceUsageLimit};
use App\Models\IncomingEInvoice;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\AssetFinance\AssetFinanceService;
use App\Support\Sqid;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Operative Vorgänge der Leasingakte (MVP-273–276): Fristenkalender,
 * Ratenplan-Referenzen (Eingangsrechnung), Nutzungslimits/Ist-Werte,
 * Optionen und Rückgabe-/Ende-Prozess.
 */
class AssetFinanceOperationsController extends Controller {
    public function __construct(private readonly AssetFinanceService $service) {}

    /** Fristenliste über alle Verträge (MVP-273). */
    public function deadlines(Request $request): View {
        Gate::authorize('viewAny', AssetFinanceContract::class);

        return view('asset-finance.deadlines', [
            'deadlines' => AssetFinanceDeadline::query()
                ->with(['contract', 'responsible'])
                ->when($request->string('status')->toString() !== '', fn($q) => $q->where('status', $request->string('status')->toString()), fn($q) => $q->open())
                ->orderBy('due_on')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }

    public function storeDeadline(Request $request, AssetFinanceContract $contract): RedirectResponse {
        Gate::authorize('update', $contract);

        if ($request->filled('responsible_user_id')) {
            $request->merge(['responsible_user_id' => Sqid::decodeOrNumeric(\App\Models\User::class, $request->input('responsible_user_id'))]);
        }

        $data = $request->validate([
            'kind' => ['required', Rule::enum(AssetFinanceDeadlineKind::class)],
            'due_on' => ['required', 'date'],
            'warn_days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'responsible_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $contract->deadlines()->create(array_merge($data, [
            'organization_id' => $contract->organization_id,
            'warn_days_before' => (int) ($data['warn_days_before'] ?? 30),
        ]));

        return back()->with('status', __('Frist eingetragen.'));
    }

    public function completeDeadline(Request $request, AssetFinanceDeadline $deadline): RedirectResponse {
        Gate::authorize('update', $deadline->contract()->firstOrFail());

        $deadline->forceFill([
            'status' => 'done',
            'done_at' => now(),
            'done_by' => $request->user()?->id,
        ])->save();

        return back()->with('status', __('Frist erledigt.'));
    }

    /** Ratenzeile mit Eingangsrechnung referenzieren (MVP-274, D11). */
    public function linkSchedule(Request $request, AssetFinanceRateSchedule $schedule): RedirectResponse {
        Gate::authorize('finance', $schedule->contract()->firstOrFail());

        $request->merge(['incoming_einvoice_id' => Sqid::decodeOrNumeric(IncomingEInvoice::class, $request->input('incoming_einvoice_id'))]);
        $data = $request->validate([
            'incoming_einvoice_id' => ['required', 'integer', new ExistsInCurrentOrganization('incoming_einvoices')],
        ]);

        try {
            $this->service->linkIncomingInvoice($schedule, IncomingEInvoice::query()->whereKey($data['incoming_einvoice_id'])->firstOrFail());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['incoming_einvoice_id' => $e->getMessage()]);
        }

        return back()->with('status', __('Eingangsrechnung referenziert.'));
    }

    public function storeUsageLimit(Request $request, AssetFinanceContract $contract): RedirectResponse {
        Gate::authorize('update', $contract);

        $data = $request->validate([
            'kind' => ['required', Rule::enum(AssetFinanceUsageLimitKind::class)],
            'limit_value' => ['required', 'numeric', 'min:0'],
            'period' => ['required', Rule::in(AssetFinanceUsageLimit::PERIODS)],
            'overrun_fee_per_unit' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $contract->usageLimits()->create(array_merge($data, ['organization_id' => $contract->organization_id]));

        return back()->with('status', __('Nutzungslimit hinterlegt.'));
    }

    /** Ist-Wert erfassen — manuell oder aus dem letzten Zählerstand (MVP-275). */
    public function recordUsage(Request $request, AssetFinanceUsageLimit $limit): RedirectResponse {
        Gate::authorize('update', $limit->contract()->firstOrFail());

        $data = $request->validate(['actual_value' => ['nullable', 'numeric', 'min:0']]);

        try {
            $this->service->recordUsage($limit, $request->user() ?? abort(401), isset($data['actual_value']) ? (float) $data['actual_value'] : null);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['actual_value' => $e->getMessage()]);
        }

        return back()->with('status', __('Ist-Wert erfasst.'));
    }

    public function storeOption(Request $request, AssetFinanceContract $contract): RedirectResponse {
        Gate::authorize('finance', $contract);

        $data = $request->validate([
            'kind' => ['required', Rule::in(AssetFinanceOption::KINDS)],
            'exercisable_from' => ['nullable', 'date'],
            'exercisable_until' => ['nullable', 'date', 'after_or_equal:exercisable_from'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $contract->options()->create(array_merge($data, ['organization_id' => $contract->organization_id]));

        return back()->with('status', __('Option hinterlegt.'));
    }

    public function exerciseOption(Request $request, AssetFinanceOption $option): RedirectResponse {
        Gate::authorize('finance', $option->contract()->firstOrFail());

        try {
            $this->service->exerciseOption($option, $request->user() ?? abort(401));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['option' => $e->getMessage()]);
        }

        return back()->with('status', __('Option ausgeübt (auditiert).'));
    }

    /** Rückgabe-/Ende-Prozess starten (MVP-276). */
    public function storeEndProcess(Request $request, AssetFinanceContract $contract): RedirectResponse {
        Gate::authorize('update', $contract);

        $data = $request->validate([
            'kind' => ['required', Rule::enum(AssetFinanceEndKind::class)],
            'condition_note' => ['nullable', 'string', 'max:4000'],
            'meter_value' => ['nullable', 'numeric', 'min:0'],
            'operating_hours' => ['nullable', 'numeric', 'min:0'],
            'damages' => ['nullable', 'string', 'max:4000'],
            'accessories' => ['nullable', 'string', 'max:4000'],
            'follow_up_amount' => ['nullable', 'numeric'],
            'new_ends_on' => ['nullable', 'date', 'required_if:kind,extension'],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);

        $contract->endProcesses()->create(array_merge($data, [
            'organization_id' => $contract->organization_id,
            'status' => 'in_progress',
        ]));

        // Statusmodell: Akte tritt in die Endphase ein (außer Verlängerung).
        if ($contract->status === \App\Enums\AssetFinance\AssetFinanceStatus::Active && $data['kind'] !== AssetFinanceEndKind::Extension->value) {
            $contract->forceFill(['status' => \App\Enums\AssetFinance\AssetFinanceStatus::Ending->value])->save();
        }

        return back()->with('status', __('Ende-Prozess gestartet.'));
    }

    public function completeEndProcess(Request $request, AssetFinanceEndProcess $endProcess): RedirectResponse {
        Gate::authorize('update', $endProcess->contract()->firstOrFail());

        try {
            $this->service->completeEndProcess($endProcess, $request->user() ?? abort(401));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['end_process' => $e->getMessage()]);
        }

        return back()->with('status', __('Ende-Prozess abgeschlossen — Aktenstatus aktualisiert.'));
    }
}
