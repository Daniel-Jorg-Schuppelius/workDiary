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
use App\Models\{PluginSetting, PluginState, User};
use App\Plugins\{PluginErrorRecorder, PluginManager};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
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

        return view('admin.plugins.index', [
            'plugins' => $manager->all(),
            'settings' => $rows,
            'states' => PluginState::all()->keyBy('plugin_id'),
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
        $state = PluginState::query()->where('plugin_id', $plugin)->first();

        return view('admin.plugins._form_dialog', [
            'plugin' => $instance,
            'setting' => $row,
            'schema' => $instance->settingsSchema(),
            'state' => $state,
        ]);
    }

    public function update(Request $request, string $plugin, PluginManager $manager): RedirectResponse {
        $admin = $this->ensureAdmin($request);

        $instance = $manager->get($plugin);
        abort_unless($instance !== null, 404);

        $schema = $instance->settingsSchema();
        $rules = ['enabled' => ['sometimes', 'boolean']];
        foreach ($schema as $field) {
            $key = 'settings.' . $field['key'];
            $required = (bool) ($field['required'] ?? false);
            // Password-Felder behandeln "" als "Wert beibehalten" — required wird im
            // Controller-Code unten geprüft (nur wenn noch gar kein Key gesetzt war).
            $isPassword = $field['type'] === 'password';
            $rules[$key] = match ($field['type']) {
                'boolean' => ['sometimes', 'boolean'],
                'select' => array_filter([$required ? 'required' : 'nullable', 'string', isset($field['options']) ? 'in:' . implode(',', array_keys($field['options'])) : null]),
                'password' => ['nullable', 'string', 'max:1000'],
                default => [$required ? 'required' : 'nullable', 'string', 'max:1000'],
            };
            unset($isPassword);
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
            if ($field['type'] !== 'password' || empty($field['required'])) {
                continue;
            }
            $input = (string) $request->input('settings.' . $field['key'], '');
            $existingVal = (string) ($existing[$field['key']] ?? '');
            if ($input === '' && $existingVal === '') {
                $missing['settings.' . $field['key']] = __('Dieses Feld ist erforderlich.');
            }
        }
        if ($missing !== []) {
            return redirect()->back()->withErrors($missing)->withInput();
        }

        $row->enabled = $request->boolean('enabled');

        $settings = $row->settings ?? [];
        foreach ($schema as $field) {
            $key = $field['key'];
            $type = $field['type'];
            $input = $request->input('settings.' . $key);

            if ($type === 'boolean') {
                $settings[$key] = $request->boolean('settings.' . $key);

                continue;
            }
            if ($type === 'password' && ($input === null || $input === '')) {
                // Leere Eingabe = bestehenden Key NICHT überschreiben (UX: nicht jedes Mal neu eintippen).
                continue;
            }
            if ($input === null || $input === '') {
                unset($settings[$key]);

                continue;
            }
            $settings[$key] = (string) $input;
        }
        $row->settings = $settings;
        $row->save();

        return redirect()->route('admin.plugins.index')
            ->with('success', __('Plugin-Einstellungen gespeichert.'));
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
        abort_unless($manager->get($plugin) !== null, 404);

        $row = PluginSetting::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'organization_id' => $admin->organization_id,
                'plugin_id' => $plugin,
            ]);
        $row->enabled = ! (bool) $row->enabled;
        $row->save();

        return back()->with('success', $row->enabled
            ? __('Plugin aktiviert.')
            : __('Plugin deaktiviert.'));
    }

    /**
     * Triggert einen sofortigen Healthcheck und gibt das Ergebnis als JSON
     * zurück (für UI-Buttons / fetch). Persistiert das Ergebnis ebenso wie
     * der scheduled Command `plugin:healthcheck`.
     */
    public function healthCheck(Request $request, string $plugin, PluginManager $manager, PluginErrorRecorder $recorder): JsonResponse {
        $this->ensureAdmin($request);
        $instance = $manager->get($plugin);
        abort_unless($instance !== null, 404);

        $state = PluginState::findOrInit($plugin);
        $state->plugin_id = $plugin;
        $state->last_health_check_at = now();

        try {
            $health = $instance->healthCheck();
            $state->last_health_status = $health->status;
            $state->last_health_message = $health->message;
            $state->save();

            if ($health->isOk()) {
                $recorder->markHealthy($plugin);
            } elseif ($health->isFailing()) {
                $recorder->record($plugin, 'healthcheck', new \RuntimeException($health->message !== '' ? $health->message : 'failing healthcheck'));
            }

            return response()->json($health->toArray() + ['checked_at' => $state->last_health_check_at->toIso8601String()]);
        } catch (Throwable $e) {
            $state->last_health_status = \App\Plugins\PluginHealth::STATUS_FAILING;
            $state->last_health_message = $e->getMessage();
            $state->save();
            $recorder->record($plugin, 'healthcheck', $e);

            return response()->json([
                'status' => \App\Plugins\PluginHealth::STATUS_FAILING,
                'message' => $e->getMessage(),
                'checked_at' => $state->last_health_check_at->toIso8601String(),
            ], 200);
        }
    }
}
