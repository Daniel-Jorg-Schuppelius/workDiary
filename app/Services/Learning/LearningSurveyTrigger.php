<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningSurveyTrigger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Mail\SurveyInvitationMail;
use App\Models\Learning\LearningEnrollment;
use App\Models\Survey\Survey;
use App\Services\Survey\SurveyService;
use Illuminate\Support\Facades\{Log, Mail};
use RuntimeException;

/**
 * Kursfeedback nach Abschluss (Feature 149, MVP-747).
 *
 * Nutzt die vorhandene Umfrage-Engine (Feature 090) mitsamt Anonymität und
 * **Ermüdungsschutz** — ein Deckel, der verhindert, dass dieselbe Person
 * nach jedem Kurs eine Mail bekommt. Ein abgelehnter Versand ist deshalb
 * kein Fehler, sondern der Deckel bei der Arbeit.
 *
 * Externe Lernende ohne Mailadresse bekommen keine Einladung — eine
 * Umfrage ohne Rückkanal wäre sinnlos.
 */
class LearningSurveyTrigger {
    public function __construct(
        private readonly SurveyService $surveys,
    ) {}

    public function onCourseCompleted(LearningEnrollment $enrollment): void {
        $email = trim((string) ($enrollment->user->email ?? $enrollment->externalParticipant->email ?? ''));

        if ($email === '') {
            return;
        }

        $surveys = Survey::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $enrollment->organization_id)
            ->where('active', true)
            ->where('trigger_on_course_completion', true)
            ->get();

        foreach ($surveys as $survey) {
            try {
                $issued = $this->surveys->invite($survey, $email, null, 'learning');
                Mail::to($email)->send(new SurveyInvitationMail($survey, $issued['token']));
            } catch (RuntimeException) {
                // Opt-out/Ermüdungsschutz: bewusst still — der Deckel ist
                // Pflichtbestandteil, kein Fehler.
                continue;
            } catch (\Throwable $e) {
                Log::warning('Kursfeedback-Einladung fehlgeschlagen.', [
                    'survey_id' => $survey->id,
                    'enrollment_id' => $enrollment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
