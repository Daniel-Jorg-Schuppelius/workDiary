<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningGamificationUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Models\Learning\LearningCourse;
use App\Models\Platform\User;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Punkte, Abzeichen, Bestenliste (Feature 149, MVP-781): Org-Schalter UND
 * persönliches Opt-in — ohne beides bleibt alles unsichtbar (§ 87 BetrVG).
 */
class LearningGamificationUiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function enable(bool $leaderboard = true): void {
        $settings = (array) ($this->organization->settings ?? []);
        $settings['learning'] = array_merge((array) ($settings['learning'] ?? []), [
            'gamification' => ['enabled' => true, 'leaderboard' => $leaderboard],
        ]);
        $this->organization->update(['settings' => $settings]);
    }

    private function learner(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }

    private function completedCourse(User $user, int $points = 10): LearningCourse {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz kompakt']);
        $courses->addUnit($course, ['title' => 'Einführung', 'points' => $points]);
        $courses->release($course->refresh(), null);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $user);
        app(LearningEnrollmentService::class)->completeUnit($enrollment, $course->units()->firstOrFail());

        return $course->refresh();
    }

    public function test_ohne_org_schalter_bleibt_alles_unsichtbar(): void {
        $learner = $this->learner();
        $this->completedCourse($learner);

        $this->actingAs($learner)
            ->get(route('learning.my.index'))
            ->assertOk()
            ->assertDontSee(__('learning.badge.first_course'))
            ->assertDontSee(__('learning.action.leaderboard'));

        $this->actingAs($learner)->get(route('learning.my.leaderboard'))->assertNotFound();
    }

    public function test_punkte_und_abzeichen_erscheinen_mit_org_schalter(): void {
        $this->enable(leaderboard: false);
        $learner = $this->learner();
        $this->completedCourse($learner, 12);

        $this->actingAs($learner)
            ->get(route('learning.my.index'))
            ->assertOk()
            ->assertSee(__('learning.badge.first_course'))
            ->assertSee('12')
            ->assertDontSee(__('learning.action.leaderboard'));
    }

    public function test_bestenliste_zeigt_nur_personen_mit_opt_in(): void {
        $this->enable();
        $visible = $this->learner();
        $silent = $this->learner();
        $this->completedCourse($visible, 10);
        $this->completedCourse($silent, 30);

        $this->actingAs($visible)
            ->post(route('learning.my.leaderboard.opt-in'), ['opt_in' => '1'])
            ->assertRedirect(route('learning.my.leaderboard'));

        $this->assertTrue((bool) ($visible->refresh()->preferences['learning']['leaderboard_opt_in'] ?? false));

        $this->actingAs($visible)
            ->get(route('learning.my.leaderboard'))
            ->assertOk()
            ->assertSee($visible->name)
            ->assertDontSee($silent->name);

        $this->actingAs($visible)
            ->post(route('learning.my.leaderboard.opt-in'), ['opt_in' => '0'])
            ->assertRedirect();

        $this->assertFalse((bool) ($visible->refresh()->preferences['learning']['leaderboard_opt_in'] ?? false));
    }
}
