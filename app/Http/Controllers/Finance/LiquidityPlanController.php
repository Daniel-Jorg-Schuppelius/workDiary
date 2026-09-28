<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LiquidityPlanController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\LiquidityPlanRecurrence;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Finance\{LiquidityForecastSnapshot, LiquidityPlanItem};
use App\Services\Accounting\{JournalService, LiquiditySnapshotService};
use App\Support\{Sqid, Tz};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Planpositionen der Liquiditätsvorschau und Plan/Ist-Vergleich (MVP-984). */
class LiquidityPlanController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly LiquiditySnapshotService $snapshots) {}

    public function index(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        return view('reports.accounting.liquidity-plan', [
            'items' => LiquidityPlanItem::query()->where('organization_id', $organization->id)->orderBy('starts_on')->get(),
            'canEdit' => Gate::allows(Permission::AccountingLedgerPrepare->value),
        ]);
    }

    public function form(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);

        return view('reports.accounting._liquidity_plan_dialog', ['recurrences' => LiquidityPlanRecurrence::cases()]);
    }

    public function store(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $data = $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'direction' => ['required', 'in:in,out'],
            'planned_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'starts_on' => ['required', 'date'],
            'recurrence' => ['required', Rule::enum(LiquidityPlanRecurrence::class)],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $currency = app(JournalService::class)->baseCurrency($organization);
        LiquidityPlanItem::query()->create([
            'organization_id' => $organization->id,
            'label' => $data['label'],
            'direction' => $data['direction'],
            'currency' => $currency,
            'planned_amount' => Money::of((string) $data['planned_amount'], $currency),
            'starts_on' => $data['starts_on'],
            'recurrence' => $data['recurrence'],
            'ends_on' => $data['recurrence'] === LiquidityPlanRecurrence::Monthly->value ? ($data['ends_on'] ?? null) : null,
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('reports.accounting.liquidity-plan.index')->with('status', __('accounting.liquidity_plan.flash.saved'));
    }

    public function destroy(LiquidityPlanItem $item): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $item->delete();

        return redirect()->route('reports.accounting.liquidity-plan.index')->with('status', __('accounting.liquidity_plan.flash.removed'));
    }

    /** Plan/Ist: festgehaltener Wochenstand gegen die Kontobewegungen. */
    public function actual(Request $request): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $snapshots = LiquidityForecastSnapshot::query()->where('organization_id', $organization->id)->orderByDesc('taken_on')->limit(26)->get();
        $selected = $request->filled('snapshot')
            ? $snapshots->firstWhere('id', Sqid::decodeOrNumeric(LiquidityForecastSnapshot::class, $request->string('snapshot')->toString()))
            : $snapshots->first();

        return view('reports.accounting.liquidity-plan-actual', [
            'snapshots' => $snapshots,
            'snapshot' => $selected,
            'rows' => $selected instanceof LiquidityForecastSnapshot ? $this->snapshots->compare($selected, Tz::now()) : [],
            'canEdit' => Gate::allows(Permission::AccountingLedgerPrepare->value),
        ]);
    }

    public function snapshot(): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $snapshot = $this->snapshots->take($this->currentOrganizationOrAbort(), Tz::now());

        return redirect()->route('reports.accounting.liquidity-plan.actual', ['snapshot' => $snapshot->sqid])->with('status', __('accounting.liquidity_plan.flash.snapshot'));
    }
}
