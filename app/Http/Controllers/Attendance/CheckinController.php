<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CheckinController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Models\Time\AttendanceCheckpoint;
use App\Services\Attendance\{AttendanceClockService, CheckpointCheckinService};
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Validation\Rule;

/**
 * QR-/NFC-Check-in (MVP-800): Der Code am Standort oder Fahrzeug öffnet diese
 * Seite auf dem eigenen Gerät; gestempelt wird erst nach Bestätigung.
 */
class CheckinController extends Controller {
    public function show(Request $request, string $token, AttendanceClockService $clock): Response {
        $checkpoint = $this->checkpoint($request, $token);
        /** @var User $user */
        $user = $request->user();

        $response = response()->view('attendance.checkin', [
            'checkpoint' => $checkpoint,
            'open' => $clock->current($user),
        ]);

        // Die App sperrt die Ortsabfrage sonst überall; nur Punkte mit Radius brauchen sie.
        if ($checkpoint->requiresLocation()) {
            $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=()');
        }

        return $response;
    }

    public function stamp(Request $request, string $token, CheckpointCheckinService $checkins): RedirectResponse {
        $checkpoint = $this->checkpoint($request, $token);
        $data = $request->validate([
            'action' => ['required', Rule::in(['in', 'out'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $action = $data['action'] === 'in' ? 'in' : 'out';
        $checkins->stamp(
            $checkpoint,
            $user,
            $action,
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null,
        );

        return redirect()->route('checkin.show', $token)
            ->with('success', __('attendance.checkin.flash.' . $action, ['name' => $checkpoint->name]));
    }

    /** Nur aktive Punkte der eigenen Organisation — fremde und gesperrte melden 404. */
    private function checkpoint(Request $request, string $token): AttendanceCheckpoint {
        $organizationId = $request->user()?->organization_id;
        abort_if($organizationId === null, 404);

        $checkpoint = AttendanceCheckpoint::query()
            ->where('token', $token)
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->with(['site', 'vehicle'])
            ->first();
        abort_unless($checkpoint instanceof AttendanceCheckpoint, 404);

        return $checkpoint;
    }
}
