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

use App\Enums\User\{Permission, UserRole};
use App\Http\Controllers\Controller;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\{ModuleCatalog, ModuleScopeService, ModuleStatusResolver, PackageCatalog};
use App\Services\Navigation\{NavFocusService, StartPageResolver};
use App\Settings\SettingScope;
use App\Support\Setting;
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
        private readonly StartPageResolver $startPages,
    ) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::OrganizationScopeManage->value);

        $organization = $this->organization($request);

        $profile = $this->scope->profile($organization);
        $packageLabel = $profile['key'] !== null ? (app(PackageCatalog::class)->package($profile['key'])['label'] ?? $profile['key']) : null;

        return view('admin.scope.index', [
            'modules' => $this->status->forOrganization($organization),
            'presets' => $this->scope->presets(),
            'profile' => $profile,
            'growthModules' => $this->scope->growthModules($organization),
            'packageLabel' => $packageLabel,
            'pendingVersion' => $this->scope->pendingPackageVersion($organization),
            'canRevert' => $profile['snapshot'] !== [],
            'recommendation' => $this->scope->branchProfileRecommendation($organization),
            'scopeConfiguredAt' => is_array($organization->settings)
                ? ($organization->settings['scope_configured_at'] ?? null)
                : null,
            'startPageLabels' => StartPageResolver::labels(),
            'startPageRoles' => array_map(
                fn (UserRole $role): array => ['role' => $role, 'route' => $this->startPages->roleDefault($organization, $role)],
                StartPageResolver::CONFIGURABLE_ROLES,
            ),
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

    /**
     * Einrichtung „Was möchten Sie erledigen?" (Feature 165, MVP-1189):
     * Dialog mit Zielen, deren Wirkung direkt sichtbar ist, Startseiten je
     * Rolle und Geltungsmodus — überspringbar, dann gilt die Paketvorgabe.
     */
    public function setup(Request $request): View {
        Gate::authorize(Permission::OrganizationScopeManage->value);

        $organization = $this->organization($request);
        $package = $this->scope->packageFor($organization) ?? ($this->scope->profile($organization)['key'] ?? null);
        if ($package === null || ! app(PackageCatalog::class)->has($package)) {
            $package = 'start';
        }
        $preview = $this->scope->bundleFor($organization, $package, ['team'], true);

        return view('admin.scope._setup_dialog', [
            'package' => app(PackageCatalog::class)->package($package),
            'teamLicensed' => $preview['team_licensed'],
            'focus' => app(NavFocusService::class),
            'startPageLabels' => StartPageResolver::labels(),
            'startpages' => $preview['startpages'],
            'profile' => $this->scope->profile($organization),
        ]);
    }

    public function applySetup(Request $request): RedirectResponse {
        Gate::authorize(Permission::OrganizationScopeManage->value);

        $organization = $this->organization($request);
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'goals' => ['nullable', 'array'],
            'goals.*' => ['string', Rule::in(['orders', 'time', 'team', 'billing'])],
        ]);
        /** @var list<string> $goals */
        $goals = array_values(array_unique(array_map('strval', (array) ($data['goals'] ?? []))));

        $package = $this->scope->packageFor($organization) ?? ($this->scope->profile($organization)['key'] ?? null);
        if ($package === null || ! app(PackageCatalog::class)->has($package)) {
            $package = 'start';
        }
        $bundle = $this->scope->bundleFor($organization, $package, $goals, in_array('billing', $goals, true));
        $result = $this->scope->applyBundle($organization, $bundle, $user, 'package', $package);

        $message = __('scope.setup.applied', ['workspaces' => count($bundle['workspaces'])]);
        if ($bundle['team'] && ! $bundle['team_licensed']) {
            $message .= ' ' . __('scope.setup.team_not_licensed');
        }

        return redirect()->route('admin.scope.index')->with('success', $message . ' ' . $this->summary($result));
    }

    /**
     * „Funktionsumfang erweitern" (Feature 165, MVP-1195): Zustände je Modul,
     * Vorschau der Wirkung, bewusste Aktivierung; Freischaltung nur manuell.
     */
    public function growth(Request $request): View {
        Gate::authorize(Permission::OrganizationScopeManage->value);

        $organization = $this->organization($request);
        $overview = $this->scope->growthOverview($organization);
        $focus = app(NavFocusService::class);
        $workspaceLabel = fn (string $key): string => $focus->label($organization, $key);

        return view('admin.scope.growth', [
            'overview' => $overview,
            'workspaceLabel' => $workspaceLabel,
            'moduleLabel' => fn (string $code): string => __($this->catalog->label($code)),
            'licenseUid' => (string) $organization->license_uid,
            'pendingVersion' => $this->scope->pendingPackageVersion($organization),
            'profile' => $this->scope->profile($organization),
        ]);
    }

    public function activate(Request $request): RedirectResponse {
        Gate::authorize(Permission::OrganizationScopeManage->value);

        $organization = $this->organization($request);
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'module' => ['required', 'string', Rule::in($this->catalog->codes())],
        ]);
        $module = (string) $data['module'];
        if (! $this->status->statusFor($organization, $module)->isLicensed()) {
            return back()->with('error', __('scope.growth.activate_failed', ['module' => __($this->catalog->label($module))]));
        }

        $result = $this->scope->activateModule($organization, $module, $user);
        $focus = app(NavFocusService::class);
        $message = __('scope.growth.activated', ['module' => __($this->catalog->label($module))]);
        if ($result['workspaces'] !== []) {
            $message .= ' ' . __('scope.growth.activated_workspaces', [
                'workspaces' => implode(', ', array_map(fn (string $k): string => $focus->label($organization, $k), $result['workspaces'])),
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Rücknahme des kompakten Einstiegs (MVP-1196): Vorschau mit Änderungen
     * und Konflikten als Dialog, Übernahme per POST.
     */
    public function revertPreview(Request $request): View {
        Gate::authorize(Permission::OrganizationScopeManage->value);

        $organization = $this->organization($request);
        $preview = $this->scope->revertPreview($organization);
        abort_unless($preview['available'], Response::HTTP_NOT_FOUND);
        $focus = app(NavFocusService::class);

        return view('admin.scope._revert_dialog', [
            'preview' => $preview,
            'profile' => $this->scope->profile($organization),
            'moduleLabel' => fn (string $code): string => __($this->catalog->label($code)),
            'workspaceLabel' => fn (string $key): string => $focus->label($organization, $key),
            'startPageLabels' => StartPageResolver::labels(),
        ]);
    }

    public function revert(Request $request): RedirectResponse {
        Gate::authorize(Permission::OrganizationScopeManage->value);

        $organization = $this->organization($request);
        /** @var User $user */
        $user = $request->user();
        $request->validate(['override_conflicts' => ['nullable', 'boolean']]);
        $result = $this->scope->revert($organization, $user, $request->boolean('override_conflicts'));

        $dimension = static fn (string $d): string => __('scope.revert.dimension.' . $d);
        $message = __('scope.revert.done', ['reverted' => implode(', ', array_map($dimension, $result['reverted']))]);
        if ($result['skipped'] !== []) {
            $message .= ' ' . __('scope.revert.skipped', ['skipped' => implode(', ', array_map($dimension, $result['skipped']))]);
        }

        return redirect()->route('admin.scope.index')->with('success', $message);
    }

    /**
     * Geltungsmodus (Feature 165, MVP-1188): „bewusst begrenzt" hält
     * Lizenzzuwachs deaktiviert, „voller Umfang" lässt ihn sofort wirken.
     */
    public function saveMode(Request $request): RedirectResponse {
        Gate::authorize(Permission::OrganizationScopeManage->value);

        $organization = $this->organization($request);
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'scope_mode' => ['required', 'string', Rule::in([ModuleScopeService::MODE_LIMITED, ModuleScopeService::MODE_FULL])],
        ]);
        $this->scope->setMode($organization, (string) $data['scope_mode'], $user);

        return back()->with('success', __('scope.mode.saved'));
    }

    /**
     * Startseite je Rolle (MVP-799). Leere Auswahl entfernt die Vorgabe; geschrieben
     * wird über die Settings-Registry, die den Routennamen gegen die Liste prüft.
     */
    public function saveStartPages(Request $request): RedirectResponse {
        Gate::authorize(Permission::OrganizationScopeManage->value);

        $organization = $this->organization($request);
        $data = $request->validate([
            'startpages' => ['nullable', 'array'],
            'startpages.*' => ['nullable', 'string', Rule::in(StartPageResolver::routeOptions())],
        ]);

        foreach (StartPageResolver::CONFIGURABLE_ROLES as $role) {
            $route = $data['startpages'][$role->value] ?? null;
            $key = StartPageResolver::settingKey($role);
            if (is_string($route) && $route !== '') {
                Setting::set($key, $route, SettingScope::Organization, $organization, $request->user()?->id);
            } else {
                Setting::reset($key, SettingScope::Organization, $organization);
            }
        }

        return back()->with('success', __('scope.startpages.saved'));
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
