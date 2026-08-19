<?php
/*
 * Created on   : Tue Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolScanController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location\LocationDeviceToken;
use App\Models\Patrol\{PatrolCheckpoint, PatrolRun};
use App\Services\Patrol\PatrolService;
use Illuminate\Http\{JsonResponse, Request};
use RuntimeException;

/**
 * Geräte-Scan-Endpunkt der Wächterrundgänge (Feature 089, Folgepunkt
 * „NFC über Standort-Geräte"): Ein NFC-/Scanner-Gerät meldet den
 * Checkpoint-Token — authentifiziert über das vorhandene
 * Standort-Geräte-Token (Muster {@see LocationController::ingest}).
 *
 * Das Gerät kennt keinen Lauf: Der Checkpoint-Token löst die Route auf, und
 * gebucht wird auf deren AKTUELL LAUFENDEN Rundgang. Ohne laufenden Rundgang
 * wird der Scan abgewiesen — ein Scan ins Leere wäre kein Nachweis, sondern
 * eine stille Datenhalde.
 */
class PatrolScanController extends Controller {
    public function scan(Request $request, string $token, PatrolService $service): JsonResponse {
        $device = LocationDeviceToken::query()
            ->where('token_hash', LocationDeviceToken::hashToken($token))
            ->whereNull('revoked_at')
            ->first();

        if (! $device instanceof LocationDeviceToken) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $data = $request->validate(['checkpoint' => ['required', 'string', 'max:64']]);

        $checkpoint = PatrolCheckpoint::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $device->organization_id)
            ->where('token_hash', PatrolCheckpoint::hashToken((string) $data['checkpoint']))
            ->first();
        if ($checkpoint === null) {
            return response()->json(['error' => 'unknown_checkpoint'], 404);
        }

        $run = PatrolRun::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $device->organization_id)
            ->where('patrol_route_id', $checkpoint->patrol_route_id)
            ->where('status', PatrolRun::STATUS_RUNNING)
            ->first();
        if ($run === null) {
            return response()->json(['error' => 'no_running_patrol'], 422);
        }

        try {
            $service->scan($run, (string) $data['checkpoint']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'scan_rejected', 'message' => $e->getMessage()], 422);
        }

        $device->forceFill(['last_used_at' => now()])->save();

        return response()->json([
            'status' => 'ok',
            'checkpoint' => $checkpoint->label,
            'run' => $run->sqid,
        ]);
    }
}
