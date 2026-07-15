<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncCommandController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Services\Sync\SyncCommandService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Idempotenter Batch-Endpunkt der Offline-Sync-Outbox (Feature 035, Phase 1;
 * interne Web-API analog `api.internal.geocode` — Session-Auth + CSRF-Header
 * aus der PWA). Nimmt bis zu 50 Outbox-Befehle entgegen und beantwortet jeden
 * einzeln mit applied | duplicate | conflict | rejected, damit der Client die
 * Outbox gezielt räumen kann (offline-sync-architektur.md §3.2).
 */
class SyncCommandController extends Controller {
    public function __invoke(Request $request, SyncCommandService $service): JsonResponse {
        $data = $request->validate([
            'commands' => ['required', 'array', 'min:1', 'max:50'],
            'commands.*.client_uuid' => ['required', 'uuid'],
            'commands.*.type' => ['required', 'string', Rule::in(SyncCommandService::TYPES)],
            'commands.*.payload' => ['nullable', 'array'],
            'commands.*.captured_at' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        abort_unless($user !== null && Auth::check(), 401);

        $results = [];
        foreach ($data['commands'] as $command) {
            $results[] = $service->handle($user, [
                'client_uuid' => $command['client_uuid'],
                'type' => $command['type'],
                'payload' => $command['payload'] ?? [],
                'captured_at' => $command['captured_at'] ?? null,
            ]);
        }

        return response()->json(['results' => $results]);
    }
}
