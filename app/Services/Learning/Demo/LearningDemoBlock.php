<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning\Demo;

use App\Models\Platform\{Organization, User};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};

/** Lernplattform-Vorführung. Aus dem Demo-Showcase gelöst (Welle 4.1); Aufräumen übernimmt der generische Demo-Reset. */
final class LearningDemoBlock implements DemoBlock {
    private DemoSeedContext $context;

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $this->context = $context;
        $actor = $context->users->first();

        return [
            'learning' => $this->seedLearning($context->organization, $actor),
        ];
    }

    public function purge(Organization $organization): void {}

    private function moduleActive(string $code): bool {
        return $this->context->moduleActive($code);
    }

    /**
     * Lernplattform-Demo (Feature 149, MVP-748): ein freigegebener Kurs mit
     * Inhalt, Prüfung und einer laufenden Einschreibung.
     *
     * **Bewusst mit Zertifikat und Unterweisungsnachweis**, denn genau der
     * Rückfluss ist der Produktwert gegenüber einem reinen Kurs-Werkzeug:
     * der Abschluss landet im Arbeitsschutz-Register, nicht nur im LMS.
     */
    private function seedLearning(Organization $organization, ?User $actor): int {
        if (! $this->moduleActive('module.lms')) {
            return 0;
        }
        if ($actor === null) {
            return 0;
        }

        try {
            $courses = app(\App\Services\Learning\LearningCourseService::class);

            $course = $courses->createCourse($organization, $actor, [
                'title' => 'Brandschutzunterweisung',
                'subtitle' => 'Jährliche Pflichtunterweisung nach DGUV Vorschrift 1',
                'objectives' => "Fluchtwege kennen\nFeuerlöscher richtig einsetzen\nVerhalten im Brandfall",
                'time_policy' => \App\Enums\Learning\LearningTimePolicy::WorkTimeRequired->value,
                'instruction_suitability' => \App\Enums\Learning\LearningInstructionSuitability::Supplementary->value,
                'validity_months' => 12,
                'certificate_enabled' => true,
                'creates_instruction_proof' => true,
                'duration_minutes' => 25,
            ]);

            $content = app(\App\Services\Learning\LearningContentService::class);

            $intro = $courses->addUnit($course, ['title' => 'Grundlagen', 'duration_minutes' => 10]);
            $content->appendBlock($intro, \App\Enums\Learning\LearningBlockKind::Heading, ['text' => 'Warum Brandschutz']);
            $content->appendBlock($intro, \App\Enums\Learning\LearningBlockKind::Text, [
                'text' => 'Die meisten Brände entstehen durch Elektrogeräte und Nachlässigkeit. '
                    . 'Wer die Fluchtwege kennt, gewinnt im Ernstfall die entscheidenden Sekunden.',
            ]);
            $content->appendBlock($intro, \App\Enums\Learning\LearningBlockKind::Callout, [
                'text' => 'Fluchtwege sind immer freizuhalten — auch „nur kurz" abgestellte Kisten sind ein Verstoß.',
                'tone' => 'warning',
            ]);
            $content->appendBlock($intro, \App\Enums\Learning\LearningBlockKind::Checklist, [
                'items' => "Fluchtwegplan am Standort gelesen\nSammelplatz bekannt\nNächsten Feuerlöscher gefunden",
            ]);

            $examUnit = $courses->addUnit($course, [
                'title' => 'Abschlussprüfung',
                'kind' => \App\Enums\Learning\LearningUnitKind::Quiz->value,
                'duration_minutes' => 10,
            ]);

            $quiz = \App\Models\Learning\LearningQuiz::query()->create([
                'organization_id' => $organization->id,
                'learning_unit_id' => $examUnit->id,
                'title' => 'Abschlussprüfung',
                'pass_percent' => 70,
                'max_attempts' => 3,
            ]);

            $catalog = app(\App\Services\Learning\LearningQuestionCatalogService::class);
            $question = \App\Models\Learning\LearningQuestion::query()->create([
                'organization_id' => $organization->id,
                'learning_question_category_id' => $catalog->categoryByName($organization, 'Brandschutz')->id,
                'kind' => \App\Enums\Learning\LearningQuestionKind::Single->value,
                'prompt' => 'Was tun Sie zuerst, wenn Sie einen Entstehungsbrand entdecken?',
                'explanation' => 'Menschenrettung geht immer vor Sachwerten.',
                'points' => 5,
                'position' => 1,
            ]);

            foreach ([['Personen warnen und in Sicherheit bringen', true], ['Erst den Laptop retten', false], ['Fenster öffnen', false]] as $index => [$label, $correct]) {
                $question->options()->create([
                    'organization_id' => $organization->id,
                    'label' => $label,
                    'is_correct' => $correct,
                    'position' => $index + 1,
                ]);
            }
            $catalog->attach($quiz, $question);

            $courses->release($course->refresh(), $actor);

            // Eine laufende Einschreibung, damit „Meine Schulungen" nicht
            // leer wirkt.
            app(\App\Services\Learning\LearningEnrollmentService::class)->enroll($course->refresh(), $actor);

            return 1;
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }
}
