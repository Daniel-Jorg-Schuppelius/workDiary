<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningGradeScaleSettingsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Models\Platform\User;
use App\Services\Learning\LearningGradebookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Notenschema je Organisation (MVP-1600): Pflege in den Lern-Einstellungen, gelesen vom Notenbuch. */
final class LearningGradeScaleSettingsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function manager(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    public function test_levels_are_stored_in_the_form_the_gradebook_reads(): void {
        $manager = $this->manager();
        $gradebook = app(LearningGradebookService::class);

        $this->actingAs($manager)->get(route('learning.settings.edit'))
            ->assertOk()
            ->assertSee(__('learning.field.grade_scale'))
            ->assertSee(__('learning.grade_scale.template_school'));

        $this->actingAs($manager)->put(route('learning.settings.update'), [
            'grade_scale' => "gut = 75\nsehr gut = 90 %\n\nnicht bestanden = 0\nbefriedigend=60",
        ])->assertSessionHasNoErrors();

        $organization = $this->organization->fresh();
        $this->assertSame([
            ['min_percent' => 90, 'label' => 'sehr gut'],
            ['min_percent' => 75, 'label' => 'gut'],
            ['min_percent' => 60, 'label' => 'befriedigend'],
            ['min_percent' => 0, 'label' => 'nicht bestanden'],
        ], $organization->settings['learning']['grade_scale']);
        $this->assertSame('gut', $gradebook->gradeFor($organization, 89));
        $this->assertSame('sehr gut', $gradebook->gradeFor($organization, 90));
        $this->assertSame('nicht bestanden', $gradebook->gradeFor($organization, 12));

        // Der Dialog zeigt den Stand in derselben Schreibweise wieder an.
        $this->actingAs($manager)->get(route('learning.settings.edit'))->assertSee('sehr gut = 90');
    }

    public function test_template_replaces_the_levels_and_empty_input_clears_them(): void {
        $manager = $this->manager();
        $gradebook = app(LearningGradebookService::class);

        $this->actingAs($manager)->put(route('learning.settings.update'), [
            'grade_scale_template' => 'school', 'grade_scale' => 'wird ersetzt = 10',
        ])->assertSessionHasNoErrors();
        $organization = $this->organization->fresh();
        $this->assertSame(['1', '2', '3', '4', '5', '6'], array_column($organization->settings['learning']['grade_scale'], 'label'));
        $this->assertSame('2', $gradebook->gradeFor($organization, 85));
        $this->assertSame('6', $gradebook->gradeFor($organization, 5));

        $this->actingAs($manager)->put(route('learning.settings.update'), ['grade_scale_template' => 'pass_fail']);
        $this->assertSame(__('learning.grade_scale.passed'), $gradebook->gradeFor($this->organization->fresh(), 50));
        $this->assertSame(__('learning.grade_scale.failed'), $gradebook->gradeFor($this->organization->fresh(), 49));

        $this->actingAs($manager)->put(route('learning.settings.update'), ['grade_scale' => '']);
        $this->assertNull($gradebook->gradeFor($this->organization->fresh(), 100));
    }

    public function test_unreadable_lines_and_duplicate_thresholds_are_rejected(): void {
        $manager = $this->manager();

        $this->actingAs($manager)->put(route('learning.settings.update'), ['grade_scale' => "gut 75"])
            ->assertSessionHasErrors('grade_scale');
        $this->actingAs($manager)->put(route('learning.settings.update'), ['grade_scale' => "gut = 120"])
            ->assertSessionHasErrors('grade_scale');
        $this->actingAs($manager)->put(route('learning.settings.update'), ['grade_scale' => "gut = 75\nganz gut = 75"])
            ->assertSessionHasErrors('grade_scale');
        $this->actingAs($manager)->put(route('learning.settings.update'), ['grade_scale_template' => 'unbekannt'])
            ->assertSessionHasErrors('grade_scale_template');

        $this->assertArrayNotHasKey('grade_scale', (array) ($this->organization->fresh()->settings['learning'] ?? []));
    }
}
