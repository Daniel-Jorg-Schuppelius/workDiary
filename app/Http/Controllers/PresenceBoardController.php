<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PresenceBoardController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{Organization, User};
use App\Services\Attendance\EmergencyAttendanceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Aktuelle Personal-Belegung (MVP-524) — Alltagssicht für Empfang/Zentrale:
 * wer ist im Haus, wer außer Haus, wer abwesend. Bewusst datensparsam:
 * Fehlgründe werden NIE angezeigt (neutral „abwesend"), Feature ist je
 * Organisation Opt-in (`settings.presence.board_enabled`, Default AUS).
 * Nutzt dieselbe Momentaufnahme wie die Notfallliste (MVP-518), aber ohne
 * deren sensible Detailtiefe und ohne eigene Berechtigungsstufe — sichtbar
 * für alle angemeldeten Mitglieder der Organisation.
 */
class PresenceBoardController extends Controller {
    public function index(EmergencyAttendanceService $service): View {
        /** @var User $user */
        $user = Auth::user();

        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if (! $org instanceof Organization || ! (bool) data_get($org->settings, 'presence.board_enabled', false)) {
            abort(404);
        }

        $snapshot = $service->snapshot((int) $user->organization_id);

        return view('presence.board', [
            'snapshot' => $snapshot,
        ]);
    }
}
