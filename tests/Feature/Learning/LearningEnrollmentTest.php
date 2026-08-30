<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningEnrollmentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningEnrollmentSource, LearningEnrollmentStatus, LearningProgressStatus};
use App\Models\{ExternalParticipant, User};
use App\Models\Learning\{LearningCourse, LearningEnrollment};
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Einschreibung und Fortschritt (Feature 149, MVP-737): Stoffstand je
 * Einschreibung, Abschluss erst bei allen Pflichteinheiten, „Meine
 * Schulungen" ohne Plan-Gate und ohne Fremdeinsicht.
 */
class LearningEnrollmentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function courses(): LearningCourseService {
        return app(LearningCourseService::class);
    }

    private function enrollments(): LearningEnrollmentService {
        return app(LearningEnrollmentService::class);
    }

    private function releasedCourse(int $units = 1, bool $secondOptional = false): LearningCourse {
        $course = $this->courses()->createCourse($this->organization, null, ['title' => 'Brandschutz kompakt']);
        for ($i = 1; $i <= $units; $i++) {
            $this->courses()->addUnit($course, ['title' => 'Einheit ' . $i]);
        }
        if ($secondOptional) {
            $this->courses()->addUnit($course, ['title' => 'Kür', 'is_mandatory' => false]);
        }
        $this->courses()->release($course->refresh(), null);

        return $course->refresh();
    }

    private function learner(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }

    public function test_einschreibung_haelt_den_stoffstand_fest(): void {
        $course = $this->releasedCourse();
        $enrollment = $this->enrollments()->enroll($course, $this->learner());

        $this->assertSame(LearningEnrollmentStatus::Assigned, $enrollment->status);
        $this->assertSame($course->currentVersion()?->id, $enrollment->learning_course_version_id);
        $this->assertSame(1, $enrollment->events()->count());
    }

    public function test_entwurf_nimmt_keine_einschreibung_an(): void {
        $course = $this->courses()->createCourse($this->organization, null, ['title' => 'Entwurf']);

        $this->expectException(ValidationException::class);
        $this->enrollments()->enroll($course, $this->learner());
    }

    public function test_doppelte_zuweisung_liefert_dieselbe_einschreibung(): void {
        $course = $this->releasedCourse();
        $user = $this->learner();

        $first = $this->enrollments()->enroll($course, $user);
        $second = $this->enrollments()->enroll($course, $user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, LearningEnrollment::query()->count());
    }

    public function test_externe_lernende_ohne_konto_koennen_eingeschrieben_werden(): void {
        $course = $this->releasedCourse();
        $external = ExternalParticipant::factory()->create(['organization_id' => $this->organization->id]);

        $enrollment = $this->enrollments()->enroll($course, $external);

        $this->assertNull($enrollment->user_id);
        $this->assertSame($external->id, $enrollment->external_participant_id);
    }

    public function test_kurs_gilt_erst_nach_allen_pflichteinheiten_als_abgeschlossen(): void {
        $course = $this->releasedCourse(units: 2);
        $enrollment = $this->enrollments()->enroll($course, $this->learner());
        $units = $course->units()->orderBy('position')->get();

        $this->enrollments()->completeUnit($enrollment, $units[0]);
        $this->assertSame(LearningEnrollmentStatus::InProgress, $enrollment->refresh()->status);

        $this->enrollments()->completeUnit($enrollment, $units[1]);
        $this->assertSame(LearningEnrollmentStatus::Completed, $enrollment->refresh()->status);
        $this->assertNotNull($enrollment->completed_at);
    }

    public function test_optionale_einheit_blockiert_den_abschluss_nicht(): void {
        $course = $this->releasedCourse(units: 1, secondOptional: true);
        $enrollment = $this->enrollments()->enroll($course, $this->learner());
        $mandatory = $course->units()->where('is_mandatory', true)->firstOrFail();

        $this->enrollments()->completeUnit($enrollment, $mandatory);

        $this->assertSame(LearningEnrollmentStatus::Completed, $enrollment->refresh()->status);
    }

    public function test_einheit_eines_fremden_kurses_wird_abgewiesen(): void {
        $courseA = $this->releasedCourse();
        $courseB = $this->releasedCourse();
        $enrollment = $this->enrollments()->enroll($courseA, $this->learner());
        $foreignUnit = $courseB->units()->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->enrollments()->completeUnit($enrollment, $foreignUnit);
    }

    public function test_pflicht_einschreibung_kann_nicht_storniert_werden(): void {
        $course = $this->releasedCourse();
        $enrollment = $this->enrollments()->enroll($course, $this->learner(), [
            'source' => LearningEnrollmentSource::Requirement->value,
        ]);

        $this->expectException(ValidationException::class);
        $this->enrollments()->cancel($enrollment);
    }

    public function test_meine_schulungen_zeigt_nur_eigene_einschreibungen(): void {
        $course = $this->releasedCourse();
        $mine = $this->learner();
        $other = $this->learner();
        $this->enrollments()->enroll($course, $mine);
        $foreign = $this->enrollments()->enroll($course, $other);

        $this->actingAs($mine)
            ->get(route('learning.my.index'))
            ->assertOk()
            ->assertSee($course->title);

        $this->actingAs($mine)
            ->get(route('learning.my.show', $foreign))
            ->assertNotFound();
    }

    public function test_player_schliesst_eine_einheit_ab(): void {
        $course = $this->releasedCourse();
        $user = $this->learner();
        $enrollment = $this->enrollments()->enroll($course, $user);
        $unit = $course->units()->firstOrFail();

        $this->actingAs($user)
            ->post(route('learning.my.units.complete', [$enrollment, $unit]))
            ->assertRedirect(route('learning.my.show', $enrollment));

        $this->assertSame(
            LearningProgressStatus::Completed,
            $enrollment->progress()->firstOrFail()->status
        );
    }

    public function test_meine_schulungen_bleibt_im_freien_plan_erreichbar(): void {
        // Pflichtunterweisung darf nie an der Lizenzstufe scheitern.
        $course = $this->releasedCourse();
        $user = $this->learner();
        $this->enrollments()->enroll($course, $user);
        $this->organization->update(['plan' => \App\Models\Organization::PLAN_FREE]);

        $this->actingAs($user)
            ->get(route('learning.my.index'))
            ->assertOk();
    }
}
