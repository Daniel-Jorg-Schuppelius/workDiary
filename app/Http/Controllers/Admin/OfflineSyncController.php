<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OfflineSyncController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Sync\SyncCommandStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\SyncCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin-Sicht auf die Offline-Synchronisierung (Feature 004, Restpunkt aus
 * dem Restpunkte-Plan 2026-08; Datenbasis Feature 035, Phase 1).
 *
 * Beantwortet die Frage „welche Daten sind mobil/offline entstanden — und sind
 * sie angekommen?": jede Zeile ein Outbox-Befehl mit Ergebnis. **Abgewiesene
 * und Konflikt-Befehle sind der eigentliche Grund für diese Seite** — sie
 * bedeuten, dass eine Offline-Erfassung NICHT im Bestand gelandet ist und
 * jemand nachfassen muss; die Vorbelegung filtert deshalb nicht auf sie,
 * zeigt sie aber als eigene Zähler.
 */
class OfflineSyncController extends Controller {
    public function index(Request $request): View {
        Gate::authorize(Permission::MetricsView->value);

        $filters = [
            'status' => (string) $request->query('status', ''),
            'type' => (string) $request->query('type', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];

        $query = SyncCommand::query()->with('user:id,name');

        if (SyncCommandStatus::tryFrom($filters['status']) !== null) {
            $query->where('result_status', $filters['status']);
        }
        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }
        if ($filters['from'] !== '') {
            $query->where('created_at', '>=', $filters['from'] . ' 00:00:00');
        }
        if ($filters['to'] !== '') {
            $query->where('created_at', '<=', $filters['to'] . ' 23:59:59');
        }

        // Zähler je Ergebnis über den GESAMTEN Bestand (nicht die gefilterte
        // Sicht): Sie sind die Antwort auf „gibt es irgendwo ein Problem?" -
        // ein Filter darf diese Frage nicht verdecken.
        $counts = SyncCommand::query()
            ->selectRaw('result_status, COUNT(*) AS n')
            ->groupBy('result_status')
            ->pluck('n', 'result_status');

        return view('admin.offline-sync.index', [
            'commands' => $query->orderByDesc('id')->paginate(50)->withQueryString(),
            'filters' => $filters,
            'counts' => $counts,
            'typeOptions' => SyncCommand::query()->select('type')->distinct()->orderBy('type')->pluck('type'),
        ]);
    }
}
