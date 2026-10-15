<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkspaceAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\User\{Permission, UserRole};
use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Services\Navigation\{NavFocusService, StartPageResolver};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-Seite „Arbeitsbereiche" (Feature 082): Org-Admins kuratieren die
 * angebotenen Arbeitsbereiche, benennen sie um und setzen einen Org-Default.
 * Rein kosmetisch (D13/D16) — ändert keine Rechte/Module, der Default schlägt
 * nur vor. Recht: `organization.scope.manage`.
 */
class WorkspaceAdminController extends Controller {
    public function __construct(private readonly NavFocusService $focus) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::OrganizationScopeManage->value);

        $organization = $this->organization($request);
        $settings = is_array($organization->settings) ? $organization->settings : [];

        $available = $settings[NavFocusService::SETTING_AVAILABLE] ?? null;
        $labels = is_array($settings[NavFocusService::SETTING_LABELS] ?? null) ? $settings[NavFocusService::SETTING_LABELS] : [];

        // Alle Produkt-Bereiche (auch nicht angebotene, zum Reaktivieren); 'all' Pflicht.
        $rows = [];
        foreach ($this->focus->all() as $key => $def) {
            $rows[] = [
                'key' => (string) $key,
                'default_label' => __((string) ($def['label'] ?? $key)),
                'description' => __((string) ($def['description'] ?? '')),
                'icon' => (string) ($def['icon'] ?? 'apps'),
                'offered' => $key === 'all' || ! is_array($available) || in_array($key, $available, true),
                'mandatory' => $key === 'all',
                'label_override' => (string) ($labels[$key] ?? ''),
            ];
        }

        $roles = is_array($settings[NavFocusService::SETTING_ROLES] ?? null) ? $settings[NavFocusService::SETTING_ROLES] : [];

        return view('admin.workspaces.index', [
            'rows' => $rows,
            'default' => $this->focus->defaultFor($organization),
            'configuredAt' => $settings[NavFocusService::SETTING_CONFIGURED_AT] ?? null,
            // Rollen-Vorschläge (Feature 165, E6): dieselben Rollen wie die Startseiten je Rolle.
            'roleRows' => array_map(
                static fn (UserRole $role): array => ['role' => $role, 'key' => is_string($roles[$role->value] ?? null) ? $roles[$role->value] : ''],
                StartPageResolver::CONFIGURABLE_ROLES,
            ),
        ]);
    }

    public function save(Request $request): RedirectResponse {
        Gate::authorize(Permission::OrganizationScopeManage->value);

        $organization = $this->organization($request);
        $keys = array_keys($this->focus->all());

        $roleValues = array_map(static fn (UserRole $role): string => $role->value, StartPageResolver::CONFIGURABLE_ROLES);
        $data = $request->validate([
            'available' => ['nullable', 'array'],
            'available.*' => ['string', Rule::in($keys)],
            'default' => ['nullable', 'string', Rule::in($keys)],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['nullable', 'string', 'max:60'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['nullable', 'string', Rule::in($keys)],
        ]);

        // Angebotene = gewählte + 'all' (Pflicht); Rollen-Vorschläge und Default
        // nur, wenn angeboten — alles in einer Schreibstelle (Feature 165).
        $roles = [];
        foreach ((array) ($data['roles'] ?? []) as $role => $key) {
            if (in_array((string) $role, $roleValues, true) && is_string($key) && $key !== '') {
                $roles[(string) $role] = $key;
            }
        }
        $this->focus->saveCuration(
            $organization,
            array_values(array_map('strval', (array) ($data['available'] ?? []))),
            (string) ($data['default'] ?? 'all'),
            $roles,
            (array) ($data['labels'] ?? []),
        );

        return back()->with('success', __('scope.focus.admin.saved'));
    }

    private function organization(Request $request): Organization {
        $organization = $request->user()?->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        return $organization;
    }
}
