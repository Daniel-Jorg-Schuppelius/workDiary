<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningReleaseRulesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Enums\Event\EventType;
use App\Enums\Learning\LearningUnitKind;
use App\Models\Calendar\Event;
use App\Models\Learning\{LearningCourse, LearningUnit};
use App\Models\Platform\User;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Freischaltung nach Vorgängereinheit und ab Präsenztermin (MVP-1268). */
final class LearningReleaseRulesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Carbon::setTestNow('2026-09-01 08:00:00');
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{0: LearningCourse, 1: LearningUnit, 2: LearningUnit, 3: LearningUnit, 4: Event} */
    private function course(): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Hubarbeitsbühne']);
        $courses->addUnit($course, ['title' => 'Theorie', 'kind' => LearningUnitKind::Content->value]);
        $courses->addUnit($course, ['title' => 'Praxistag', 'kind' => LearningUnitKind::Event->value]);
        $courses->addUnit($course, ['title' => 'Nachbereitung', 'kind' => LearningUnitKind::Content->value]);
        [$theory, $practice, $followUp] = $course->refresh()->units()->orderBy('position')->get()->all();
        $event = Event::query()->create([
            'organization_id' => $this->organization->id, 'title' => 'Praxistag Bühne', 'event_type' => EventType::Training->value,
            'started_at' => Carbon::parse('2026-09-10 07:00:00'), 'ended_at' => Carbon::parse('2026-09-10 15:00:00'),
        ]);
        $practice->update(['event_id' => $event->id]);

        return [$course, $theory, $practice, $followUp, $event];
    }

    public function test_unit_opens_after_its_predecessor_and_from_the_classroom_day(): void {
        [$course, $theory, $practice, $followUp, $event] = $this->course();
        $followUp->update(['release_rule' => ['after_unit_id' => $theory->id, 'after_event_unit_id' => $practice->id]]);
        app(LearningCourseService::class)->release($course->refresh(), null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollments = app(LearningEnrollmentService::class);
        $enrollment = $enrollments->enroll($course->refresh(), $learner);
        $followUp->refresh();

        $this->assertSame('2026-09-10', $followUp->releaseDateFor($enrollment)?->toDateString());
        $this->assertFalse($followUp->isReleasedFor($enrollment));

        Carbon::setTestNow('2026-09-10 08:00:00');
        // Der Tag ist da, aber die Theorie fehlt noch.
        $this->assertFalse($followUp->isReleasedFor($enrollment));
        $this->assertSame(__('learning.badge.after_unit', ['unit' => 'Theorie']), $followUp->lockLabelFor($enrollment));
        try {
            $enrollments->completeUnit($enrollment, $followUp);
            $this->fail('Ohne Vorgänger darf die Einheit nicht abschließbar sein.');
        } catch (ValidationException $exception) {
            $this->assertSame(__('learning.errors.unit_locked_by_predecessor', ['unit' => 'Theorie']), $exception->errors()['unit'][0]);
        }

        $enrollments->completeUnit($enrollment, $theory);
        $this->assertTrue($followUp->isReleasedFor($enrollment->refresh()));

        // Verschiebt sich der Termin, wandert die Freigabe mit.
        $event->update(['started_at' => Carbon::parse('2026-09-12 07:00:00'), 'ended_at' => Carbon::parse('2026-09-12 15:00:00')]);
        $this->assertSame('2026-09-12', $followUp->refresh()->releaseDateFor($enrollment)?->toDateString());
        $this->assertSame(__('learning.badge.available_from', ['date' => '12.09.2026']), $followUp->lockLabelFor($enrollment));
    }

    public function test_editor_stores_predecessor_and_event_and_rejects_foreign_units(): void {
        [$course, $theory, $practice, $followUp] = $this->course();
        [, $foreign] = $this->course();
        $author = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($author)->get(route('learning.courses.units.edit', [$course, $followUp]))
            ->assertOk()
            ->assertSee(__('learning.field.release_after_unit'))
            ->assertSee('value="' . $practice->sqid . '"', false);

        $this->actingAs($author)->put(route('learning.courses.units.update', [$course, $followUp]), [
            'title' => 'Nachbereitung', 'release_after_unit' => $theory->sqid, 'release_after_event' => $practice->sqid,
        ])->assertSessionHasNoErrors();
        $this->assertSame(['after_unit_id' => $theory->id, 'after_event_unit_id' => $practice->id], $followUp->refresh()->release_rule);

        $this->actingAs($author)->put(route('learning.courses.units.update', [$course, $followUp]), [
            'title' => 'Nachbereitung', 'release_after_unit' => $foreign->sqid,
        ])->assertSessionHasErrors('release_after_unit');
        $this->actingAs($author)->put(route('learning.courses.units.update', [$course, $followUp]), [
            'title' => 'Nachbereitung', 'release_after_event' => $theory->sqid,
        ])->assertSessionHasErrors('release_after_event');
    }
}
