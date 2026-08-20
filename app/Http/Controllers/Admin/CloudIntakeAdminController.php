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

use App\Enums\CloudIntake\CloudIntakeConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloudIntake\SaveCloudRouteRequest;
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem, CloudDocumentRoute};
use App\Plugins\Contracts\DocumentIntakeSource;
use App\Plugins\PluginManager;
use App\Services\CloudIntake\RoutePatternValidator;
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

        return view('admin.cloud-intake.index', [
            'connections' => $connections,
            'items' => $items,
            'canManage' => Gate::allows('create', CloudDocumentConnection::class),
            'canManageRoutes' => Gate::allows('create', CloudDocumentRoute::class),
            'containerConnectionId' => $containerConnectionId,
            'containerSearch' => $containerSearch,
            'containerOptions' => $containerOptions,
            'containerLoadFailed' => $containerLoadFailed,
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
        // dedupen den Wieder-Import).
        $connection->forceFill([
            'container_id' => $data['container_id'],
            'root_folder_id' => ($data['root_folder_id'] ?? '') !== '' ? $data['root_folder_id'] : null,
            'root_folder_path' => $normalized,
            'checkpoint' => null,
        ])->save();

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
    public function preview(CloudDocumentConnection $connection): RedirectResponse {
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

        return back()->with('success', __('cloud_intake.flash.preview_result', [
            'files' => count($page->items),
            'matched' => $matched,
            'unmatched' => count($page->items) - $matched,
            'size' => \Illuminate\Support\Number::fileSize($bytes, precision: 1),
            'more' => $page->hasMore ? ' (+)' : '',
        ]));
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

        return view('admin.cloud-intake._route_dialog', [
            'connection' => $connection,
            'route' => new CloudDocumentRoute(['priority' => 100, 'active' => true]),
        ]);
    }

    public function editRoute(CloudDocumentRoute $cloudRoute): View {
        Gate::authorize('update', $cloudRoute);

        return view('admin.cloud-intake._route_dialog', [
            'connection' => $cloudRoute->connection,
            'route' => $cloudRoute,
        ]);
    }

    public function storeRoute(SaveCloudRouteRequest $request, CloudDocumentConnection $connection): RedirectResponse {
        Gate::authorize('create', CloudDocumentRoute::class);

        $connection->routes()->create($request->routeAttributes() + [
            'organization_id' => $connection->organization_id,
        ]);
        $this->refreshStatus($connection);

        return back()->with('success', __('cloud_intake.flash.route_saved'));
    }

    public function updateRoute(SaveCloudRouteRequest $request, CloudDocumentRoute $cloudRoute): RedirectResponse {
        Gate::authorize('update', $cloudRoute);

        $cloudRoute->update($request->routeAttributes());
        $this->refreshStatus($cloudRoute->connection);

        return back()->with('success', __('cloud_intake.flash.route_saved'));
    }

    public function destroyRoute(CloudDocumentRoute $cloudRoute): RedirectResponse {
        Gate::authorize('delete', $cloudRoute);

        $connection = $cloudRoute->connection;
        $cloudRoute->delete();
        $this->refreshStatus($connection);

        return back()->with('success', __('cloud_intake.flash.route_deleted'));
    }

    /**
     * Aktiv erst mit bestätigtem Konto, gewähltem Ordner UND mindestens
     * einer aktiven Regel (Konzept §Verbindung und Preflight).
     */
    private function refreshStatus(?CloudDocumentConnection $connection): void {
        if ($connection === null || $connection->status === CloudIntakeConnectionStatus::Disabled) {
            return;
        }

        $ready = $connection->external_account_id !== null
            && $connection->container_id !== null
            && $connection->routes()->where('active', true)->exists();

        $connection->forceFill([
            'status' => $ready ? CloudIntakeConnectionStatus::Active : CloudIntakeConnectionStatus::Draft,
        ])->save();
    }
}
