<?php

/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\SortableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Organization::class);

        // Recovery: ist der Admin (noch) keiner Org zugeordnet, aber es
        // existiert mindestens eine Organisation (etwa nach Löschen der
        // eigenen Org oder Bootstrap), weisen wir ihn der ersten zu.
        // Damit greifen org-gebundene Policies (manage-members, Branding,
        // …) wieder, ohne dass der Admin die Zuordnung manuell pflegen
        // muss. Cross-Org-Wechsel bleibt über den Org-Switcher möglich.
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user instanceof \App\Models\User && $user->isAdmin() && empty($user->organization_id)) {
            $first = Organization::query()->orderBy('id')->first();
            if ($first instanceof Organization) {
                $user->forceFill(['organization_id' => $first->id])->save();
            }
        }

        $query = Organization::query()->withoutGlobalScopes()->withCount('users');

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'name' => 'name',
            'plan' => 'plan',
            'locale' => 'locale',
            'timezone' => 'timezone',
            'is_active' => 'is_active',
            'users' => 'users_count',
        ], 'name', 'asc');

        $organizations = $query->paginate((int) setting('pagination.organizations', 25))->withQueryString();

        return view('admin.organizations.index', compact('organizations', 'sort', 'dir'));
    }

    public function create(): View
    {
        Gate::authorize('create', Organization::class);

        return view('admin.organizations._form_dialog', ['organization' => new Organization]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Organization::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', 'in:'.implode(',', Organization::$plans)],
            'locale' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:64'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $organization = Organization::create($data);

        // Wenn der ausführende Admin (noch) keiner Organisation zugeordnet
        // ist – typischerweise nach Lösch-/Bootstrap-Szenarien – weisen wir
        // ihn der frisch angelegten Org zu, damit Org-bezogene Policies
        // (z. B. manage-members) sofort greifen.
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user instanceof \App\Models\User && empty($user->organization_id)) {
            $user->forceFill(['organization_id' => $organization->id])->save();
        }

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organisation wurde erstellt.'));
    }

    public function edit(Organization $organization): View
    {
        Gate::authorize('update', $organization);

        return view('admin.organizations._form_dialog', compact('organization'));
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('update', $organization);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', 'in:'.implode(',', Organization::$plans)],
            'locale' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:64'],
            'is_active' => ['boolean'],
            'compliance' => ['sometimes', 'array'],
            'compliance.mode' => ['sometimes', 'in:'.implode(',', Organization::$complianceModes)],
            'compliance.max_hours_day' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'compliance.min_rest_hours' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'compliance.max_hours_week' => ['sometimes', 'integer', 'min:1', 'max:168'],
            'compliance.max_consecutive_days' => ['sometimes', 'integer', 'min:1', 'max:14'],
            'compliance.rules' => ['sometimes', 'array'],
            'compliance.rules.*' => ['boolean'],
            // Generic per-group overrides. Werte sind immer Strings (Form-Input);
            // leere Strings werden weiter unten verworfen → Fallback auf config().
            'settings' => ['sometimes', 'array'],
            'settings.pagination' => ['sometimes', 'array'],
            'settings.pagination.*' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'settings.invoicing' => ['sometimes', 'array'],
            'settings.invoicing.default_tax_rate' => ['nullable', 'string', 'max:8'],
            'settings.invoicing.default_currency' => ['nullable', 'string', 'size:3'],
            'settings.invoicing.time_unit' => ['nullable', 'string', 'max:8'],
            'settings.uploads' => ['sometimes', 'array'],
            'settings.uploads.*' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'settings.validation' => ['sometimes', 'array'],
            'settings.validation.*' => ['sometimes', 'array'],
            'settings.validation.*.*' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'settings.notifications' => ['sometimes', 'array'],
            'settings.notifications.push' => ['sometimes', 'array'],
            'settings.notifications.push.body_truncate' => ['nullable', 'integer', 'min:20', 'max:500'],
            'settings.ui' => ['sometimes', 'array'],
            'settings.ui.*' => ['sometimes', 'array'],
            'settings.ui.*.*' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        // Eingehende Override-Gruppen rekursiv mit Bestand mergen; leere Werte
        // werden entfernt, damit der systemweite config()-Default greift.
        /** @var array<string,mixed> $existingSettings */
        $existingSettings = (array) ($organization->settings ?? []);
        $mergedSettings = $existingSettings;

        if (array_key_exists('settings', $data) && is_array($data['settings'])) {
            foreach ($data['settings'] as $group => $values) {
                if (! is_array($values)) {
                    continue;
                }
                $clean = $this->stripEmpty($values);
                $existingGroup = isset($mergedSettings[$group]) && is_array($mergedSettings[$group])
                    ? $mergedSettings[$group]
                    : [];
                $next = array_replace_recursive($existingGroup, $clean);
                $next = $this->stripEmpty($next);
                if ($next === []) {
                    unset($mergedSettings[$group]);
                } else {
                    $mergedSettings[$group] = $next;
                }
            }
            unset($data['settings']);
        }

        // Compliance ggf. in settings einbetten.
        if (array_key_exists('compliance', $data)) {
            /** @var array<string,mixed> $existingCompliance */
            $existingCompliance = isset($mergedSettings['compliance']) && is_array($mergedSettings['compliance'])
                ? $mergedSettings['compliance']
                : [];
            $mergedSettings['compliance'] = array_replace(
                $existingCompliance,
                $data['compliance'],
            );
            // Boolean-Konvertierung für rules
            if (isset($mergedSettings['compliance']['rules']) && is_array($mergedSettings['compliance']['rules'])) {
                $mergedSettings['compliance']['rules'] = array_map(
                    static fn ($v) => filter_var($v, FILTER_VALIDATE_BOOL),
                    $mergedSettings['compliance']['rules'],
                );
            }
            unset($data['compliance']);
        }

        $data['settings'] = $mergedSettings;

        $organization->update($data);

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organisation wurde aktualisiert.'));
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        Gate::authorize('delete', $organization);

        // Verhindere, dass der Admin sich selbst aussperrt, indem er die
        // letzte vorhandene Organisation löscht: ohne Org gibt es keinen
        // Tenant-Kontext mehr (und der Guardrail in BelongsToOrganization
        // würde jede weitere Schreibaktion blockieren).
        $remaining = Organization::query()->where('id', '!=', $organization->id)->count();
        if ($remaining === 0) {
            return redirect()->route('admin.organizations.index')
                ->with('error', __('Die letzte Organisation kann nicht gelöscht werden. Legen Sie zuerst eine neue Organisation an.'));
        }

        $deletedId = (int) $organization->id;
        $organization->delete();

        // Session-Override (Org-Switcher) bereinigen, falls der Admin
        // gerade die gelöschte Org aktiv hatte.
        $session = request()->session();
        if ((int) $session->get(\App\Http\Controllers\OrganizationSwitchController::SESSION_KEY) === $deletedId) {
            $session->forget(\App\Http\Controllers\OrganizationSwitchController::SESSION_KEY);
        }

        // Falls der ausführende Admin selbst dieser Org zugeordnet war
        // (FK nullOnDelete hat seine organization_id geleert), weisen wir
        // ihn der ersten verfügbaren Org zu, damit er weiterarbeiten kann.
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user instanceof \App\Models\User) {
            $user->refresh();
            if (empty($user->organization_id)) {
                $fallback = Organization::query()->orderBy('id')->first();
                if ($fallback instanceof Organization) {
                    $user->forceFill(['organization_id' => $fallback->id])->save();
                }
            }
        }

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organisation wurde gelöscht.'));
    }

    /**
     * Entfernt leere Strings / nulls (rekursiv) aus einem Settings-Array.
     * Verbleibende leere Sub-Arrays werden ebenfalls entfernt, damit
     * config()-Fallbacks greifen.
     *
     * @param  array<string,mixed>  $values
     * @return array<string,mixed>
     */
    private function stripEmpty(array $values): array
    {
        $out = [];
        foreach ($values as $k => $v) {
            if (is_array($v)) {
                $cleaned = $this->stripEmpty($v);
                if ($cleaned !== []) {
                    $out[$k] = $cleaned;
                }

                continue;
            }
            if ($v === null || $v === '') {
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }
}
