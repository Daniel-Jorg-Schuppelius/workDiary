<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeTargetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Models\Absence\SickLeave;
use App\Models\Platform\User;
use App\Models\Project\{Project, ProjectTimeTarget};
use App\Services\Project\TimeTargets\ProjectTimeTargetResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Projekt-Sollzeiten je Person (Feature 194, MVP-1467): Vorrang Datum vor
 * Wochentag und Person vor Projektvorgabe, Gültigkeit, Abwesenheit, Pflege
 * über den Dialog, Kacheln am Zeitreiter.
 */
class ProjectTimeTargetTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $anna;

    private User $ben;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->anna = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Anna Beispiel']);
        $this->ben = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Ben Muster']);
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schule Nord']);
        $this->project->members()->attach([$this->anna->id, $this->ben->id]);
    }

    public function test_resolver_applies_precedence_validity_and_absence(): void {
        // Projektvorgabe Mo–Fr 8 h ab Montag, dem 11.03.2030; Anna mittwochs 4 h, am Donnerstag ausgesetzt; Ben am Freitag krank.
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            ProjectTimeTarget::factory()->create(['project_id' => $this->project->id, 'weekday' => $weekday, 'target_minutes' => 480, 'valid_from' => '2030-03-11']);
        }
        ProjectTimeTarget::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->anna->id, 'weekday' => 3, 'target_minutes' => 240]);
        ProjectTimeTarget::factory()->onDate('2030-03-14')->create(['project_id' => $this->project->id, 'user_id' => $this->anna->id, 'target_minutes' => 0]);
        SickLeave::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->ben->id, 'start_date' => '2030-03-15', 'end_date' => '2030-03-15']);

        $resolver = app(ProjectTimeTargetResolver::class);
        $targets = $resolver->targetsFor($this->project, [$this->anna->id, $this->ben->id], CarbonImmutable::parse('2030-03-08'), CarbonImmutable::parse('2030-03-16'));

        $this->assertSame([
            '2030-03-11' => 480, '2030-03-12' => 480, '2030-03-13' => 240, '2030-03-14' => 0, '2030-03-15' => 480,
        ], $targets[$this->anna->id], 'Vorgabe erst ab Gültigkeit, Mittwoch eigene Zeile, Donnerstag Ausnahme');
        $this->assertSame([
            '2030-03-11' => 480, '2030-03-12' => 480, '2030-03-13' => 480, '2030-03-14' => 480, '2030-03-15' => 0,
        ], $targets[$this->ben->id], 'Krankheit setzt das Soll auf null');
        $this->assertEqualsCanonicalizing([$this->anna->id, $this->ben->id], $resolver->subjectUserIds($this->project));
    }

    public function test_update_replaces_the_target_set_and_ignores_non_members(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        ProjectTimeTarget::factory()->create(['project_id' => $this->project->id, 'weekday' => 6, 'target_minutes' => 120]);

        $this->actingAs($this->admin)->put(route('projects.time-targets.update', $this->project), [
            'default' => [1 => '8:00', 2 => '8:00', 3 => '8:00', 4 => '8:00', 5 => '8:00', 6 => '', 7 => ''],
            'members' => [
                $this->anna->sqid => [1 => '', 3 => '4:00'],
                $stranger->sqid => [1 => '1:00'],
            ],
            'exceptions' => [
                ['user' => $this->anna->sqid, 'date' => '2030-03-14', 'minutes' => '0:00'],
                ['user' => '', 'date' => '', 'minutes' => ''],
            ],
            'valid_from' => '2030-03-11',
            'valid_until' => '',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $rows = $this->project->timeTargets()->get();
        $this->assertCount(7, $rows, 'Fünf Vorgaben, eine Personenzeile, eine Ausnahme; der alte Samstag ist weg');
        $this->assertSame(0, $rows->where('user_id', $stranger->id)->count(), 'Nicht zuweisbare Personen fallen weg');
        $this->assertSame(240, (int) $rows->firstWhere('user_id', $this->anna->id)->target_minutes);
        $this->assertSame('2030-03-11', $rows->whereNull('user_id')->first()->valid_from?->toDateString());
        $exception = $rows->whereNotNull('specific_date')->first();
        $this->assertSame(0, (int) $exception->target_minutes);
        $this->assertNull($exception->valid_from, 'Ausnahmen tragen keine Gültigkeit');
    }

    public function test_edit_dialog_lists_members_and_requires_the_permission(): void {
        ProjectTimeTarget::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->ben->id, 'weekday' => 2, 'target_minutes' => 300]);

        $html = $this->actingAs($this->admin)->get(route('projects.time-targets.edit', $this->project))->assertOk()->getContent();
        $this->assertStringContainsString('Anna Beispiel', $html);
        $this->assertStringContainsString('name="members[' . $this->ben->sqid . '][2]"', $html);
        $this->assertStringContainsString('value="5:00"', $html);

        $this->actingAs($this->anna)->get(route('projects.time-targets.edit', $this->project))->assertForbidden();
        $this->actingAs($this->anna)->put(route('projects.time-targets.update', $this->project), ['default' => [1 => '8:00']])->assertForbidden();
    }

    public function test_time_tab_shows_target_tiles_and_the_manage_button(): void {
        ProjectTimeTarget::factory()->create(['project_id' => $this->project->id, 'weekday' => 1, 'target_minutes' => 480]);

        $html = $this->actingAs($this->admin)->get(route('projects.show', ['project' => $this->project, 'tab' => 'time']))->assertOk()->getContent();

        $this->assertStringContainsString('Soll im Zeitraum', $html);
        $this->assertStringContainsString('Offene Unterschreitungen', $html);
        $this->assertStringContainsString(route('projects.time-targets.edit', $this->project), $html);
    }

    /** MVP-1617 (F9-04): Soll je Objekt trägt nicht — die nie geschriebene Spalte ist entfernt. */
    public function test_targets_carry_no_site(): void {
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('project_time_targets', 'site_id'));
        $this->assertNotContains('site_id', (new ProjectTimeTarget())->getFillable());
    }
}
