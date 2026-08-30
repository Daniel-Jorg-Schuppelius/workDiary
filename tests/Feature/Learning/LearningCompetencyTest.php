<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCompetencyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Models\Learning\{Competency, CompetencyRequirement, LearningCourse, UserCompetency};
use App\Models\User;
use App\Services\Learning\{LearningCompetencyService, LearningCourseService, LearningEnrollmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kompetenzen und Lückenanalyse (Feature 149, MVP-745).
 *
 * Die Kompetenz ist eine Einschätzung mit Stufe, kein Nachweis mit
 * Sperrwirkung — sie zeigt Lücken, sie sperrt nichts.
 */
class LearningCompetencyTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): LearningCompetencyService {
        return app(LearningCompetencyService::class);
    }

    private function competency(array $attributes = []): Competency {
        return Competency::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'code' => 'psaga',
            'name' => 'PSA gegen Absturz',
            'max_level' => 4,
            'is_active' => true,
        ], $attributes));
    }

    private function courseGranting(Competency $competency, int $level, ?int $validityMonths = null): LearningCourse {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, [
            'title' => 'PSAgA-Schulung',
            'competency_id' => $competency->id,
            'competency_level' => $level,
            'validity_months' => $validityMonths,
        ]);
        $courses->addUnit($course, ['title' => 'Praxis']);
        $courses->release($course->refresh(), null);

        return $course->refresh();
    }

    private function completeCourse(LearningCourse $course, User $user): void {
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $user);
        app(LearningEnrollmentService::class)->completeUnit($enrollment, $course->units()->firstOrFail());
    }

    public function test_kursabschluss_belegt_die_kompetenzstufe(): void {
        $competency = $this->competency();
        $course = $this->courseGranting($competency, 3, validityMonths: 24);
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->completeCourse($course, $user);

        $granted = UserCompetency::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(3, $granted->level);
        $this->assertSame('course', $granted->source);
        $this->assertSame(Carbon::today()->addMonths(24)->toDateString(), $granted->valid_until?->toDateString());
    }

    public function test_stufe_wird_auf_das_maximum_begrenzt(): void {
        $competency = $this->competency(['max_level' => 2]);
        $course = $this->courseGranting($competency, 9);
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->completeCourse($course, $user);

        $this->assertSame(2, UserCompetency::query()->where('user_id', $user->id)->firstOrFail()->level);
    }

    public function test_wiederholung_senkt_die_erreichte_stufe_nicht(): void {
        $competency = $this->competency();
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $this->service()->assess($user, $competency, 4, User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]));

        $course = $this->courseGranting($competency, 1);
        $this->completeCourse($course, $user);

        $this->assertSame(4, UserCompetency::query()->where('user_id', $user->id)->firstOrFail()->level, 'Wiederholung ist keine Rückstufung.');
    }

    public function test_einschaetzung_darf_senken(): void {
        $competency = $this->competency();
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        $this->service()->assess($user, $competency, 4, $lead);
        $this->service()->assess($user, $competency, 2, $lead, 'Praxis fehlt');

        $entry = UserCompetency::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(2, $entry->level);
        $this->assertSame('assessment', $entry->source);
        $this->assertSame('Praxis fehlt', $entry->note);
    }

    public function test_luecke_wird_erkannt(): void {
        $competency = $this->competency();
        CompetencyRequirement::query()->create([
            'organization_id' => $this->organization->id,
            'competency_id' => $competency->id,
            'subject_kind' => 'role',
            'subject_key' => 'aussendienst',
            'required_level' => 3,
            'is_active' => true,
        ]);
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $gaps = $this->service()->gapsFor($user, ['aussendienst']);
        $this->assertCount(1, $gaps);
        $this->assertSame(3, $gaps[0]['gap'], 'Ohne Kompetenz ist die ganze Stufe die Lücke.');

        $this->completeCourse($this->courseGranting($competency, 3), $user);

        $this->assertSame([], $this->service()->gapsFor($user->refresh(), ['aussendienst']));
    }

    public function test_abgelaufene_kompetenz_zaehlt_nicht_als_erfuellt(): void {
        $competency = $this->competency();
        CompetencyRequirement::query()->create([
            'organization_id' => $this->organization->id,
            'competency_id' => $competency->id,
            'subject_kind' => 'role',
            'subject_key' => 'aussendienst',
            'required_level' => 2,
            'is_active' => true,
        ]);
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        UserCompetency::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'competency_id' => $competency->id,
            'level' => 3,
            'source' => 'assessment',
            'assessed_on' => Carbon::today()->subYears(2)->toDateString(),
            'valid_until' => Carbon::today()->subDay()->toDateString(),
        ]);

        $gaps = $this->service()->gapsFor($user, ['aussendienst']);

        $this->assertCount(1, $gaps);
        $this->assertSame(0, $gaps[0]['actual'], 'Abgelaufen zählt wie nicht vorhanden.');
    }

    public function test_matrix_zeigt_stufen_je_person(): void {
        $competency = $this->competency();
        $a = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $b = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->service()->assess($a, $competency, 2, $lead);

        $matrix = $this->service()->matrixFor($this->organization, collect([$a, $b]));

        $this->assertCount(1, $matrix['competencies']);
        $this->assertSame(2, $matrix['rows'][0]['levels'][$competency->id] ?? null);
        $this->assertSame([], $matrix['rows'][1]['levels']);
    }
}
