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
use App\Models\{AttendanceTerminal, FlexBalance, Organization, User};
use App\Services\Absence\VacationBalanceService;
use App\Services\Attendance\TerminalStampService;
use Illuminate\Http\{JsonResponse, Request};

/**
 * Ingest-Endpunkt für Hardware-Stempelterminals (Feature 061, MVP-130).
 * Sessionlos, ohne CSRF; Auth über Gerätetoken im Pfad (nur SHA-256-Hash
 * gespeichert). Badge-Scan → Anwesenheitsstempel via {@see TerminalStampService};
 * fremdes Token und unbekannter Badge werden abgewiesen und auditiert (DoD).
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

            // Wartungsmodus (Rang 65): Ingest pausiert nur bei explizitem block_ingest.
            if ($organization->maintenanceBlocksIngest()) {
                return response()->json(['status' => 'maintenance'], 503, ['Retry-After' => '3600']);
            }
        }

        // MVP-516: `credential` als Alias (herstellerneutrale Terminals senden
        // die Kennung teils unter diesem Namen, vgl. Feature 103).
        $badgeUid = trim((string) ($request->input('badge_uid') ?? $request->input('badge') ?? $request->input('credential') ?? ''));
        if ($badgeUid === '') {
            return response()->json(['status' => 'missing_badge'], 422);
        }

        // Ereignistyp work (Default) oder break; unbekannt → work (alte Terminals senden das Feld nicht).
        $eventType = strtolower(trim((string) ($request->input('event_type') ?? 'work')));
        if ($eventType !== 'break') {
            $eventType = 'work';
        }

        // Optionaler Offline-Pufferstand des Terminals (MVP-516, Diagnose).
        $queued = $request->has('queued') && is_numeric($request->input('queued'))
            ? (int) $request->input('queued')
            : null;

        $result = $service->stamp(
            $terminal,
            $badgeUid,
            (string) ($request->input('event') ?? 'toggle'),
            $request->has('occurred_at') ? (string) $request->input('occurred_at') : null,
            $request->has('event_id') ? (string) $request->input('event_id') : null,
            $eventType,
            $queued,
        );
        $status = $result['status'];

        // Manipulationsversuch nachvollziehbar machen (ohne Klartext-Kennung).
        if ($status === 'unknown_badge') {
            $terminal->audit('terminal.unknown_badge', ['terminal_id' => (int) $terminal->id]);
        }

        $payload = ['status' => $status];
        if ($terminal->show_status && $result['user'] !== null && in_array($status, ['clocked_in', 'clocked_out', 'break_started', 'break_ended'], true)) {
            $payload += $this->statusInfo($result['user']);
        }

        return response()->json($payload);
    }

    /**
     * MVP-516: Statusinformationen fürs Terminal-Display — nur je Terminal
     * opt-in (`show_status`, Standard AUS: die Anzeige am Gerät ist für
     * Umstehende sichtbar) und nur nach erfolgreichem Badge-Match.
     *
     * @return array<string, mixed>
     */
    private function statusInfo(User $user): array {
        $info = ['employee' => (string) $user->name];

        if ($user->isFlexEligible()) {
            // Jüngste FlexBalance-Zeile trägt den kumulierten Saldo
            // (gleiche Lesart wie die Urlaub-&-Flex-Auswertung).
            $latest = FlexBalance::query()
                ->where('user_id', $user->id)
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->first(['balance_minutes']);
            if ($latest !== null) {
                $info['flex_balance_minutes'] = (int) $latest->balance_minutes;
            }
        }

        $balance = app(VacationBalanceService::class)->balanceFor((int) $user->id, (int) now()->year);
        if ($balance->hasEntitlement) {
            $info['vacation_days_remaining'] = $balance->remainingDays();
        }

        // MVP-526: Zusatz-Zeitkonten mit Terminal-Freigabe (jüngster Stand).
        $accounts = \App\Models\TimeAccount::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $user->organization_id)
            ->where('is_active', true)
            ->where('show_on_terminal', true)
            ->get();
        foreach ($accounts as $account) {
            $latest = \App\Models\TimeAccountBalance::query()
                ->withoutGlobalScopes()
                ->where('time_account_id', $account->getKey())
                ->where('user_id', $user->getKey())
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->first();
            $info['time_accounts'][] = [
                'code' => $account->code,
                'name' => $account->name,
                'balance' => $latest !== null ? (float) $latest->balance : 0.0,
                'formatted' => $account->unit->format($latest !== null ? (float) $latest->balance : 0.0),
            ];
        }

        return $info;
    }
}
