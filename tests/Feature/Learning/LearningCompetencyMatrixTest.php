<?php

/*
 * Filename     : LearningCompetencyMatrixTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\User\UserRole;
use App\Models\Learning\{Competency, CompetencyRequirement, LearningCourse, UserCompetency};
use App\Models\Platform\{Organization, User};
use App\Services\Learning\LearningCourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kompetenzmatrix-Oberfläche (Vollscan 2026-09-15, `C3-03` / `MVP-798`):
 * Dienst und Tabellen waren gebaut, aber ohne Route, Seite oder Kursfeld —
 * weder Einschätzung noch Soll-Stufe waren erfassbar.
 */
class LearningCompetencyMatrixTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_manager_builds_catalog_assessment_and_required_level_through_the_ui(): void {
        $manager = $this->manager();
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id, 'name' => 'Petra Prüfling']);

        $this->actingAs($manager)
            ->post(route('learning.competencies.store'), ['code' => 'LEITER', 'name' => 'Leitern und Tritte', 'max_level' => 4])
            ->assertRedirect(route('learning.competencies.index'));
        $competency = Competency::query()->where('code', 'LEITER')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('learning.competencies.assess'), ['user_id' => $learner->sqid, 'competency_id' => $competency->sqid, 'level' => 1])
            ->assertRedirect(route('learning.competencies.index'));
        $this->actingAs($manager)
            ->post(route('learning.competencies.requirements.store'), ['competency_id' => $competency->sqid, 'role' => UserRole::Aussendienst->value, 'required_level' => 3])
            ->assertRedirect(route('learning.competencies.index'));

        $assessment = UserCompetency::query()->where('user_id', $learner->id)->firstOrFail();
        $this->assertSame(1, $assessment->level);
        $this->assertSame('assessment', $assessment->source);
        $this->assertSame($manager->id, $assessment->assessed_by_user_id);

        // Lücke 1 von 3 wird in der Matrix sichtbar.
        $this->actingAs($manager)
            ->get(route('learning.competencies.index'))
            ->assertOk()
            ->assertSee('Leitern und Tritte')
            ->assertSee('Petra Prüfling')
            ->assertSee('1 / 3');

        // Erneutes Festlegen ändert die Stufe, statt am Unique-Index zu scheitern.
        $this->actingAs($manager)
            ->post(route('learning.competencies.requirements.store'), ['competency_id' => $competency->sqid, 'role' => UserRole::Aussendienst->value, 'required_level' => 2])
            ->assertRedirect();
        $this->assertSame(2, CompetencyRequirement::query()->sole()->required_level);
    }

    public function test_foreign_competency_cannot_be_assessed(): void {
        $foreign = Competency::query()->create([
            'organization_id' => Organization::factory()->create()->id,
            'code' => 'FREMD',
            'name' => 'Fremde Kompetenz',
            'max_level' => 4,
            'is_active' => true,
        ]);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->manager())
            ->post(route('learning.competencies.assess'), ['user_id' => $learner->sqid, 'competency_id' => $foreign->id, 'level' => 2])
            ->assertSessionHasErrors('competency_id');

        $this->assertSame(0, UserCompetency::query()->withoutGlobalScopes()->count());
    }

    public function test_course_dialog_links_a_competency(): void {
        $competency = Competency::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'ERSTE_HILFE',
            'name' => 'Erste Hilfe',
            'max_level' => 4,
            'is_active' => true,
        ]);
        $course = app(LearningCourseService::class)->createCourse($this->organization, null, ['title' => 'Ersthelfer']);

        $this->actingAs($this->manager())
            ->get(route('learning.courses.edit', $course))
            ->assertOk()
            ->assertSee('name="competency_id"', false);

        $this->actingAs($this->manager())
            ->put(route('learning.courses.update', $course), [
                'title' => 'Ersthelfer',
                'access_kind' => 'enrolled',
                'time_policy' => $course->time_policy->value,
                'instruction_suitability' => $course->instruction_suitability->value,
                'competency_id' => $competency->sqid,
                'competency_level' => 2,
            ])
            ->assertRedirect();

        $course = LearningCourse::query()->findOrFail($course->id);
        $this->assertSame($competency->id, $course->competency_id);
        $this->assertSame(2, $course->competency_level);
    }

    public function test_without_learning_management_the_matrix_is_forbidden(): void {
        $outsider = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($outsider)
            ->get(route('learning.competencies.index'))
            ->assertForbidden();
    }

    private function manager(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }
}
