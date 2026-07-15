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

use App\Models\{Organization, User};
use App\Services\OrganizationLifecycleService;
use App\Support\{Setting, SortableQuery};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrganizationController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Organization::class);

        // Recovery: ist der Admin (noch) keiner Org zugeordnet, aber es
        // existiert mindestens eine Organisation (etwa nach Löschen der
        // eigenen Org oder Bootstrap), weisen wir ihn der ersten zu.
        // Damit greifen org-gebundene Policies (manage-members, Branding,
        // …) wieder, ohne dass der Admin die Zuordnung manuell pflegen
        // muss. Cross-Org-Wechsel bleibt über den Org-Switcher möglich.
        $user = Auth::user();
        if ($user instanceof User && $user->isAdmin() && empty($user->organization_id)) {
            $first = Organization::query()->orderBy('id')->first();
            if ($first instanceof Organization) {
                $user->forceFill(['organization_id' => $first->id])->save();
            }
        }

        // TENANT-BYPASS: Plattform-Admin listet Organisationen über Mandanten
        // hinweg. Route ist hinter auth + access.manage abgesichert; ohne
        // diese Berechtigung erreicht der Aufruf den Controller nicht.
        $query = Organization::query()->withoutGlobalScopes()->withCount('users');

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'name' => 'name',
            'plan' => 'plan',
            'locale' => 'locale',
            'timezone' => 'timezone',
            'is_active' => 'is_active',
            'users' => 'users_count',
        ], 'name', 'asc');

        $organizations = $query->paginate((int) Setting::get('pagination.organizations', 25))->withQueryString();

        return view('admin.organizations.index', compact('organizations', 'sort', 'dir'));
    }

    public function create(): View {
        Gate::authorize('create', Organization::class);

        return view('admin.organizations._form_dialog', ['organization' => new Organization]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Organization::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', 'in:' . implode(',', Organization::$plans)],
            'locale' => ['required', \Illuminate\Validation\Rule::in(\App\Support\Locales::enabledCodes())],
            'timezone' => ['required', 'timezone'],
            'is_active' => ['boolean'],
            // Funktionsumfang-Preset (Feature 081, MVP-373): optional beim
            // Anlegen; ohne Auswahl bleibt der volle Lizenzumfang aktiv.
            'scope_preset' => ['nullable', 'string', \Illuminate\Validation\Rule::in(array_keys((array) config('plans.presets', [])))],
        ]);

        $scopePreset = $data['scope_preset'] ?? null;
        unset($data['scope_preset']);

        $data['is_active'] = $request->boolean('is_active', true);

        $organization = Organization::create($data);

        if (is_string($scopePreset) && $scopePreset !== '' && $request->user() instanceof User) {
            app(\App\Services\Licensing\ModuleScopeService::class)
                ->applyPreset($organization, $scopePreset, $request->user());
        }

        // Wenn der ausführende Admin (noch) keiner Organisation zugeordnet
        // ist – typischerweise nach Lösch-/Bootstrap-Szenarien – weisen wir
        // ihn der frisch angelegten Org zu, damit Org-bezogene Policies
        // (z. B. manage-members) sofort greifen.
        $user = Auth::user();
        if ($user instanceof User && empty($user->organization_id)) {
            $user->forceFill(['organization_id' => $organization->id])->save();
        }

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organisation wurde erstellt.'));
    }

    public function edit(Organization $organization): View {
        Gate::authorize('update', $organization);

        return view('admin.organizations._form_dialog', compact('organization'));
    }

    public function update(Request $request, Organization $organization): RedirectResponse {
        Gate::authorize('update', $organization);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', 'in:' . implode(',', Organization::$plans)],
            'locale' => ['required', \Illuminate\Validation\Rule::in(\App\Support\Locales::enabledCodes())],
            'timezone' => ['required', 'timezone'],
            'is_active' => ['boolean'],
            'compliance' => ['sometimes', 'array'],
            'compliance.mode' => ['sometimes', 'in:' . implode(',', Organization::$complianceModes)],
            'compliance.max_hours_day' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'compliance.min_rest_hours' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'compliance.max_hours_week' => ['sometimes', 'integer', 'min:1', 'max:168'],
            'compliance.max_consecutive_days' => ['sometimes', 'integer', 'min:1', 'max:14'],
            'compliance.rules' => ['sometimes', 'array'],
            'compliance.rules.*' => ['boolean'],
            // Generic per-group overrides. Werte sind immer Strings (Form-Input);
            // leere Strings werden weiter unten verworfen → Fallback auf config().
            // Die Einzelregeln kommen aus der Settings-Registry (067-P3b):
            // jede org-scoped registrierte Einstellung ist hier setzbar.
            'settings' => ['sometimes', 'array'],
            // Fakturierungsweg-Default (Feature 045): skalarer Top-Level-Key —
            // bewusst nicht in der Registry (unterläuft deren group.rest-
            // Mechanik) und im Schreibpfad separat Gate-gebunden.
            'settings.billing_mode' => ['nullable', 'in:' . implode(',', \App\Enums\Finance\BillingMode::values())],
            // Wildcard-Netze für UNregistrierte Unterkeys dieser Gruppen
            // (historisches Formularverhalten, z. B. settings.pagination.<neu>).
            'settings.pagination.*' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'settings.uploads.*' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'settings.validation' => ['sometimes', 'array'],
            'settings.validation.*' => ['sometimes', 'array'],
            'settings.validation.*.*' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'settings.ui.*' => ['sometimes', 'array'],
            'settings.ui.*.*' => ['nullable', 'integer', 'min:1', 'max:1000'],
            ...app(\App\Settings\SettingsRegistry::class)->formRulesForScope(\App\Settings\SettingScope::Organization),
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        // Eingehende Override-Gruppen rekursiv mit Bestand mergen; leere Werte
        // werden entfernt, damit der systemweite config()-Default greift.
        /** @var array<string,mixed> $existingSettings */
        $existingSettings = (array) ($organization->settings ?? []);
        $mergedSettings = $existingSettings;

        // Fakturierungsweg-Default (Feature 045, additiv): skalarer Top-Level-
        // Key in settings — die Gruppen-Schleife unten überspringt Skalare
        // bewusst, daher hier explizit behandeln. Nur mit finance.config.
        if (
            array_key_exists('settings', $data)
            && is_array($data['settings'])
            && array_key_exists('billing_mode', $data['settings'])
        ) {
            if (Gate::allows(\App\Enums\User\Permission::FinanceConfig->value)) {
                $mode = $data['settings']['billing_mode'];
                if ($mode === null || $mode === '') {
                    unset($mergedSettings['billing_mode']);
                } else {
                    $mergedSettings['billing_mode'] = (string) $mode;
                }
            }
            unset($data['settings']['billing_mode']);
        }

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
                    static fn($v) => filter_var($v, FILTER_VALIDATE_BOOL),
                    $mergedSettings['compliance']['rules'],
                );
            }
            unset($data['compliance']);
        }

        $data['settings'] = $mergedSettings;
        // Checkbox: unchecked wird nicht gesendet → explizit aus dem Request lesen.
        $data['two_factor_required'] = $request->boolean('two_factor_required');

        // Wartungsmodus-Umschaltung nachvollziehbar machen (Rang 65).
        $maintenanceBefore = (string) data_get($existingSettings, 'maintenance.enabled', '0') === '1';
        $maintenanceAfter = (string) data_get($mergedSettings, 'maintenance.enabled', '0') === '1';

        $organization->update($data);

        if ($maintenanceBefore !== $maintenanceAfter) {
            $organization->audit('organization.maintenance_toggled', [
                'enabled' => $maintenanceAfter,
                'until' => data_get($mergedSettings, 'maintenance.until'),
            ]);
        }

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organisation wurde aktualisiert.'));
    }

    public function destroy(Organization $organization): RedirectResponse {
        Gate::authorize('delete', $organization);

        // Hard-Delete einer Organisation ist NICHT mehr direkt möglich:
        // Wir leiten auf die explizite Deaktivierung um. Endgültiges
        // Löschen muss bewusst über die `purge`-Aktion erfolgen (mit
        // Slug-Bestätigung und Cooldown nach Deaktivierung).
        return redirect()
            ->route('admin.organizations.index')
            ->with('error', __('Direktes Löschen ist nicht mehr möglich. Bitte zuerst deaktivieren, optional Daten exportieren und anschließend endgültig löschen.'));
    }

    /**
     * Setzt die Organisation auf inaktiv (reversibel). Sie verschwindet
     * sofort aus dem Header-Switcher und kann nicht mehr als aktiver
     * Mandanten-Kontext gewählt werden.
     */
    public function deactivate(
        Request $request,
        Organization $organization,
        OrganizationLifecycleService $lifecycle,
    ): RedirectResponse {
        Gate::authorize('deactivate', $organization);

        $remaining = Organization::query()
            ->where('id', '!=', $organization->id)
            ->where('is_active', true)
            ->count();
        if ($remaining === 0) {
            return redirect()->route('admin.organizations.index')
                ->with('error', __('Die letzte aktive Organisation kann nicht deaktiviert werden.'));
        }

        $actor = Auth::user();
        $lifecycle->deactivate($organization, $actor instanceof User ? $actor : null);

        // Wenn der aktive Switcher-Kontext gerade diese Org war,
        // räumen wir die Session auf, damit die Middleware auf eine
        // aktive Org zurückfällt.
        $session = $request->session();
        if ((int) $session->get(OrganizationSwitchController::SESSION_KEY) === (int) $organization->id) {
            $session->forget(OrganizationSwitchController::SESSION_KEY);
        }

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organisation wurde deaktiviert.'));
    }

    public function reactivate(
        Organization $organization,
        OrganizationLifecycleService $lifecycle,
    ): RedirectResponse {
        Gate::authorize('reactivate', $organization);

        $actor = Auth::user();
        $lifecycle->reactivate($organization, $actor instanceof User ? $actor : null);

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organisation wurde reaktiviert.'));
    }

    /**
     * Erzeugt einen vollständigen ZIP-Export aller mandantengebundenen
     * Datensätze und Dateien (DSGVO Art. 20). Liefert die Datei direkt
     * als Download zurück.
     */
    public function export(
        Organization $organization,
        OrganizationLifecycleService $lifecycle,
    ): BinaryFileResponse {
        Gate::authorize('export', $organization);

        $actor = Auth::user();
        $relPath = $lifecycle->export($organization, $actor instanceof User ? $actor : null);
        $absPath = Storage::disk('local')->path($relPath);

        return response()->download(
            $absPath,
            basename($relPath),
            ['Content-Type' => 'application/zip'],
        );
    }

    /**
     * Endgültiges, unwiderrufliches Löschen einer Organisation inkl.
     * aller Datensätze und Dateien. Erfordert:
     *   - Organisation ist deaktiviert
     *   - Cooldown nach Deaktivierung ist abgelaufen
     *   - Slug der Organisation wird zur Bestätigung mitgesendet
     */
    public function purge(
        Request $request,
        Organization $organization,
        OrganizationLifecycleService $lifecycle,
    ): RedirectResponse {
        Gate::authorize('purge', $organization);

        $data = $request->validate([
            'confirm_slug' => ['required', 'string'],
        ]);

        if (trim((string) $data['confirm_slug']) !== (string) $organization->slug) {
            return redirect()->route('admin.organizations.index')
                ->with('error', __('Bestätigung fehlgeschlagen: der eingegebene Slug stimmt nicht überein.'));
        }

        if ($organization->is_active) {
            return redirect()->route('admin.organizations.index')
                ->with('error', __('Aktive Organisationen können nicht endgültig gelöscht werden. Bitte zuerst deaktivieren.'));
        }

        if (! $lifecycle->isPurgeAllowed($organization)) {
            return redirect()->route('admin.organizations.index')
                ->with('error', __('Endgültiges Löschen ist erst :h Stunden nach Deaktivierung möglich.', [
                    'h' => $lifecycle->cooldownHours(),
                ]));
        }

        // Mindestens eine andere aktive Org muss verbleiben, damit der
        // ausführende Admin nicht ausgesperrt wird.
        $remaining = Organization::query()
            ->where('id', '!=', $organization->id)
            ->where('is_active', true)
            ->count();
        if ($remaining === 0) {
            return redirect()->route('admin.organizations.index')
                ->with('error', __('Es muss mindestens eine andere aktive Organisation verbleiben.'));
        }

        $deletedName = (string) $organization->name;
        $actor = Auth::user();
        $lifecycle->purge($organization, $actor instanceof User ? $actor : null);

        // Aus­führenden Admin auffangen, falls er der Org zugeordnet war.
        if ($actor instanceof User) {
            $actor->refresh();
            if (empty($actor->organization_id)) {
                $fallback = Organization::query()->where('is_active', true)->orderBy('id')->first();
                if ($fallback instanceof Organization) {
                    $actor->forceFill(['organization_id' => $fallback->id])->save();
                }
            }
        }

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organisation ":name" wurde endgültig gelöscht.', ['name' => $deletedName]));
    }

    /**
     * Entfernt leere Strings / nulls (rekursiv) aus einem Settings-Array.
     * Verbleibende leere Sub-Arrays werden ebenfalls entfernt, damit
     * config()-Fallbacks greifen.
     *
     * @param  array<string,mixed>  $values
     * @return array<string,mixed>
     */
    private function stripEmpty(array $values): array {
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
