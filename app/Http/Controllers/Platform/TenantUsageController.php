<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantUsageController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Enums\Platform\TenantPlanRequestStatus;
use App\Http\Controllers\Concerns\RequiresPlatformOperator;
use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Models\Platform\{TenantPlanRequest, TenantUsageSnapshot};
use App\Services\Licensing\ModuleStatusResolver;
use App\Services\Metrics\OperationsMetricsService;
use App\Services\Platform\TenantBillingService;
use App\Support\{CsvExport, Sqid};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Nutzungsübersicht je Mandant für den Plattformbetrieb (MVP-951); nur für Betreiber.
 */
class TenantUsageController extends Controller {
    use RequiresPlatformOperator;

    public function index(OperationsMetricsService $metrics, ModuleStatusResolver $modules): View {
        $this->assertPlatformOperator();
        $organizations = Organization::query()->withoutGlobalScopes()->withCount(['users' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deactivated_at')])->orderBy('name')->paginate(50);
        $rows = [];
        foreach ($organizations as $organization) {
            $usage = $metrics->tenantUsage($organization);
            $rows[] = [
                'organization' => $organization,
                'users' => (int) $organization->getAttribute('users_count'),
                'active_users' => $usage['active_users'],
                'bytes' => $usage['bytes'],
                'last_activity' => $usage['last_activity'],
                'modules' => count(array_filter($modules->forOrganization($organization), static fn (array $row): bool => $row['available'])),
                'status' => $organization->tenantStatus(),
            ];
        }

        $planRequests = TenantPlanRequest::query()->withoutGlobalScopes()->where('status', TenantPlanRequestStatus::Open->value)->with(['organization' => fn ($q) => $q->withoutGlobalScopes()])->orderBy('id')->get();

        return view('admin.organizations.usage', ['rows' => $rows, 'organizations' => $organizations, 'planRequests' => $planRequests]);
    }

    /** Nutzungsabrechnung eines Monats (MVP-956), auch als CSV. */
    public function billing(Request $request, TenantBillingService $billing): View|Response {
        $this->assertPlatformOperator();
        $months = $billing->months();
        $month = $request->filled('month') && in_array($request->string('month')->toString(), $months, true) ? $request->string('month')->toString() : ($months[0] ?? null);
        $snapshots = $month !== null ? $billing->forMonth(CarbonImmutable::parse($month . '-01')) : collect();
        if ($month !== null && $request->query('export') === 'csv') {
            $rows = $snapshots->map(static fn (TenantUsageSnapshot $s): array => [
                (string) ($s->organization->name ?? $s->organization_id), $s->plan, implode(' ', $s->addons ?? []), $s->users, $s->active_users ?? '', $s->storage_bytes, $s->modules, (string) $s->amount, $s->currency,
            ])->all();

            return response(CsvExport::toString(['Organisation', 'Tarif', 'Addons', 'Nutzer', 'AktiveNutzer', 'SpeicherBytes', 'Module', 'Betrag', 'Waehrung'], $rows), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="nutzungsabrechnung_' . $month . '.csv"',
            ]);
        }

        return view('admin.organizations.billing', ['snapshots' => $snapshots, 'months' => $months, 'month' => $month]);
    }

    /** Tarifanfrage schließen (MVP-957); die Lizenz stellt der Betreiber über die Lizenzseite aus. */
    public function decidePlanRequest(Request $request, string $planRequest): RedirectResponse {
        $this->assertPlatformOperator();
        $model = TenantPlanRequest::query()->withoutGlobalScopes()->findOrFail(Sqid::decodeOrNumeric(TenantPlanRequest::class, $planRequest));
        $data = $request->validate([
            'decision' => ['required', Rule::in([TenantPlanRequestStatus::Done->value, TenantPlanRequestStatus::Declined->value])],
            'decision_note' => ['nullable', 'string', 'max:500'],
        ]);
        $target = TenantPlanRequestStatus::from($data['decision']);
        abort_unless($model->status->canTransitionTo($target), 409);
        $model->forceFill(['status' => $target, 'decider_user_id' => $this->authUser()->id, 'decided_at' => now(), 'decision_note' => $data['decision_note'] ?? null])->save();
        $model->audit('tenantPlanRequest.' . $target->value, []);

        return back()->with('success', __('platform_usage.plan_request.flash.decided'));
    }
}
