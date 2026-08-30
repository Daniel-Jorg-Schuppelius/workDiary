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
    private const SESSION_KEY = 'learning.external_enrollment_id';

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

        $enrollment->load(['course.units.section', 'progress', 'externalParticipant']);

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
}
