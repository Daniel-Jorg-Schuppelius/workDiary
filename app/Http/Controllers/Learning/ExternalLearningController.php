<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalLearningController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\Learning\LearningProgressStatus;
use App\Http\Controllers\Controller;
use App\Models\Learning\{LearningEnrollment, LearningUnit};
use App\Services\Learning\{LearningAccessService, LearningEnrollmentService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Lernzugang ohne Benutzerkonto (Feature 149, MVP-742).
 *
 * Anwendungsfall: die Sicherheitsunterweisung, die ein Subunternehmer vor
 * dem ersten Baustellentag absolviert.
 *
 * Der Token steht **nur im Einstiegslink**; danach trägt die Session den
 * Zustand — so landet er nicht in Browserverlauf, Referrer oder
 * Server-Logs jeder Folgeseite. Fehlerfälle antworten **neutral**: ob ein
 * Token unbekannt, abgelaufen oder widerrufen ist, verrät die Seite nicht.
 */
class ExternalLearningController extends Controller {
    /** Session-Schlüssel der freigeschalteten Einschreibung. */
    /** Auch der LTI-Start legt die Einschreibung hier ab. */
    public const SESSION_KEY = 'learning.external_enrollment_id';

    public function __construct(
        private readonly LearningAccessService $access,
        private readonly LearningEnrollmentService $enrollments,
    ) {}

    /** Einstieg über den Link: Token einlösen und in die Session legen. */
    public function enter(Request $request, string $token): RedirectResponse {
        $enrollment = $this->access->resolve($token);

        if ($enrollment === null) {
            return redirect()
                ->route('learning.external.denied')
                ->with('error', __('learning.external.link_invalid'));
        }

        $request->session()->put(self::SESSION_KEY, $enrollment->id);
        $request->session()->regenerate();

        return redirect()->route('learning.external.show');
    }

    public function denied(): View {
        return view('learning.external.denied');
    }

    public function show(Request $request): View {
        $enrollment = $this->currentEnrollment($request);

        $enrollment->load(['course.units.section', 'externalParticipant']);
        // Mindestverweildauer (MVP-788): das erste Öffnen zählt ab jetzt.
        $this->enrollments->markSeen($enrollment, $enrollment->course->units ?? []);
        $enrollment->load('progress');

        return view('learning.external.show', [
            'enrollment' => $enrollment,
            'course' => $enrollment->course,
            'completedUnitIds' => $enrollment->progress
                ->where('status', LearningProgressStatus::Completed)
                ->pluck('learning_unit_id')
                ->all(),
        ]);
    }

    public function completeUnit(Request $request, LearningUnit $unit): RedirectResponse {
        $enrollment = $this->currentEnrollment($request);
        abort_unless($unit->learning_course_id === $enrollment->learning_course_id, 404);
        // Wie in „Meine Schulungen": Einheiten mit eigener Ergebnisquelle hakt niemand von Hand ab.
        abort_if($unit->reportsOwnResult(), 403);

        $this->enrollments->completeUnit($enrollment, $unit);

        return redirect()
            ->route('learning.external.show')
            ->with('success', __('learning.flash.unit_completed'));
    }

    /**
     * Die Einschreibung aus der Session — und nur diese. Ohne gültige
     * Session gibt es keinen Zugang, auch nicht über geratene IDs.
     */
    private function currentEnrollment(Request $request): LearningEnrollment {
        $id = $request->session()->get(self::SESSION_KEY);

        abort_if(! is_int($id) && ! is_numeric($id), 403);

        $enrollment = LearningEnrollment::query()
            ->whereKey((int) $id)
            ->whereNotNull('external_participant_id')
            ->first();

        abort_if($enrollment === null, 403);

        // Der Zugang wird bei JEDEM Request neu geprüft: Widerruf, Ablauf der
        // Einschreibung oder des Gastzugangs endeten sonst erst mit der Sitzung
        // (Sicherheitsaudit 2026-09-17, learning-ext-1).
        if (! $this->accessStillValid($enrollment)) {
            $request->session()->forget(self::SESSION_KEY);
            abort(403);
        }

        // Ohne angemeldete Person ist keine Organisation gebunden — der
        // Mandanten-Scope liefe leer und nachgelagerte Schreibvorgänge
        // (Fortschritt, Zertifikat) hätten keinen Mandanten. Deshalb wird er
        // hier aus der Einschreibung gesetzt, nicht aus einer Eingabe.
        $organization = $enrollment->organization;
        if ($organization !== null && ! app()->bound('currentOrganization')) {
            app()->instance('currentOrganization', $organization);
        }

        return $enrollment;
    }

    /**
     * Gilt der Gastzugang noch? Verlangt eine offene, nicht abgelaufene
     * Einschreibung, einen nicht widerrufenen Gast und mindestens einen
     * gültigen Zugangslink.
     */
    private function accessStillValid(LearningEnrollment $enrollment): bool {
        if ($enrollment->isAccessExpired()) {
            return false;
        }

        $participant = $enrollment->externalParticipant;
        if ($participant === null || ! $participant->isUsable()) {
            return false;
        }

        return \App\Models\Learning\LearningAccessToken::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();
    }
}
