<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider};
use App\Http\Controllers\Controller;
use App\Http\Requests\CloudIntake\SaveCloudRouteRequest;
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem, CloudDocumentRoute};
use App\Plugins\Contracts\{DocumentIntakeSource, IntakeSourceConnector};
use App\Plugins\PluginManager;
use App\Services\CloudIntake\{CloudRouteTargets, CloudRouteTester, RoutePatternValidator};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Zentrale Seite „Cloud-Dokumenteingang" (Feature 080, MVP-358):
 * Verbindungen je Provider mit Konto/Ordner/Status, Ordnerregeln (Modal),
 * Erstlaufvorschau (ohne Übernahme), Importprotokoll und Trennung.
 * Trennen löscht weder Nachweise noch importierte Dokumente.
 */
class CloudIntakeAdminController extends Controller {
    /** Vorschau je Verbindung in der Sitzung; der Start ist nur kurz danach bestätigbar (MVP-1613). */
    private const string PREVIEW_SESSION = 'cloud_intake.previews';

    private const int PREVIEW_VALID_MINUTES = 60;

    public function index(Request $request): View {
        Gate::authorize('viewAny', CloudDocumentConnection::class);

        $connections = CloudDocumentConnection::query()
            ->with(['routes' => fn ($q) => $q->orderBy('priority')])
            ->withCount('routes')
            ->orderBy('provider')
            ->orderBy('name')
            ->get();

        $items = CloudDocumentItem::query()
            ->with('connection:id,name,provider')
            ->latest('id')
            ->paginate(25, pageName: 'protokoll')
            ->withQueryString();

        // Container-Picker (SharePoint-Muster): auf Anforderung die wählbaren
        // Container GENAU EINER Verbindung laden — nie beim reinen Seitenaufruf
        // für alle Verbindungen (je Aufruf externe API-Requests). Fehler → leer;
        // die Freitext-Eingabe bleibt als Fallback.
        // Sqid aus dem Picker (W3.3); Alt-Bookmarks mit roher ID bleiben lesbar.
        $containerConnectionId = (int) (\App\Support\Sqid::decodeOrNumeric(CloudDocumentConnection::class, (string) $request->query('containers', '')) ?? 0);
        $containerSearch = trim((string) $request->query('container_search', ''));
        $containerOptions = [];
        $containerLoadFailed = false;
        $pickerConnection = $connections->firstWhere('id', $containerConnectionId);
        if ($pickerConnection instanceof CloudDocumentConnection && $pickerConnection->external_account_id !== null) {
            $plugin = app(PluginManager::class)->find($pickerConnection->provider->pluginId());
            if ($plugin instanceof DocumentIntakeSource) {
                try {
                    $containerOptions = $plugin->intakeContainers($pickerConnection, $containerSearch !== '' ? $containerSearch : null);
                } catch (Throwable) {
                    $containerLoadFailed = true;
                }
            }
        }

        // Verbinden liefert das Anbieter-Plugin (MVP-1041), ebenso die erneute Anmeldung (MVP-1620).
        $connectActions = [];
        foreach (CloudIntakeProvider::cases() as $provider) {
            $plugin = app(PluginManager::class)->find($provider->pluginId());
            if ($plugin instanceof IntakeSourceConnector) {
                $connectActions[] = $plugin->intakeConnectAction();
            }
        }
        $reconnectActions = [];
        foreach ($connections as $connection) {
            $plugin = $connection->status->needsReconnect() ? app(PluginManager::class)->find($connection->provider->pluginId()) : null;
            if ($plugin instanceof IntakeSourceConnector) {
                $reconnectActions[(int) $connection->id] = $plugin->intakeConnectAction($connection);
            }
        }

        return view('admin.cloud-intake.index', [
            'connections' => $connections,
            'connectActions' => $connectActions,
            'reconnectActions' => $reconnectActions,
            'items' => $items,
            'canManage' => Gate::allows('create', CloudDocumentConnection::class),
            'canManageRoutes' => Gate::allows('create', CloudDocumentRoute::class),
            'containerConnectionId' => $containerConnectionId,
            'containerSearch' => $containerSearch,
            'containerOptions' => $containerOptions,
            'containerLoadFailed' => $containerLoadFailed,
            'previews' => (array) $request->session()->get(self::PREVIEW_SESSION, []),
            'targetLabels' => $this->targetLabels($connections),
        ]);
    }

    /** Container + Stammordner setzen (nach Konto-Bestätigung). */
    public function selectFolder(Request $request, CloudDocumentConnection $connection): RedirectResponse {
        Gate::authorize('update', $connection);

        $data = $request->validate([
            'container_id' => ['required', 'string', 'max:512'],
            'root_folder_id' => ['nullable', 'string', 'max:512'],
            'root_folder_path' => ['required', 'string', 'max:1024'],
        ]);

        // Überlappende Stammordner desselben Kontos blockieren (Konzept
        // §Datenmodell): keine Datei darf über zwei Quellen konkurrieren.
        $normalized = '/' . trim((string) $data['root_folder_path'], '/');
        $overlap = CloudDocumentConnection::query()
            ->whereKeyNot($connection->id)
            ->where('provider', $connection->provider->value)
            ->where('external_account_id', $connection->external_account_id)
            ->where('container_id', $data['container_id'])
            ->get()
            ->first(function (CloudDocumentConnection $other) use ($normalized): bool {
                $otherPath = '/' . trim((string) $other->root_folder_path, '/');

                return str_starts_with(mb_strtolower($normalized) . '/', mb_strtolower($otherPath) . '/')
                    || str_starts_with(mb_strtolower($otherPath) . '/', mb_strtolower($normalized) . '/');
            });
        if ($overlap !== null) {
            return back()->with('error', __('cloud_intake.flash.overlapping_root', ['name' => $overlap->name]));
        }

        // Ordnerwechsel = neuer Abgleich: Checkpoint verwerfen (Nachweise
        // dedupen den Wieder-Import); der neue Erstlauf braucht wieder eine
        // bestätigte Vorschau.
        $connection->forceFill([
            'container_id' => $data['container_id'],
            'root_folder_id' => ($data['root_folder_id'] ?? '') !== '' ? $data['root_folder_id'] : null,
            'root_folder_path' => $normalized,
            'checkpoint' => null,
            'start_confirmed_at' => null,
        ])->save();
        $request->session()->forget(self::PREVIEW_SESSION . '.' . $connection->id);

        $this->refreshStatus($connection);
        $connection->audit('cloudIntake.folderSelected', ['path' => $normalized]);

        // Change-Notification (MS365-Plan §8): Subscription auf dem neu
        // gewählten Container best-effort anlegen — der tägliche Renewal-Job
        // holt Fehlschläge nach, Polling funktioniert auch ohne.
        $plugin = app(PluginManager::class)->find($connection->provider->pluginId());
        if ($plugin instanceof \App\Plugins\Contracts\DocumentIntakeSubscriptions) {
            try {
                $plugin->intakeSubscribe($connection->fresh() ?? $connection);
            } catch (Throwable) {
                // bewusst still — Webhooks sind nur Beschleuniger.
            }
        }

        return back()->with('success', __('cloud_intake.flash.folder_selected'));
    }

    /** Erstlaufvorschau: erste Delta-Seite OHNE Übernahme (Konzept §Vorschau). */
    public function preview(Request $request, CloudDocumentConnection $connection): RedirectResponse {
        Gate::authorize('view', $connection);
        abort_unless(Gate::allows('viewAny', CloudDocumentConnection::class), 403);

        $plugin = app(PluginManager::class)->find($connection->provider->pluginId());
        if (! $plugin instanceof DocumentIntakeSource) {
            return back()->with('error', __('cloud_intake.flash.not_configured'));
        }

        try {
            $page = $plugin->intakeChanges($connection, null);
        } catch (Throwable $e) {
            return back()->with('error', __('cloud_intake.flash.preview_failed', ['class' => class_basename($e)]));
        }

        $routes = $connection->routes()->where('active', true)->orderBy('priority')->get();
        $patterns = app(RoutePatternValidator::class);
        $matched = 0;
        $bytes = 0;
        foreach ($page->items as $item) {
            $bytes += $item->size;
            $extension = strtolower(pathinfo($item->name, PATHINFO_EXTENSION));
            if ($patterns->firstMatch($routes, $item->path, $extension, $item->size) !== null) {
                $matched++;
            }
        }

        $result = [
            'files' => count($page->items),
            'matched' => $matched,
            'unmatched' => count($page->items) - $matched,
            'size' => \Illuminate\Support\Number::fileSize($bytes, precision: 1),
            'more' => $page->hasMore ? ' (+)' : '',
        ];
        $request->session()->put(self::PREVIEW_SESSION . '.' . $connection->id, $result + ['at' => now()->toIso8601String()]);

        return back()->with('success', __('cloud_intake.flash.preview_result', $result));
    }

    /**
     * Start nach der Vorschau bestätigen (Feature 080 §Vorschau und
     * Erstabgleich): erst danach importiert die Verbindung.
     */
    public function confirmStart(Request $request, CloudDocumentConnection $connection): RedirectResponse {
        Gate::authorize('update', $connection);

        $key = self::PREVIEW_SESSION . '.' . $connection->id;
        $preview = $request->session()->get($key);
        $at = is_array($preview) && is_string($preview['at'] ?? null) ? \Illuminate\Support\Carbon::parse($preview['at']) : null;
        if ($at === null || $at->lt(now()->subMinutes(self::PREVIEW_VALID_MINUTES))) {
            return back()->with('error', __('cloud_intake.flash.preview_required'));
        }
        if ($connection->start_confirmed_at !== null) {
            return back()->with('success', __('cloud_intake.flash.start_confirmed'));
        }

        $connection->forceFill(['start_confirmed_at' => now()])->save();
        $request->session()->forget($key);
        $connection->audit('cloudIntake.startConfirmed', [
            'files' => (int) ($preview['files'] ?? 0),
            'matched' => (int) ($preview['matched'] ?? 0),
            'unmatched' => (int) ($preview['unmatched'] ?? 0),
        ]);
        $this->refreshStatus($connection);

        return back()->with('success', $connection->status === CloudIntakeConnectionStatus::Active
            ? __('cloud_intake.flash.start_confirmed')
            : __('cloud_intake.flash.start_confirmed_not_ready'));
    }

    /** Verbindung trennen: Tokens/Checkpoint weg, Nachweise bleiben. */
    public function disconnect(CloudDocumentConnection $connection): RedirectResponse {
        Gate::authorize('delete', $connection);

        // Subscription abmelden, solange das Token noch gültig ist
        // (best effort — Provider räumen abgelaufene Subscriptions selbst ab).
        $plugin = app(PluginManager::class)->find($connection->provider->pluginId());
        if ($plugin instanceof \App\Plugins\Contracts\DocumentIntakeSubscriptions) {
            $plugin->intakeUnsubscribe($connection);
        }

        $connection->audit('cloudIntake.disconnected', ['provider' => $connection->provider->value]);
        // nullOnDelete an den Nachweisen — importierte Dokumente bleiben.
        $connection->delete();

        return back()->with('success', __('cloud_intake.flash.disconnected'));
    }

    // ── Ordnerregeln ────────────────────────────────────────────────────

    public function createRoute(CloudDocumentConnection $connection): View {
        Gate::authorize('create', CloudDocumentRoute::class);

        return $this->routeDialog($connection, new CloudDocumentRoute(['priority' => 100, 'active' => true]));
    }

    public function editRoute(CloudDocumentRoute $cloudRoute): View {
        Gate::authorize('update', $cloudRoute);

        $connection = $cloudRoute->connection;
        abort_if($connection === null, 404);

        return $this->routeDialog($connection, $cloudRoute);
    }

    public function storeRoute(SaveCloudRouteRequest $request, CloudDocumentConnection $connection): RedirectResponse {
        Gate::authorize('create', CloudDocumentRoute::class);

        $connection->routes()->create($request->routeAttributes() + [
            'organization_id' => $connection->organization_id,
        ]);
        $this->refreshStatus($connection);

        return back()->with('success', __('cloud_intake.flash.route_saved') . ' ' . $request->testSummary());
    }

    public function updateRoute(SaveCloudRouteRequest $request, CloudDocumentRoute $cloudRoute): RedirectResponse {
        Gate::authorize('update', $cloudRoute);

        $cloudRoute->update($request->routeAttributes());
        $this->refreshStatus($cloudRoute->connection);

        return back()->with('success', __('cloud_intake.flash.route_saved') . ' ' . $request->testSummary());
    }

    /** Regeldialog mit fester Zielreferenz und — beim Bearbeiten — der Vorschau des gespeicherten Testpfads. */
    private function routeDialog(CloudDocumentConnection $connection, CloudDocumentRoute $route): View {
        $targets = app(CloudRouteTargets::class);
        $current = $targets->resolveStored($route->target_ref_type, $route->target_ref_id !== null ? (int) $route->target_ref_id : null);

        return view('admin.cloud-intake._route_dialog', [
            'connection' => $connection,
            'route' => $route,
            'targetOptions' => $targets->options($current),
            'targetValue' => $targets->value($current),
            'testPreview' => $route->exists && $route->test_path !== null
                ? app(CloudRouteTester::class)->test($connection, $route, $route->test_path)
                : null,
        ]);
    }

    /**
     * Feste Zielreferenzen der angezeigten Regeln als Text.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, CloudDocumentConnection>  $connections
     * @return array<int, string>
     */
    private function targetLabels(\Illuminate\Database\Eloquent\Collection $connections): array {
        $targets = app(CloudRouteTargets::class);
        $labels = [];
        foreach ($connections as $connection) {
            foreach ($connection->routes as $route) {
                $ref = $targets->resolveStored($route->target_ref_type, $route->target_ref_id !== null ? (int) $route->target_ref_id : null);
                if ($ref !== null) {
                    $labels[(int) $route->id] = $targets->describe($ref);
                }
            }
        }

        return $labels;
    }

    public function destroyRoute(CloudDocumentRoute $cloudRoute): RedirectResponse {
        Gate::authorize('delete', $cloudRoute);

        $connection = $cloudRoute->connection;
        $cloudRoute->delete();
        $this->refreshStatus($connection);

        return back()->with('success', __('cloud_intake.flash.route_deleted'));
    }

    /**
     * Aktiv erst mit bestätigtem Konto, gewähltem Ordner, mindestens einer
     * aktiven Regel (Konzept §Verbindung und Preflight) und bestätigtem
     * Start nach der Vorschau.
     */
    private function refreshStatus(?CloudDocumentConnection $connection): void {
        // Abgelaufen, blockiert oder abgeschaltet heilt nur die erneute Anmeldung (MVP-1620).
        if ($connection === null || $connection->status->needsReconnect()) {
            return;
        }

        $ready = $connection->external_account_id !== null
            && $connection->container_id !== null
            && $connection->routes()->where('active', true)->exists();

        $connection->forceFill(['status' => $connection->readyStatus($ready)])->save();
    }
}
