<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenancePlanController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Asset;

use App\Enums\Asset\MaintenanceDueAction;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\SaveMaintenancePlanRequest;
use App\Models\Asset\{Asset, MaintenancePlan};
use App\Models\Platform\User;
use App\Services\Asset\{AssetFormOptions, MaintenancePlanService};
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MaintenancePlanController extends Controller {
    public function __construct(
        private readonly MaintenancePlanService $plans,
        private readonly AssetFormOptions $options,
        private readonly FeatureFlagResolver $features,
    ) {}

    public function create(Asset $asset): View {
        Gate::authorize('update', $asset);
        Gate::authorize('create', MaintenancePlan::class);

        return view('assets._maintenance_form_dialog', $this->dialogData($asset, null));
    }

    /** Bestehenden Plan im selben Dialog bearbeiten (Vollscan 2026-10-21). */
    public function edit(Asset $asset, MaintenancePlan $plan): View {
        $this->ensurePlanBelongsToAsset($asset, $plan);
        Gate::authorize('update', $plan);

        return view('assets._maintenance_form_dialog', $this->dialogData($asset, $plan));
    }

    public function store(Asset $asset, SaveMaintenancePlanRequest $request): RedirectResponse {
        Gate::authorize('update', $asset);
        Gate::authorize('create', MaintenancePlan::class);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $this->plans->create($asset, $user, $request->validated());

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', __('Wartungsplan angelegt.'));
    }

    public function update(Asset $asset, MaintenancePlan $plan, SaveMaintenancePlanRequest $request): RedirectResponse {
        $this->ensurePlanBelongsToAsset($asset, $plan);
        Gate::authorize('update', $plan);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $this->plans->update($plan, $user, $request->validated());

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', __('Wartungsplan aktualisiert.'));
    }

    public function complete(Asset $asset, MaintenancePlan $plan, Request $request): RedirectResponse {
        $this->ensurePlanBelongsToAsset($asset, $plan);
        Gate::authorize('complete', $plan);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $this->plans->markCompleted($plan, $user);

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', __('Wartung als erledigt vermerkt.'));
    }

    public function toggle(Asset $asset, MaintenancePlan $plan, Request $request): RedirectResponse {
        $this->ensurePlanBelongsToAsset($asset, $plan);
        Gate::authorize('update', $plan);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($plan->is_active) {
            $this->plans->pause($plan, $user);
            $message = __('Wartungsplan pausiert.');
        } else {
            $this->plans->resume($plan, $user);
            $message = __('Wartungsplan reaktiviert.');
        }

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', $message);
    }

    public function destroy(Asset $asset, MaintenancePlan $plan): RedirectResponse {
        $this->ensurePlanBelongsToAsset($asset, $plan);
        Gate::authorize('delete', $plan);

        $plan->delete();

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', __('Wartungsplan gelöscht.'));
    }

    /**
     * Vertragsbindung nur mit Leserecht auf SLA-Verträge; die Aktion „Ticket“ nur,
     * wenn der Helpdesk das Ticket auch anlegen kann.
     *
     * @return array<string, mixed>
     */
    private function dialogData(Asset $asset, ?MaintenancePlan $plan): array {
        $canBindContract = $this->authUser()->can(Permission::SlaContractView->value);

        return [
            'asset' => $asset,
            'plan' => $plan,
            'intervalKindOptions' => $this->options->intervalKindOptions(),
            'contractOptions' => $canBindContract
                ? $this->options->maintenanceContractOptions($asset, $plan?->sla_contract_id)
                : null,
            'dueActionOptions' => $this->features->isEnabled('module.helpdesk') || $plan?->due_action === MaintenanceDueAction::Ticket
                ? MaintenanceDueAction::options()
                : null,
        ];
    }

    private function ensurePlanBelongsToAsset(Asset $asset, MaintenancePlan $plan): void {
        if ($plan->asset_id !== $asset->id) {
            abort(404);
        }
    }
}
