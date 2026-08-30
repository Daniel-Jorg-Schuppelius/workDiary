<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningPortabilityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningBlockKind, LearningCourseStatus, LearningQuestionKind, LearningUnitKind};
use App\Models\Learning\{LearningCourse, LearningQuestion, LearningQuiz};
use App\Models\User;
use App\Services\Learning\{LearningContentService, LearningCoursePortabilityService, LearningCourseService, LearningEnrollmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kurs-Export und -Import (Feature 149, MVP-748).
 *
 * Kein Lock-in: ein Kurs muss mitnehmbar sein. Aber **Nachweise und
 * Personendaten bleiben zu Hause** — ein Kurs ist Lehrmaterial.
 */
class LearningPortabilityTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function service(): LearningCoursePortabilityService {
        return app(LearningCoursePortabilityService::class);
    }

    private function fullCourse(): LearningCourse {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, [
            'title' => 'Brandschutz kompakt',
            'objectives' => 'Fluchtwege kennen',
            'validity_months' => 12,
        ]);
        $section = $courses->addSection($course, ['title' => 'Grundlagen']);
        $courses->addUnit($course, ['title' => 'Fluchtwege', 'section' => $section, 'points' => 5]);
        $unit = $course->refresh()->units()->firstOrFail();
        app(LearningContentService::class)->appendBlock($unit, LearningBlockKind::Text, [
            'text' => 'Fluchtwege sind freizuhalten.',
        ]);

        $courses->addUnit($course->refresh(), ['title' => 'Abschlussprüfung', 'kind' => LearningUnitKind::Quiz->value]);
        $quizUnit = $course->refresh()->units()->where('kind', LearningUnitKind::Quiz)->firstOrFail();
        $quiz = LearningQuiz::query()->create([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $quizUnit->id,
            'title' => 'Abschlussprüfung',
            'pass_percent' => 60,
        ]);
        $question = LearningQuestion::query()->create([
            'organization_id' => $this->organization->id,
            'learning_quiz_id' => $quiz->id,
            'kind' => LearningQuestionKind::Single->value,
            'prompt' => 'Was gehört in den Fluchtweg?',
            'points' => 2,
            'position' => 1,
        ]);
        $question->options()->create([
            'organization_id' => $this->organization->id,
            'label' => 'Nichts',
            'is_correct' => true,
            'position' => 1,
        ]);
        $question->options()->create([
            'organization_id' => $this->organization->id,
            'label' => 'Kisten',
            'position' => 2,
        ]);

        return $course->refresh();
    }

    public function test_export_enthaelt_struktur_inhalt_und_fragen(): void {
        $course = $this->fullCourse();

        $payload = $this->service()->export($course);

        $this->assertSame('workdiary.learning.course', $payload['format']);
        $this->assertSame('Brandschutz kompakt', $payload['course']['title']);
        $this->assertCount(1, $payload['sections']);
        $this->assertCount(2, $payload['units']);
        $this->assertSame('Fluchtwege sind freizuhalten.', $payload['units'][0]['blocks'][0]['text']);
        $this->assertCount(1, $payload['units'][1]['quiz']['questions']);
    }

    public function test_export_enthaelt_keine_nachweise(): void {
        $course = $this->fullCourse();
        app(LearningCourseService::class)->release($course, null);
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        app(LearningEnrollmentService::class)->enroll($course->refresh(), $user);

        $json = json_encode($this->service()->export($course->refresh())) ?: '';

        $this->assertStringNotContainsString('enrollment', $json);
        $this->assertStringNotContainsString($user->name, $json);
        $this->assertStringNotContainsString($user->email, $json);
    }

    public function test_import_legt_den_kurs_als_entwurf_an(): void {
        // Importiert wird in die eigene Organisation — das ist der reale
        // Fall. Ein Import in eine fremde Organisation wäre am
        // Mandanten-Scope ohnehin nicht sichtbar.
        $payload = $this->service()->export($this->fullCourse());

        $imported = $this->service()->import($this->organization, $payload);

        $this->assertSame(LearningCourseStatus::Draft, $imported->status, 'Ein Import ist nie automatisch freigegeben.');
        $this->assertSame($this->organization->id, $imported->organization_id);
        $this->assertNotSame('brandschutz-kompakt', $imported->code, 'Der Kurscode wird neu vergeben.');
        $this->assertSame(2, $imported->units()->count());
        $this->assertSame(1, $imported->sections()->count());

        $quizUnit = $imported->units()->where('kind', LearningUnitKind::Quiz)->firstOrFail();
        $this->assertSame(1, $quizUnit->quiz?->questions()->count());
        $this->assertSame(2, $quizUnit->quiz?->questions()->first()?->options()->count());
    }

    public function test_import_prueft_das_format(): void {
        $this->expectException(ValidationException::class);
        $this->service()->import($this->organization, ['format' => 'etwas-anderes']);
    }

    public function test_import_lehnt_neuere_formatversionen_ab(): void {
        $this->expectException(ValidationException::class);
        $this->service()->import($this->organization, [
            'format' => 'workdiary.learning.course',
            'format_version' => LearningCoursePortabilityService::FORMAT_VERSION + 1,
            'course' => ['title' => 'Zukunft'],
        ]);
    }

    public function test_export_ueber_die_route_liefert_eine_datei(): void {
        $course = $this->fullCourse();
        $author = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($author)
            ->get(route('learning.courses.export', $course))
            ->assertOk()
            ->assertHeader('content-type', 'application/json');
    }
}
