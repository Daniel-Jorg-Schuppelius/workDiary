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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Admin-Inbox für Plugin-Fehler (Boot / Runtime / Healthcheck / Manuell).
 *
 * Mandantentrennung (Review 2026-08, W1a): sichtbar sind nur Fehler der
 * eigenen Organisation plus globale (organization_id = null, z. B. Boot).
 * Autorisierung über das Route-Gate `manage-plugins`.
 */
class PluginErrorController extends Controller {
    private const ALLOWED_SORTS = ['occurred_at', 'plugin_id', 'phase', 'exception_class', 'message', 'acknowledged_at'];

    public function index(Request $request, PluginManager $manager): View {
        $admin = $this->ensureAdmin($request);

        // Whitelist-Auflösung zentral (C21; Vollaudit 2026-07, N26) — bei
        // ungültigem Key fallen Key UND Richtung auf die Defaults zurück.
        [$sort, $dir] = \App\Support\SortableQuery::resolve($request, self::ALLOWED_SORTS, 'occurred_at');

        $query = PluginError::query()->orderBy($sort, $dir);
        $this->scopeToAdmin($query, $admin);

        $filters = [
            'plugin' => (string) $request->string('plugin'),
            'phase' => (string) $request->string('phase'),
            'status' => (string) $request->string('status'), // open|acknowledged|all
            'q' => (string) $request->string('q'),
            'from' => (string) $request->string('from'),
            'to' => (string) $request->string('to'),
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
        // Triage-Einstieg (W4c): Volltext über Meldung/Exception + Zeitraum.
        if ($filters['q'] !== '') {
            $query->where(function (Builder $q) use ($filters): void {
                $q->where('message', 'like', '%' . $filters['q'] . '%')
                    ->orWhere('exception_class', 'like', '%' . $filters['q'] . '%');
            });
        }
        if ($filters['from'] !== '') {
            $query->where('occurred_at', '>=', $filters['from'] . ' 00:00:00');
        }
        if ($filters['to'] !== '') {
            $query->where('occurred_at', '<=', $filters['to'] . ' 23:59:59');
        }

        // Bewusst NICHT `errors` — das würde Laravels ViewErrorBag in der View
        // überschreiben (Review 2026-08, A9).
        return view('admin.plugin-errors.index', [
            'pluginErrors' => $query->with('organization')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'plugins' => $manager->all(),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function show(Request $request, PluginError $pluginError): View {
        $admin = $this->ensureAdmin($request);
        $this->ensureVisible($pluginError, $admin);

        // Öffnen quittiert (E-5): der Admin hat den Fehler gesehen; „wieder
        // öffnen" macht es umkehrbar.
        if (! $pluginError->isAcknowledged()) {
            $pluginError->acknowledged_at = now();
            $pluginError->acknowledged_by = $admin->id;
            $pluginError->save();
        }

        // Vor/Zurück innerhalb des sichtbaren Bestands (W4c).
        $newer = PluginError::query()->tap(fn($q) => $this->scopeToAdmin($q, $admin))
            ->where('occurred_at', '>', $pluginError->occurred_at)->orderBy('occurred_at')->first();
        $older = PluginError::query()->tap(fn($q) => $this->scopeToAdmin($q, $admin))
            ->where('occurred_at', '<', $pluginError->occurred_at)->orderByDesc('occurred_at')->first();

        return view('admin.plugin-errors.show', [
            'error' => $pluginError->load(['acknowledger', 'organization']),
            'newer' => $newer,
            'older' => $older,
        ]);
    }

    public function acknowledge(Request $request, PluginError $pluginError): RedirectResponse {
        $admin = $this->ensureAdmin($request);
        $this->ensureVisible($pluginError, $admin);

        if (! $pluginError->isAcknowledged()) {
            $pluginError->acknowledged_at = now();
            $pluginError->acknowledged_by = $admin->id;
            $pluginError->save();
        }

        return back()->with('success', __('Fehler als gesehen markiert.'));
    }

    /** Quittieren rückgängig machen (W4c, E-5). */
    public function reopen(Request $request, PluginError $pluginError): RedirectResponse {
        $admin = $this->ensureAdmin($request);
        $this->ensureVisible($pluginError, $admin);

        $pluginError->acknowledged_at = null;
        $pluginError->acknowledged_by = null;
        $pluginError->save();

        return back()->with('success', __('Fehler wieder geöffnet.'));
    }

    /**
     * Bulk-Quittierung (W4c): entweder explizite IDs (Checkboxen) oder — mit
     * `all_filtered` — alle offenen Fehler des aktuellen Filters.
     */
    public function bulkAcknowledge(Request $request): RedirectResponse {
        $admin = $this->ensureAdmin($request);

        $query = PluginError::query()->whereNull('acknowledged_at');
        $this->scopeToAdmin($query, $admin);

        if ($request->boolean('all_filtered')) {
            if (($plugin = (string) $request->string('plugin')) !== '') {
                $query->where('plugin_id', $plugin);
            }
            if (($phase = (string) $request->string('phase')) !== '') {
                $query->where('phase', $phase);
            }
            if (($q = (string) $request->string('q')) !== '') {
                $query->where(function (Builder $sub) use ($q): void {
                    $sub->where('message', 'like', '%' . $q . '%')
                        ->orWhere('exception_class', 'like', '%' . $q . '%');
                });
            }
        } else {
            // Sqids aus der Liste (W3.3); Alt-Aufrufe mit roher ID bleiben lesbar.
            $ids = array_values(array_filter(array_map(
                static fn ($v): ?int => \App\Support\Sqid::decodeOrNumeric(\App\Models\PluginError::class, (string) $v),
                (array) $request->input('ids', []),
            )));
            if ($ids === []) {
                return back()->with('error', __('Keine Fehler ausgewählt.'));
            }
            $query->whereIn('id', $ids);
        }

        $count = $query->update(['acknowledged_at' => now(), 'acknowledged_by' => $admin->id]);

        return back()->with('success', __(':count Fehler als gesehen markiert.', ['count' => $count]));
    }

    public function reset(Request $request, string $plugin, PluginErrorRecorder $recorder): RedirectResponse {
        $admin = $this->ensureAdmin($request);

        // Nur den Zustand der eigenen Organisation zurücksetzen (W1b): der
        // globale Kill-Switch (organization_id = null, z. B. Boot-Fehler) gilt
        // instanzweit und fällt in die Betreiber-Hoheit → CLI `plugin:reset`.
        $recorder->reset($plugin, (int) $admin->organization_id);

        // Offene Fehler des Plugins mit-quittieren (Review 2026-08, A12): die
        // Inbox zeigte nach dem Reset sonst weiter dieselben roten Einträge.
        PluginError::query()
            ->where('plugin_id', $plugin)
            ->whereNull('acknowledged_at')
            ->where(function (Builder $q) use ($admin): void {
                $q->whereNull('organization_id')->orWhere('organization_id', (int) $admin->organization_id);
            })
            ->update(['acknowledged_at' => now(), 'acknowledged_by' => $admin->id]);

        $globalState = PluginState::findOrInit($plugin, null);
        if ($globalState->isAutoDisabled()) {
            return back()->with('warning', __('Org-Zustand zurückgesetzt — es besteht aber eine instanzweite Auto-Deaktivierung. Aufhebung durch den Betreiber: php artisan plugin:reset :plugin', ['plugin' => $plugin]));
        }

        // Kein „Plugin wieder aktiv" — die Aktivierung (PluginSetting.enabled)
        // bleibt von diesem Reset unberührt.
        return back()->with('success', __('Failure-Counter zurückgesetzt und offene Fehler quittiert — Auto-Disable aufgehoben.'));
    }

    /**
     * Sichtbarkeits-Scope der Inbox: eigene Organisation + globale Fehler.
     *
     * @param  Builder<PluginError>  $query
     */
    private function scopeToAdmin(Builder $query, User $admin): void {
        $query->where(function (Builder $q) use ($admin): void {
            $q->whereNull('organization_id')->orWhere('organization_id', (int) $admin->organization_id);
        });
    }

    private function ensureVisible(PluginError $error, User $admin): void {
        abort_unless(
            $error->organization_id === null || (int) $error->organization_id === (int) $admin->organization_id,
            404,
        );
    }

    private function ensureAdmin(Request $request): User {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }
}
