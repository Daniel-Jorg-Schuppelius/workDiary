<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningTrainerScopeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Enums\User\Permission;
use App\Models\Learning\{LearningAssignment, LearningCourse};
use App\Models\Platform\User;
use App\Services\Learning\{LearningAssignmentService, LearningCourseService, LearningEnrollmentService, LearningReportService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Trainer-Scoping (Feature 149, MVP-786): Org-Schalter aus ⇒ alles wie
 * bisher; an ⇒ Autoren und Bewertende sehen nur Kurse, die ihnen gehören
 * oder an denen sie Trainer sind — die Verwaltung weiterhin alles.
 */
class LearningTrainerScopeTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function enableScoping(): void {
        $settings = (array) ($this->organization->settings ?? []);
        data_set($settings, 'learning.scope_to_courses', true);
        $this->organization->update(['settings' => $settings]);
    }

    /** Autorin/Bewerterin ohne Verwaltungsrecht — die Teamleitung hätte `learning.manage`. */
    private function author(): User {
        return $this->actorIn($this->organization, [Permission::LearningViewAny, Permission::LearningAuthor, Permission::LearningGrade]);
    }

    private function manager(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    private function course(string $title, ?User $owner = null): LearningCourse {
        $course = app(LearningCourseService::class)->createCourse($this->organization, $owner, ['title' => $title]);
        app(LearningCourseService::class)->addUnit($course, ['title' => 'Einheit']);

        return $course->refresh();
    }

    public function test_ohne_schalter_bleibt_alles_sichtbar(): void {
        $author = $this->author();
        $foreign = $this->course('Fremd', $this->manager());

        $this->actingAs($author)->get(route('learning.courses.show', $foreign))->assertOk();
        $this->actingAs($author)->get(route('learning.courses.index'))->assertOk()->assertSee('Fremd');
    }

    public function test_mit_schalter_sieht_die_autorin_nur_eigene_kurse(): void {
        $this->enableScoping();
        $author = $this->author();
        $own = $this->course('Eigen', $author);
        $assigned = $this->course('Zugeordnet', $this->manager());
        $assigned->trainers()->attach($author->id, ['organization_id' => $this->organization->id, 'role' => 'trainer']);
        $foreign = $this->course('Fremd', $this->manager());

        $this->actingAs($author)
            ->get(route('learning.courses.index'))
            ->assertOk()
            ->assertSee('Eigen')
            ->assertSee('Zugeordnet')
            ->assertDontSee('Fremd');

        $this->actingAs($author)->get(route('learning.courses.show', $own))->assertOk();
        $this->actingAs($author)->get(route('learning.courses.show', $assigned))->assertOk();
        $this->actingAs($author)->get(route('learning.courses.show', $foreign))->assertForbidden();
        $this->actingAs($author)->get(route('learning.courses.enrollments.index', $foreign))->assertForbidden();

        $this->assertSame(
            [$own->id, $assigned->id],
            LearningCourse::query()->visibleTo($author)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_verwaltung_und_admin_sehen_weiterhin_alles(): void {
        $this->enableScoping();
        $foreign = $this->course('Fremd', $this->author());

        $this->actingAs($this->manager())->get(route('learning.courses.show', $foreign))->assertOk();
        $this->actingAs($this->orgAdmin())->get(route('learning.courses.show', $foreign))->assertOk();
    }

    public function test_bewertungsliste_enthaelt_nur_abgaben_eigener_kurse(): void {
        $this->enableScoping();
        $grader = $this->author();
        $courses = app(LearningCourseService::class);
        $own = $courses->createCourse($this->organization, $grader, ['title' => 'Eigen']);
        $foreign = $courses->createCourse($this->organization, $this->manager(), ['title' => 'Fremd']);
        $submissions = [];
        foreach ([$own, $foreign] as $course) {
            $courses->addUnit($course, ['title' => 'Bericht', 'kind' => LearningUnitKind::Assignment->value]);
            $unit = $course->refresh()->units()->firstOrFail();
            $assignment = LearningAssignment::query()->create([
                'organization_id' => $this->organization->id,
                'learning_unit_id' => $unit->id,
                'title' => 'Bericht ' . $course->title,
                'submission_kind' => 'text',
                'points' => 10,
                'pass_percent' => 50,
            ]);
            $courses->release($course->refresh(), null);
            $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
            $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);
            $submissions[$course->title] = app(LearningAssignmentService::class)->submit($enrollment, $assignment->refresh(), 'Text');
        }

        $this->actingAs($grader)
            ->get(route('learning.grading.index'))
            ->assertOk()
            ->assertSee('Bericht Eigen')
            ->assertDontSee('Bericht Fremd');

        $this->actingAs($grader)->get(route('learning.grading.submission', $submissions['Eigen']))->assertOk();
        $this->actingAs($grader)->get(route('learning.grading.submission', $submissions['Fremd']))->assertNotFound();

        $report = app(LearningReportService::class)->summary($this->organization, [$own->id]);
        $this->assertSame(1, $report['courses']);
        $this->assertSame(1, $report['enrollments']);
    }

    public function test_einstellungen_dialog_schaltet_die_trainer_sicht(): void {
        $manager = $this->manager();

        $this->actingAs($manager)
            ->get(route('learning.settings.edit'))
            ->assertOk()
            ->assertSee(__('learning.field.scope_to_courses'));

        $this->actingAs($manager)
            ->put(route('learning.settings.update'), [
                'scope_to_courses' => '1',
                'gamification_enabled' => '1',
                'leaderboard_enabled' => '1',
                'embed_hosts' => "www.youtube-nocookie.com\nPlayer.Vimeo.com",
            ])
            ->assertRedirect(route('learning.courses.index'));

        $settings = $this->organization->refresh()->settings['learning'];
        $this->assertTrue($settings['scope_to_courses']);
        $this->assertTrue($settings['gamification']['enabled']);
        $this->assertTrue($settings['gamification']['leaderboard']);
        $this->assertSame(['www.youtube-nocookie.com', 'player.vimeo.com'], $settings['embed_hosts']);

        $this->actingAs($this->author())->get(route('learning.settings.edit'))->assertForbidden();
    }

    public function test_trainer_zuordnen_und_entfernen(): void {
        $manager = $this->manager();
        $course = $this->course('Kurs', $manager);
        $trainer = $this->author();

        $this->actingAs($manager)
            ->post(route('learning.courses.trainers.store', $course), ['user_id' => $trainer->sqid, 'role' => 'grader'])
            ->assertRedirect(route('learning.courses.show', $course));
        $this->assertSame('grader', $course->trainers()->firstOrFail()->pivot->role);

        $this->actingAs($manager)
            ->delete(route('learning.courses.trainers.destroy', [$course, $trainer]))
            ->assertRedirect(route('learning.courses.show', $course));
        $this->assertSame(0, $course->trainers()->count());
    }
}
