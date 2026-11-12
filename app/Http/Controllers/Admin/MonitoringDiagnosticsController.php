<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonitoringDiagnosticsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Diagnostics\{DiagnosticsService, MonitoringTokenService};
use Illuminate\Http\{JsonResponse, Request};

/**
 * Diagnose-JSON für externe Überwachung (MVP-1618): ohne Sitzung, Zugang nur
 * über `Authorization: Bearer <Token>` — nie im Pfad, damit der Token nicht in
 * Zugriffsprotokollen landet. Personenbezogene Kennzahlen fehlen.
 */
class MonitoringDiagnosticsController extends Controller {
    public function __invoke(Request $request, DiagnosticsService $diagnostics, MonitoringTokenService $tokens): JsonResponse {
        $token = $tokens->resolve($request->bearerToken());
        if ($token === null) {
            return response()->json(['error' => 'unauthorized'], 401, ['WWW-Authenticate' => 'Bearer']);
        }

        $report = $diagnostics->collect();
        $tokens->recordUse($token);

        return response()->json($report->toMonitoringArray(), 200, ['Cache-Control' => 'no-store']);
    }
}
