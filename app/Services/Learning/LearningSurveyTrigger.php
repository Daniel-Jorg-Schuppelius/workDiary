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

use App\Models\Learning\LearningEnrollment;
use App\Models\Survey\SurveyInvitation;
use App\Services\Survey\SurveyService;

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

        // Kursbezug für den Sternewert (MVP-794) — nur der Kurs, keine Person.
        $this->surveys->inviteByTrigger((int) $enrollment->organization_id, 'trigger_on_course_completion', $email, null, 'learning',
            function (SurveyInvitation $invitation) use ($enrollment): void {
                $invitation->forceFill(['learning_course_id' => $enrollment->learning_course_id])->save();
            });
    }
}
