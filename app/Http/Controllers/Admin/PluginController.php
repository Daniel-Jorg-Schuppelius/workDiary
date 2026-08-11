<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, PluginSetting, PluginState, User};
use App\Plugins\{PluginCompatibility, PluginManager};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Admin-UI zum Verwalten von Plugins pro Organisation:
 *  - Übersicht aller registrierten Plugins (auch deaktivierten)
 *  - Aktivieren/Deaktivieren
 *  - Settings (API-Keys etc.) gemäß plugin-eigener {@see Plugin::settingsSchema()}
 *
 * API-Keys werden in der plugin_settings-Tabelle verschlüsselt abgelegt.
 */
class PluginController extends Controller {
    public function index(Request $request, PluginManager $manager): View {
        $admin = $this->ensureAdmin($request);

        $rows = PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $admin->organization_id)
            ->get()
            ->keyBy('plugin_id');

        $compatibility = [];
        $operational = [];
        foreach ($manager->all() as $plugin) {
            $compatibility[$plugin->id()] = PluginCompatibility::for($plugin);
            // Anzeige-Wahrheit (W0d): „aktiv" heißt erst dann grün, wenn das
            // Plugin im Org-Kontext auch funktionsfähig ist (Key vorhanden etc.).
            // Exception-isoliert — ein werfendes isEnabled() darf die Seite nicht reißen.
            try {
                $operational[$plugin->id()] = $plugin->isEnabled();
            } catch (Throwable) {
                $operational[$plugin->id()] = false;
            }
        }

        // Offene Fehler je Plugin (W4a): Badge in der Übersicht verlinkt auf die
        // gefilterte Inbox — eigener Org-Scope + globale Fehler, ein Query.
        $errorCounts = \App\Models\PluginError::query()
            ->whereNull('acknowledged_at')
            ->where(function ($q) use ($admin): void {
                $q->whereNull('organization_id')->orWhere('organization_id', (int) $admin->organization_id);
            })
            ->selectRaw('plugin_id, count(*) as open_count')
            ->groupBy('plugin_id')
            ->pluck('open_count', 'plugin_id');

        return view('admin.plugins.index', [
            'plugins' => $manager->all(),
            'settings' => $rows,
            'states' => PluginState::mapForOrganization((int) $admin->organization_id),
            'compatibility' => $compatibility,
            'operational' => $operational,
            'errorCounts' => $errorCounts,
            'filters' => [
                'status' => (string) $request->string('status'),
                'q' => (string) $request->string('q'),
            ],
        ]);
    }

    public function edit(Request $request, string $plugin, PluginManager $manager): View {
        $admin = $this->ensureAdmin($request);

        $instance = $manager->get($plugin);
        abort_unless($instance !== null, 404);

        $row = PluginSetting::forOrganization((int) $admin->organization_id, $plugin);
        $state = PluginState::forContext($plugin, $instance->isPerOrganization() ? (int) $admin->organization_id : null);

        return view('admin.plugins._form_dialog', [
            'plugin' => $instance,
            'setting' => $row,
            // Normalisiert (W5b): akzeptiert Array-Literale UND SettingsField-VOs.
            'schema' => array_map(
                static fn(array|\App\Plugins\Contracts\SettingsField $f): array => \App\Plugins\Contracts\SettingsField::fromArray($f)->toArray(),
                $instance->settingsSchema(),
            ),
            'state' => $state,
        ]);
    }

    public function update(Request $request, string $plugin, PluginManager $manager): RedirectResponse|JsonResponse {
        $admin = $this->ensureAdmin($request);

        $instance = $manager->get($plugin);
        abort_unless($instance !== null, 404);

        // Typisiertes Schema (W5b): Regeln kommen aus FieldType::rules(),
        // `required` gilt für ALLE Typen (vorher nur password, Befund F2).
        $schema = array_map(
            static fn(array|\App\Plugins\Contracts\SettingsField $f): \App\Plugins\Contracts\SettingsField => \App\Plugins\Contracts\SettingsField::fromArray($f),
            $instance->settingsSchema(),
        );
        $rules = ['enabled' => ['sometimes', 'boolean']];
        foreach ($schema as $field) {
            $key = 'settings.' . $field->key;
            // Secret-Felder (Flag `secret`, Default bei type=password) behandeln
            // "" als "Wert beibehalten" — required wird im Controller-Code unten
            // geprüft (nur wenn noch gar kein Key gesetzt war). (W1d)
            $rules[$key] = match (true) {
                $field->type === \App\Plugins\Contracts\FieldType::Boolean => ['sometimes', 'boolean'],
                $field->type === \App\Plugins\Contracts\FieldType::Select => [$field->required ? 'required' : 'nullable', 'string', 'in:' . implode(',', array_keys($field->options))],
                $field->isSecret() => ['nullable', ...$field->type->rules()],
                default => [$field->required ? 'required' : 'nullable', ...$field->type->rules()],
            };
        }

        $data = $request->validate($rules);

        $row = PluginSetting::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'organization_id' => $admin->organization_id,
                'plugin_id' => $plugin,
            ]);

        // Required password-Felder müssen beim ERSTEN Speichern (kein vorhandener
        // Wert) gesetzt werden — danach erlaubt die UX "leer = nicht ändern".
        $existing = $row->settings ?? [];
        $missing = [];
        foreach ($schema as $field) {
            if (! $field->isSecret() || ! $field->required) {
                continue;
            }
            $input = (string) $request->input('settings.' . $field->key, '');
            $existingVal = (string) ($existing[$field->key] ?? '');
            if ($input === '' && $existingVal === '') {
                $missing['settings.' . $field->key] = __('Dieses Feld ist erforderlich.');
            }
        }
        if ($missing !== []) {
            // Liefert im Dialog-Flow (fetch, Accept: application/json) 422 mit
            // errors-Bag, sonst Redirect+Flash — beide Wege zeigen die Meldung (W0h).
            throw ValidationException::withMessages($missing);
        }

        $wasEnabled = (bool) $row->enabled;
        $row->enabled = $request->boolean('enabled');

        // Aktivierung nur, wenn das Plugin zur laufenden Kernversion passt.
        if ($row->enabled && ! $wasEnabled) {
            $compat = PluginCompatibility::for($instance);
            if (! $compat->compatible) {
                $message = __('plugins.compatibility.activation_blocked', ['message' => $compat->message]);
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 409);
                }

                return redirect()->back()->withInput()->with('error', $message);
            }
        }

        $settings = $row->settings ?? [];
        foreach ($schema as $field) {
            $key = $field->key;
            $input = $request->input('settings.' . $key);

            if ($field->type === \App\Plugins\Contracts\FieldType::Boolean) {
                $settings[$key] = $request->boolean('settings.' . $key);

                continue;
            }
            if ($field->isSecret() && ($input === null || $input === '')) {
                // Leeres Feld + „Zurücksetzen" angehakt: gespeicherten Wert
                // ENTFERNEN → Config/ENV-Fallback greift wieder (z. B. Wechsel
                // von eigener App auf die Instanz-App). Ohne Haken bleibt der
                // Wert erhalten ("leer = unverändert").
                if ($request->boolean('settings_reset.' . $key)) {
                    unset($settings[$key]);
                }

                continue;
            }
            if ($input === null || $input === '') {
                unset($settings[$key]);

                continue;
            }
            $settings[$key] = (string) $input;
        }

        // Plugin-eigene Validierung (über das Schema hinaus) vor dem Speichern.
        $pluginErrors = $instance->validateSettings($settings);
        if ($pluginErrors !== []) {
            $errors = [];
            foreach ($pluginErrors as $field => $message) {
                $errors['settings.' . $field] = $message;
            }

            throw ValidationException::withMessages($errors);
        }

        $row->settings = $settings;
        $row->save();

        // Lifecycle-Hooks: Settings gespeichert + (De-)Aktivierung in dieser Org.
        $orgId = (int) $admin->organization_id;
        $instance->onSettingsSaved($orgId, $settings);
        if ($row->enabled && ! $wasEnabled) {
            $instance->onActivate($orgId);
        } elseif (! $row->enabled && $wasEnabled) {
            $instance->onDeactivate($orgId);
        }
        if ((bool) $row->enabled !== $wasEnabled) {
            $this->auditIntegrationChanged($admin, $row, $wasEnabled);
        }
        // Audit auch für Settings-Änderungen (W1d, B5): der Austausch eines
        // API-Keys ist sicherheitsrelevanter als der Toggle. Nur Feldnamen —
        // nie Werte — landen im Audit-Log.
        $changedKeys = [];
        foreach ($schema as $field) {
            $k = $field->key;
            if (($existing[$k] ?? null) !== ($settings[$k] ?? null)) {
                $changedKeys[] = $k;
            }
        }
        if ($changedKeys !== []) {
            AuditLog::query()->create([
                'organization_id' => (int) $admin->organization_id,
                'user_id' => $admin->id,
                'event' => 'integration.settings_changed',
                'auditable_type' => PluginSetting::class,
                'auditable_id' => (int) $row->id,
                'changes' => [
                    'integration' => (string) $row->plugin_id,
                    'fields' => $changedKeys,
                ],
            ]);
        }
        if (! $row->enabled && $wasEnabled) {
            // Deaktivierung invalidiert den Health-Zustand (W0c): ein stehen
            // gebliebener Status würde in der Übersicht als aktuell wirken.
            $this->clearHealthState($plugin, $instance->isPerOrganization() ? $orgId : null);
        }

        $manager->flushRuntimeCaches();

        $request->session()->flash('success', __('Plugin-Einstellungen gespeichert.'));
        if ($request->expectsJson()) {
            // Dialog-Flow: fetch folgt Redirects transparent und würde das
            // Flash dabei verbrauchen (W0h) — Redirect-Ziel explizit übergeben.
            return response()->json(['redirect' => route('admin.plugins.index')]);
        }

        return redirect()->route('admin.plugins.index');
    }

    /**
     * Setzt den persistierten Health-Zustand eines Plugins im gegebenen Kontext
     * zurück (nur vorhandene Zeilen; legt nie neue an).
     */
    private function clearHealthState(string $plugin, ?int $organizationId): void {
        PluginState::query()
            ->where('plugin_id', $plugin)
            ->when(
                $organizationId === null,
                fn($q) => $q->whereNull('organization_id'),
                fn($q) => $q->where('organization_id', $organizationId),
            )
            ->update([
                'last_health_status' => null,
                'last_health_message' => null,
                'last_health_check_at' => null,
            ]);
    }

    private function ensureAdmin(Request $request): User {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    /**
     * Aktiviert oder deaktiviert ein Plugin für die Organisation des Admins
     * (PluginSetting.enabled). Globaler Auto-Disable (plugin_states.disabled_reason)
     * bleibt davon unberührt — dazu dient {@see PluginErrorController::reset()}.
     */
    public function toggle(Request $request, string $plugin, PluginManager $manager): RedirectResponse {
        $admin = $this->ensureAdmin($request);
        $instance = $manager->get($plugin);
        abort_unless($instance !== null, 404);

        $row = PluginSetting::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'organization_id' => $admin->organization_id,
                'plugin_id' => $plugin,
            ]);

        $willEnable = ! (bool) $row->enabled;
        if ($willEnable) {
            // Inkompatible Plugins lassen sich nicht aktivieren (Feature 022):
            // die Kernversion liegt außerhalb des deklarierten Bereichs.
            $compat = PluginCompatibility::for($instance);
            if (! $compat->compatible) {
                return back()->with('error', __('plugins.compatibility.activation_blocked', [
                    'message' => $compat->message,
                ]));
            }
        }

        $wasEnabled = (bool) $row->enabled;
        $row->enabled = $willEnable;
        $row->save();

        // Lifecycle-Hook für (De-)Aktivierung in dieser Organisation.
        $orgId = (int) $admin->organization_id;
        $row->enabled ? $instance->onActivate($orgId) : $instance->onDeactivate($orgId);
        $this->auditIntegrationChanged($admin, $row, $wasEnabled);
        if (! $row->enabled) {
            // Deaktivierung invalidiert den Health-Zustand (W0c).
            $this->clearHealthState($plugin, $instance->isPerOrganization() ? $orgId : null);
        }
        $manager->flushRuntimeCaches();

        return back()->with('success', $row->enabled
            ? __('Plugin aktiviert.')
            : __('Plugin deaktiviert.'));
    }

    /**
     * Audit-Event `integration.changed` (Datenschutzseite-Konzept §4, MVP-327):
     * eine externe Integration wurde in dieser Organisation aktiviert oder
     * deaktiviert. Schreibt über den Eloquent-Schreibweg der Hash-Kette
     * ({@see AuditLog}), nie roh.
     */
    private function auditIntegrationChanged(User $admin, PluginSetting $row, bool $wasEnabled): void {
        AuditLog::query()->create([
            'organization_id' => (int) $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'integration.changed',
            'auditable_type' => PluginSetting::class,
            'auditable_id' => (int) $row->id,
            'changes' => [
                'integration' => (string) $row->plugin_id,
                'from' => $wasEnabled ? 'enabled' : 'disabled',
                'to' => $row->enabled ? 'enabled' : 'disabled',
            ],
        ]);
    }

    /**
     * Triggert einen sofortigen Healthcheck über die zentrale Pipeline
     * ({@see \App\Plugins\PluginHealthService}, W3a) und gibt das Ergebnis als
     * JSON zurück (für UI-Buttons / fetch) — inkl. failure_count/auto_disabled
     * für das Zeilen-Update (W4b). Manuelle Checks zählen nie für den
     * Auto-Disable (Phase `manual`, E-1).
     */
    public function healthCheck(Request $request, string $plugin, PluginManager $manager, \App\Plugins\PluginHealthService $service): JsonResponse|RedirectResponse {
        $admin = $this->ensureAdmin($request);
        $instance = $manager->get($plugin);
        abort_unless($instance !== null, 404);

        // Per-Org-Plugins: Zustand/Fehler der Org des Admins zuordnen (der
        // healthCheck() prüft denselben gebundenen Org-Kontext); globale Plugins → null.
        $orgId = $instance->isPerOrganization() ? (int) $admin->organization_id : null;

        // Deaktivierte Plugins werden nicht geprüft (W0e) — wie im Scheduler:
        // ein Check ohne Konfiguration erzeugte sonst nur Pseudo-Fehler.
        $settingRow = PluginSetting::forOrganization((int) $admin->organization_id, $plugin);
        $enabled = (bool) $settingRow->enabled || (bool) config('plugins.' . $plugin . '.enabled', false);
        if (! $enabled) {
            $message = __('Plugin ist deaktiviert — Healthcheck nicht ausgeführt.');
            if ($request->expectsJson()) {
                return response()->json(['status' => 'disabled', 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $result = $service->check($instance, $orgId, manual: true);
        $health = $result['health'];
        $state = $result['state'];

        if (! $request->expectsJson()) {
            return $this->healthCheckRedirect($health->status, $health->message);
        }

        return response()->json($health->toArray() + [
            'checked_at' => $state->last_health_check_at?->toIso8601String(),
            'failure_count' => (int) $state->failure_count,
            'auto_disabled' => $result['auto_disabled'],
            'disabled_reason' => $state->disabled_reason,
        ]);
    }

    /**
     * Löst ein ausstehendes Plugin-Schema-Upgrade aus (Review 2026-08, W6):
     * der frühere „→ neue Version"-Badge war eine Sackgasse für Admins ohne
     * Shell. Downgrades verweigert der SchemaManager.
     */
    public function upgrade(Request $request, string $plugin, PluginManager $manager, \App\Plugins\PluginSchemaManager $schema): RedirectResponse {
        $this->ensureAdmin($request);
        $instance = $manager->get($plugin);
        abort_unless($instance !== null, 404);

        if (! $schema->needsUpgrade($instance)) {
            return back()->with('info', __('Kein Schema-Upgrade ausstehend.'));
        }

        try {
            $schema->upgrade($instance);
        } catch (Throwable $e) {
            return back()->with('error', __('Schema-Upgrade fehlgeschlagen: :message', ['message' => $e->getMessage()]));
        }

        return back()->with('success', __('Schema-Upgrade ausgeführt (:version).', ['version' => $instance->schemaVersion()]));
    }

    /** Ohne JS (A16): Ergebnis als Flash statt roher JSON-Seite. */
    private function healthCheckRedirect(string $status, string $message): RedirectResponse {
        $label = match ($status) {
            \App\Plugins\PluginHealth::STATUS_OK => __('Healthcheck: ok'),
            \App\Plugins\PluginHealth::STATUS_DEGRADED => __('Healthcheck: eingeschränkt'),
            default => __('Healthcheck: fehlerhaft'),
        };
        $text = $label . ($message !== '' ? ' — ' . $message : '');

        return back()->with($status === \App\Plugins\PluginHealth::STATUS_OK ? 'success' : 'error', $text);
    }
}
