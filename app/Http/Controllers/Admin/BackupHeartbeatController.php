<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupHeartbeatController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, BackupHeartbeat};
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, Request};
use Symfony\Component\HttpFoundation\Response;

/**
 * Heartbeat-Endpoint für externes Backup-Tool (MVP-046 §5).
 *
 * Erwartet einen Bearer-Token, der mit `config('backup.heartbeat_token')`
 * übereinstimmt. Speichert einen Eintrag in `backup_heartbeats` und legt
 * ein Audit-Event `backup.heartbeatReceived` an.
 */
class BackupHeartbeatController extends Controller {
    public function store(Request $request): JsonResponse|Response {
        $expected = (string) config('backup.heartbeat_token', '');
        if ($expected === '') {
            return response()->json(['error' => 'heartbeat_disabled'], 503);
        }

        $provided = $this->extractToken($request);
        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $validated = $request->validate([
            'manifest_sha256' => ['nullable', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'size_bytes' => ['nullable', 'integer', 'min:0'],
            'source' => ['nullable', 'string', 'max:191'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $occurredAt = isset($validated['occurred_at'])
            ? CarbonImmutable::parse((string) $validated['occurred_at'])
            : CarbonImmutable::now();

        $heartbeat = BackupHeartbeat::create([
            'occurred_at' => $occurredAt,
            'size_bytes' => $validated['size_bytes'] ?? null,
            'manifest_hash' => isset($validated['manifest_sha256']) ? strtolower((string) $validated['manifest_sha256']) : null,
            'source' => $validated['source'] ?? null,
            'ip' => $request->ip(),
        ]);

        AuditLog::create([
            'organization_id' => null,
            'user_id' => null,
            'event' => 'backup.heartbeatReceived',
            'auditable_type' => BackupHeartbeat::class,
            'auditable_id' => $heartbeat->id,
            'changes' => [
                'occurred_at' => $occurredAt->toIso8601String(),
                'size_bytes' => $heartbeat->size_bytes,
                'manifest_hash' => $heartbeat->manifest_hash,
                'source' => $heartbeat->source,
            ],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json([
            'id' => $heartbeat->id,
            'occurred_at' => $occurredAt->toIso8601String(),
        ], 201);
    }

    private function extractToken(Request $request): string {
        $header = (string) $request->bearerToken();
        if ($header !== '') {
            return $header;
        }

        return (string) $request->input('token', '');
    }
}
