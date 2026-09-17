<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCheckInController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Http\Controllers\Controller;
use App\Models\Learning\{LearningEnrollment, LearningUnit};
use App\Models\User;
use App\Services\Learning\LearningEventService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * QR-Check-in am Präsenztermin (Feature 149, MVP-741).
 *
 * **Die Anwesenheit bestätigt die Person selbst** — wie bei der
 * Unterweisungs-Signatur (132). Der QR-Code ist nur der Weg dorthin, kein
 * Nachweis: er trägt eine befristete Signatur und öffnet ein Zeitfenster um
 * den Termin. Ohne das wäre ein abfotografierter Code ein Dauerticket.
 *
 * Bestätigt wird per POST, nie beim bloßen Aufruf des Links — sonst würde
 * ein Vorschau-Scanner die Anwesenheit setzen.
 */
class LearningCheckInController extends Controller {
    public function __construct(
        private readonly LearningEventService $events,
    ) {}

    /** Sitzungsschlüssel des Einmal-Nachweises je Einheit (signed-1). */
    private const NONCE_KEY = 'learning.checkin.nonce';

    public function show(Request $request, LearningUnit $unit): View {
        [$enrollment, $event] = $this->context($unit);

        // Der signierte Aufruf stellt einen einmaligen, sitzungsgebundenen
        // Nachweis aus; ohne ihn bestätigt das POST nichts
        // (Sicherheitsaudit 2026-09-17, signed-1).
        $nonce = Str::random(32);
        $request->session()->put(self::NONCE_KEY . '.' . $unit->getKey(), $nonce);

        return view('learning.my.checkin', [
            'nonce' => $nonce,
            'unit' => $unit,
            'event' => $event,
            'enrollment' => $enrollment,
            'open' => $this->events->isCheckInOpen($event),
            'window' => $this->events->checkInWindow($event),
        ]);
    }

    public function store(Request $request, LearningUnit $unit): RedirectResponse {
        [$enrollment] = $this->context($unit);

        // Nur wer den signierten QR-Link geöffnet hat, kann bestätigen: der
        // Nachweis gilt einmal und nur in dieser Sitzung (signed-1).
        $key = self::NONCE_KEY . '.' . $unit->getKey();
        $expected = (string) $request->session()->pull($key, '');
        $given = (string) $request->input('checkin_nonce', '');
        if ($expected === '' || ! hash_equals($expected, $given)) {
            return back()->withErrors(['checkin_nonce' => (string) __('learning.errors.checkin_link_required')]);
        }

        try {
            $this->events->checkIn($enrollment, $unit, $this->actor());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('learning.my.show', $enrollment->sqid)
            ->with('success', __('learning.flash.checked_in'));
    }

    /**
     * Eigene Einschreibung zum Termin — fremde gibt es nicht, und ohne
     * Einschreibung ist der Link wertlos.
     *
     * @return array{0: LearningEnrollment, 1: \App\Models\Event}
     */
    private function context(LearningUnit $unit): array {
        abort_unless($unit->kind === LearningUnitKind::Event, 404);

        $event = $unit->event;

        abort_if($event === null, 404);

        $enrollment = LearningEnrollment::query()
            ->where('user_id', $this->actor()->id)
            ->where('learning_course_id', $unit->learning_course_id)
            ->first();

        abort_if($enrollment === null, 404);

        return [$enrollment, $event];
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
