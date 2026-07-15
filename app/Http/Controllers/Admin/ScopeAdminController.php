<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScopeAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{Organization, User};
use App\Services\Licensing\{ModuleCatalog, ModuleScopeService, ModuleStatusResolver};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seite „Funktionsumfang" (Feature 081, MVP-373): Org-Admins reduzieren oder
 * erweitern den sichtbaren Funktionsumfang ihrer Organisation über kuratierte
 * Presets oder eine Modul-Checkliste. Reine Schreibhilfe für die
 * MVP-052-Modulkonfiguration — nur `Active` ↔ `InactiveByCustomer`,
 * Lizenzverwaltung bleibt bei `platform.featureFlag.override`.
 */
class ScopeAdminController extends Controller {
    public function __construct(
        private readonly ModuleScopeService $scope,
        private readonly ModuleStatusResolver $status,
        private readonly ModuleCatalog $catalog,
    ) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::OrganizationScopeManage->value);

        $organization = $this->organization($request);

        return view('admin.scope.index', [
            'modules' => $this->status->forOrganization($organization),
            'presets' => $this->scope->presets(),
            'recommendation' => $this->scope->branchProfileRecommendation($organization),
            'scopeConfiguredAt' => is_array($organization->settings)
                ? ($organization->settings['scope_configured_at'] ?? null)
                : null,
        ]);
    }

    public function save(Request $request): RedirectResponse {
        Gate::authorize(Permission::OrganizationScopeManage->value);

        $organization = $this->organization($request);
        /** @var User $user */
        $user = $request->user();

        if ($request->filled('preset')) {
            $data = $request->validate([
                'preset' => ['required', 'string', Rule::in(array_keys($this->scope->presets()))],
            ]);
            $result = $this->scope->applyPreset($organization, (string) $data['preset'], $user);

            return back()->with('success', $this->summary($result));
        }

        if ($request->boolean('apply_recommendation')) {
            $recommendation = $this->scope->branchProfileRecommendation($organization);
            if ($recommendation === null) {
                return back()->with('error', __('scope.flash.no_recommendation'));
            }
            $result = $this->scope->setActiveModules($organization, $recommendation['modules'], $user, 'branch:' . $recommendation['code']);

            return back()->with('success', $this->summary($result));
        }

        $data = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in($this->catalog->codes())],
        ]);
        /** @var list<string> $active */
        $active = array_values($data['modules'] ?? []);
        $result = $this->scope->setActiveModules($organization, $active, $user);

        return back()->with('success', $this->summary($result));
    }

    private function organization(Request $request): Organization {
        $organization = $request->user()?->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        return $organization;
    }

    /** @param array{disabled: list<string>, enabled: list<string>} $result */
    private function summary(array $result): string {
        return __('scope.flash.saved', [
            'disabled' => count($result['disabled']),
            'enabled' => count($result['enabled']),
        ]);
    }
}
