<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PresenceStatusController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Time;

use App\Enums\Attendance\PresenceStatusKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Services\Attendance\PresenceStatusService;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Selbstauskunft am Anwesenheits-Board (Feature 103, MVP-1669): jede Person
 * setzt und löscht nur ihren eigenen Stand — es gibt keinen Weg, den Stand
 * einer anderen Person zu setzen. Nur bei eingeschaltetem Board der
 * Organisation, sonst 404 wie das Board.
 */
class PresenceStatusController extends Controller {
    use ResolvesCurrentOrganization;

    /** Schnellwahl „für … Minuten“ im Dialog. */
    public const array DURATIONS = [15, 30, 60, 120, 240, 480];

    public function __construct(private readonly PresenceStatusService $presence) {}

    public function edit(): View {
        $this->assertBoardEnabled();

        return view('presence._status_dialog', [
            'current' => $this->presence->current($this->authUser()),
            'durations' => self::DURATIONS,
        ]);
    }

    public function update(Request $request): RedirectResponse {
        $this->assertBoardEnabled();
        $data = $request->validate([
            'kind' => ['required', Rule::enum(PresenceStatusKind::class)],
            'duration' => ['nullable', 'integer', Rule::in(self::DURATIONS)],
            'valid_until' => ['nullable', 'date'],
        ]);
        $kind = PresenceStatusKind::from((string) $data['kind']);
        // Ein genaues Ende geht der Schnellwahl vor; ohne beides gilt der Stand bis zum Zurücksetzen.
        $until = match (true) {
            is_string($data['valid_until'] ?? null) && $data['valid_until'] !== '' => Tz::parse($data['valid_until']),
            ($data['duration'] ?? null) !== null => CarbonImmutable::now()->addMinutes((int) $data['duration']),
            default => null,
        };

        $this->presence->set($this->authUser(), $kind, $until);

        return redirect()->route('presence.board')->with('success', __('presence.flash.set', ['status' => $kind->label()]));
    }

    public function destroy(): RedirectResponse {
        $this->assertBoardEnabled();
        $this->presence->clear($this->authUser());

        return redirect()->route('presence.board')->with('success', __('presence.flash.cleared'));
    }

    private function assertBoardEnabled(): void {
        abort_unless($this->presence->enabled($this->currentOrganizationOrNull()), 404);
    }
}
