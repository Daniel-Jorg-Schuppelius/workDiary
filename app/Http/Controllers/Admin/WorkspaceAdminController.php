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

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Navigation\NavFocusService;
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

        return view('admin.workspaces.index', [
            'rows' => $rows,
            'default' => $this->focus->defaultFor($organization),
            'configuredAt' => $settings[NavFocusService::SETTING_CONFIGURED_AT] ?? null,
        ]);
    }

    public function save(Request $request): RedirectResponse {
        Gate::authorize(Permission::OrganizationScopeManage->value);

        $organization = $this->organization($request);
        $keys = array_keys($this->focus->all());

        $data = $request->validate([
            'available' => ['nullable', 'array'],
            'available.*' => ['string', Rule::in($keys)],
            'default' => ['nullable', 'string', Rule::in($keys)],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['nullable', 'string', 'max:60'],
        ]);

        // Angebotene = gewählte + 'all' (Pflicht); Config-Reihenfolge für stabile Sortierung.
        $chosen = array_values((array) ($data['available'] ?? []));
        $available = array_values(array_filter(
            $keys,
            static fn(string $k): bool => $k === 'all' || in_array($k, $chosen, true)
        ));

        // Default nur zulassen, wenn er auch angeboten wird — sonst 'all'.
        $default = (string) ($data['default'] ?? 'all');
        if (! in_array($default, $available, true)) {
            $default = 'all';
        }

        // Nur nicht-leere Labels übernehmen.
        $labels = [];
        foreach ((array) ($data['labels'] ?? []) as $key => $value) {
            $value = trim((string) $value);
            if ($value !== '' && in_array((string) $key, $keys, true)) {
                $labels[(string) $key] = $value;
            }
        }

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $settings[NavFocusService::SETTING_AVAILABLE] = $available;
        $settings[NavFocusService::SETTING_DEFAULT] = $default;
        $settings[NavFocusService::SETTING_LABELS] = $labels;
        $settings[NavFocusService::SETTING_CONFIGURED_AT] = now()->toIso8601String();
        $organization->settings = $settings;
        $organization->save();

        return back()->with('success', __('scope.focus.admin.saved'));
    }

    private function organization(Request $request): Organization {
        $organization = $request->user()?->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        return $organization;
    }
}
