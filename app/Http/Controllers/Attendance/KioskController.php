<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KioskController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Models\Time\AttendanceTerminal;
use Illuminate\Http\Response;

/**
 * Kiosk-Modus (MVP-800): ein Tablet am gemeinsamen Arbeitsplatz wird zum
 * Stempelterminal. Die Seite hat keine Anmeldung — sie ist über das
 * Gerätetoken des Terminals autorisiert und stempelt über denselben
 * Ingest-Endpunkt wie ein Hardware-Terminal (`api.terminal.ingest`), also mit
 * denselben Regeln, Sperren und derselben Zeitplausibilität.
 */
class KioskController extends Controller {
    public function show(string $token): Response {
        $terminal = AttendanceTerminal::query()->withoutGlobalScopes()
            ->where('token_hash', AttendanceTerminal::hashToken($token))
            ->where('active', true)
            ->first();
        abort_unless($terminal instanceof AttendanceTerminal, 404);

        $organization = Organization::query()->whereKey($terminal->organization_id)->first();
        abort_unless($organization instanceof Organization && $organization->publicSurfacesAvailable(), 423);

        return response()
            ->view('attendance.kiosk', [
                'terminal' => $terminal,
                'ingestUrl' => route('api.terminal.ingest', ['token' => $token]),
            ])
            // Das Token steht im Pfad: nicht weitergeben, nicht zwischenspeichern, nicht indexieren.
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
