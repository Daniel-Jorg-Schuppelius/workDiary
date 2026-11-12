<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncConflictController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Integration;

use App\Enums\Sync\SyncConflictResolution;
use App\Http\Controllers\Controller;
use App\Services\Sync\SyncCommandService;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Validation\Rule;

/**
 * Konflikt der Offline-Erfassung auflösen (MVP-1595). Die Seite
 * „Offline-Änderungen“ ruft das per JSON auf; Konflikte anderer Geräte
 * verwirft ein normales Formular. Nur eigene Konflikte.
 */
class SyncConflictController extends Controller {
    public function __invoke(Request $request, SyncCommandService $service): JsonResponse|RedirectResponse {
        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'resolution' => ['required', Rule::enum(SyncConflictResolution::class)],
            'resent_client_uuid' => ['nullable', 'uuid', 'required_if:resolution,' . SyncConflictResolution::Resent->value],
            'edited' => ['nullable', 'boolean'],
        ]);

        $found = $service->resolveConflict(
            $this->authUser(),
            (string) $data['client_uuid'],
            SyncConflictResolution::from((string) $data['resolution']),
            $data['resent_client_uuid'] ?? null,
            (bool) ($data['edited'] ?? false),
        );

        if ($request->expectsJson()) {
            return response()->json(['resolved' => $found], $found ? 200 : 404);
        }

        return back()->with($found ? 'success' : 'error', __($found ? 'offline.flash.resolved' : 'offline.flash.not_found'));
    }
}
