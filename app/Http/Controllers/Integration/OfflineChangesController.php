<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OfflineChangesController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Integration;

use App\Enums\Sync\SyncCommandStatus;
use App\Http\Controllers\Controller;
use App\Models\Integration\SyncCommand;
use Illuminate\View\View;

/**
 * Seite „Offline-Änderungen“ (Feature 035, MVP-367). Ausstehendes und
 * Abgelehntes liegen nur im Gerät; offene Konflikte kommen zusätzlich vom
 * Server (MVP-1595) — auch die eines anderen Geräts oder nach geleertem
 * Gerätespeicher, damit kein Konflikt unentschieden liegen bleibt.
 */
class OfflineChangesController extends Controller {
    public function __invoke(): View {
        $conflicts = SyncCommand::query()
            ->where('user_id', $this->authUser()->id)
            ->where('result_status', SyncCommandStatus::Conflict->value)
            ->whereNull('resolved_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['client_uuid', 'type', 'result_errors', 'captured_at', 'created_at'])
            ->map(static fn (SyncCommand $row): array => [
                'client_uuid' => $row->client_uuid,
                'type' => $row->type,
                'errors' => $row->result_errors,
                'captured_at' => ($row->captured_at ?? $row->created_at)?->toIso8601String(),
            ])
            ->values()
            ->all();

        return view('offline.changes', ['serverConflicts' => $conflicts]);
    }
}
