<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisBiaTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Crisis;

use App\Enums\Crisis\CrisisProcessCriticality;
use App\Models\Crisis\{CrisisAction, CrisisBusinessProcess, CrisisCase, CrisisExercise};
use App\Models\Platform\User;
use App\Models\Procedure\ProcedureTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-943/944: BIA-Register mit Import und Übernahme, BCM-Auswertung. */
final class CrisisBiaTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_register_import_and_adoption(): void {
        $template = ProcedureTemplate::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Notbetrieb Lager', 'active' => true]);
        $this->actingAs($this->admin)->get(route('crisis.bia.index'))->assertOk()->assertSee('Notbetrieb Lager');

        $this->actingAs($this->admin)->post(route('crisis.bia.import'), ['keys' => [$template->getMorphClass() . ':' . $template->id]])->assertSessionHas('success');
        $process = CrisisBusinessProcess::query()->sole();
        $this->assertSame('Notbetrieb Lager', $process->name);
        $this->actingAs($this->admin)->get(route('crisis.bia.index'))->assertOk()->assertDontSee('Notbetrieb Lager (');

        $this->actingAs($this->admin)->put(route('crisis.bia.update', $process), ['name' => 'Notbetrieb Lager', 'criticality' => 'critical', 'rto_hours' => 4, 'rpo_hours' => 1])->assertRedirect();
        $this->assertSame(CrisisProcessCriticality::Critical, $process->fresh()?->criticality);

        $case = CrisisCase::query()->create(['organization_id' => $this->organization->id, 'title' => 'Stromausfall', 'category' => 'infrastructure', 'severity' => 'major', 'status' => 'reported', 'created_by' => $this->admin->id]);
        $this->actingAs($this->admin)->post(route('crisis.bcm.adopt', $case), ['process_id' => $process->sqid])->assertSessionHas('status');
        $impact = $case->continuityImpacts()->sole();
        $this->assertSame(4, $impact->rto_hours);
        $this->assertSame($process->id, $impact->crisis_business_process_id);
    }

    public function test_bcm_report(): void {
        CrisisBusinessProcess::query()->create(['organization_id' => $this->organization->id, 'name' => 'Auftragsannahme', 'criticality' => 'high', 'review_due_on' => now()->subDay()->toDateString()]);
        CrisisExercise::query()->create(['organization_id' => $this->organization->id, 'title' => 'Übung', 'scenario' => 'IT-Ausfall', 'exercised_at' => now()->subMonth(), 'effectiveness' => 'partly', 'next_due_on' => now()->subDay()->toDateString()]);
        $case = CrisisCase::query()->create(['organization_id' => $this->organization->id, 'title' => 'Alt', 'category' => 'security', 'severity' => 'minor', 'status' => 'closed', 'created_by' => $this->admin->id]);
        CrisisAction::query()->create(['organization_id' => $this->organization->id, 'crisis_case_id' => $case->id, 'title' => 'Nacharbeit', 'priority' => 'normal', 'status' => 'open', 'due_at' => now()->subDay()]);

        $this->actingAs($this->admin)->get(route('crisis.bcm-report'))->assertOk()->assertSee(__('crisis.bcm_report.title'));
        $pdf = $this->actingAs($this->admin)->get(route('crisis.bcm-report.pdf'))->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());

        $report = app(\App\Services\Crisis\CrisisBcmReportBuilder::class)->build();
        $this->assertSame(1, $report['exercises']);
        $this->assertSame(1, $report['exercises_due']);
        $this->assertSame(1, $report['actions_overdue']);
        $this->assertSame(1, $report['cases_without_review']);
        $this->assertSame(1, $report['processes_review_due']);
    }
}
