<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginErrorController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{PluginError, PluginState, User};
use App\Plugins\{PluginErrorRecorder, PluginManager};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Admin-Inbox für Plugin-Fehler (Boot / Runtime / Healthcheck).
 *
 * Routen:
 *  - GET  admin/plugin-errors                         → index (Filterbar)
 *  - GET  admin/plugin-errors/{error}                 → show (Stacktrace)
 *  - POST admin/plugin-errors/{error}/acknowledge     → einzelnen Fehler markieren
 *  - POST admin/plugins/{plugin}/reset-errors         → failure_count + disabled_reason zurücksetzen
 */
class PluginErrorController extends Controller {
    public function index(Request $request, PluginManager $manager): View {
        $this->ensureAdmin($request);

        $query = PluginError::query()->orderByDesc('occurred_at');

        $filters = [
            'plugin' => (string) $request->string('plugin'),
            'phase' => (string) $request->string('phase'),
            'status' => (string) $request->string('status'), // open|acknowledged|all
        ];

        if ($filters['plugin'] !== '') {
            $query->where('plugin_id', $filters['plugin']);
        }
        if ($filters['phase'] !== '') {
            $query->where('phase', $filters['phase']);
        }
        if ($filters['status'] === '' || $filters['status'] === 'open') {
            $query->whereNull('acknowledged_at');
        } elseif ($filters['status'] === 'acknowledged') {
            $query->whereNotNull('acknowledged_at');
        }

        return view('admin.plugin-errors.index', [
            'errors' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'plugins' => $manager->all(),
            'states' => PluginState::all()->keyBy('plugin_id'),
        ]);
    }

    public function show(Request $request, PluginError $pluginError): View {
        $this->ensureAdmin($request);

        return view('admin.plugin-errors.show', [
            'error' => $pluginError->load('acknowledger'),
        ]);
    }

    public function acknowledge(Request $request, PluginError $pluginError): RedirectResponse {
        $admin = $this->ensureAdmin($request);

        if (! $pluginError->isAcknowledged()) {
            $pluginError->acknowledged_at = now();
            $pluginError->acknowledged_by = $admin->id;
            $pluginError->save();
        }

        return back()->with('success', __('Fehler als gesehen markiert.'));
    }

    public function reset(Request $request, string $plugin, PluginErrorRecorder $recorder): RedirectResponse {
        $this->ensureAdmin($request);

        $recorder->reset($plugin);

        return back()->with('success', __('Failure-Counter zurückgesetzt — Plugin wieder aktiv.'));
    }

    private function ensureAdmin(Request $request): User {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);

        return $user;
    }
}
