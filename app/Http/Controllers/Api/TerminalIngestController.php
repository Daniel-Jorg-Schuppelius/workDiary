<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TerminalIngestController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{AttendanceTerminal, Organization};
use App\Services\Attendance\TerminalStampService;
use Illuminate\Http\{JsonResponse, Request};

/**
 * Ingest-Endpunkt für Hardware-Stempelterminals (Feature 061, MVP-130).
 * Sessionlos und ohne CSRF; autorisiert über einen Gerätetoken im Pfad (nur
 * SHA-256-Hash gespeichert, Muster `location/ingest`). Ein Badge-Scan wird über
 * den {@see TerminalStampService} zu einem Anwesenheitsstempel — fremdes Token
 * und unbekannter Badge werden abgewiesen und auditiert (DoD).
 */
class TerminalIngestController extends Controller {
    public function __invoke(Request $request, string $token, TerminalStampService $service): JsonResponse {
        $terminal = AttendanceTerminal::query()->withoutGlobalScopes()
            ->where('token_hash', AttendanceTerminal::hashToken($token))
            ->where('active', true)
            ->first();
        if (! $terminal instanceof AttendanceTerminal) {
            return response()->json(['status' => 'invalid_token'], 401);
        }

        // Org-Kontext für nachgelagerte (scoped) Operationen binden.
        $organization = Organization::query()->whereKey($terminal->organization_id)->first();
        if ($organization instanceof Organization) {
            app()->instance('currentOrganization', $organization);

            // Wartungsmodus (Rang 65): Stempeln läuft standardmäßig weiter;
            // nur bei explizitem block_ingest pausiert der Ingest.
            if ($organization->maintenanceBlocksIngest()) {
                return response()->json(['status' => 'maintenance'], 503, ['Retry-After' => '3600']);
            }
        }

        $badgeUid = trim((string) ($request->input('badge_uid') ?? $request->input('badge') ?? ''));
        if ($badgeUid === '') {
            return response()->json(['status' => 'missing_badge'], 422);
        }

        // Fachlicher Ereignistyp: work (Kommen/Gehen, Default) oder break
        // (Pausen-Toggle). Unbekannte Werte werden auf work normalisiert
        // (rückwärtskompatibel: alte Terminals senden das Feld nicht).
        $eventType = strtolower(trim((string) ($request->input('event_type') ?? 'work')));
        if ($eventType !== 'break') {
            $eventType = 'work';
        }

        $status = $service->stamp(
            $terminal,
            $badgeUid,
            (string) ($request->input('event') ?? 'toggle'),
            $request->has('occurred_at') ? (string) $request->input('occurred_at') : null,
            $request->has('event_id') ? (string) $request->input('event_id') : null,
            $eventType,
        );

        // Manipulationsversuch nachvollziehbar machen (ohne Klartext-Kennung).
        if ($status === 'unknown_badge') {
            $terminal->audit('terminal.unknown_badge', ['terminal_id' => (int) $terminal->id]);
        }

        return response()->json(['status' => $status]);
    }
}
