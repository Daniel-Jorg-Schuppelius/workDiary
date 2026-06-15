<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDesignerHttpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\{ProcedureRunStatus, ProcedureStepRunStatus, ProcedureStepType};
use App\Models\{DiaryEntry, ProcedureRun, ProcedureStepRun, ProcedureTemplate, User};
use App\Services\Procedure\ProcedureTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Designer-UI (Feature 026): CRUD-Endpunkte der Prozedurvorlagen, Schritt-
 * Sync inkl. bedingter Schritte, Veröffentlichung, druckbare Lauf-Ansicht
 * sowie Permission-/Cross-Org-Schutz.
 */
class ProcedureDesignerHttpTest extends TestCase {
    use RefreshDatabase;

    public function test_teamleitung_can_create_template_and_is_redirected_to_designer(): void {
        $lead = User::factory()->teamleitung()->create();

        $response = $this->actingAs($lead)
            ->from(route('procedures.index'))
            ->post(route('procedures.store'), [
                'code' => 'FW_UPDATE_ROUTER',
                'name' => 'Firmware-Update Router',
                'description' => 'Update mit Pflicht-Backup',
                'domain' => 'it',
            ]);

        $this->assertDatabaseHas('procedure_templates', [
            'code' => 'FW_UPDATE_ROUTER',
            'name' => 'Firmware-Update Router',
            'organization_id' => $lead->organization_id,
            'active' => true,
        ]);

        app()->instance('currentOrganization', $lead->organization);
        $template = ProcedureTemplate::query()->firstOrFail();
        $response->assertRedirect(route('procedures.edit', $template));
        // Initiale Draft-Version wurde mit angelegt.
        $this->assertCount(1, $template->versions);
    }

    public function test_designer_save_syncs_steps_with_conditional_step_into_config(): void {
        $lead = User::factory()->teamleitung()->create();
        app()->instance('currentOrganization', $lead->organization);

        /** @var ProcedureTemplate $template */
        $template = app(ProcedureTemplateService::class)->create($lead->organization, $lead, [
            'code' => 'MAINT',
            'name' => 'Wartung',
        ]);

        $this->actingAs($lead)
            ->from(route('procedures.edit', $template))
            ->put(route('procedures.update', $template), [
                'name' => 'Wartung',
                'risk_level' => 'high',
                'applicability_entry_types' => 'service, maintenance',
                'steps' => [
                    ['code' => 'BACKUP', 'step_type' => ProcedureStepType::Backup->value, 'label' => 'Backup anlegen', 'required' => '1', 'blocking' => '1'],
                    ['code' => 'UPDATE', 'step_type' => ProcedureStepType::Confirm->value, 'label' => 'Update bestätigen', 'required' => '1', 'condition_step' => 'BACKUP', 'condition_equals' => 'done'],
                ],
            ])
            ->assertRedirect(route('procedures.edit', $template));

        $version = $template->versions()->firstOrFail();
        $steps = $version->steps()->orderBy('sort_order')->get();
        $this->assertCount(2, $steps);
        $this->assertSame('BACKUP', $steps[0]->code);
        $this->assertSame(ProcedureStepType::Backup, $steps[0]->step_type);
        // Bedingter Schritt landet additiv in config.depends_on.
        $this->assertSame('BACKUP', data_get($steps[1]->config, 'depends_on.step_code'));
        $this->assertSame('done', data_get($steps[1]->config, 'depends_on.equals'));
        // Anwendbarkeit der Draft-Version gesetzt.
        $this->assertSame(['service', 'maintenance'], $version->fresh()->applicability['diary_entry_type']);
    }

    public function test_publish_locks_version_and_steps_cannot_be_changed(): void {
        $lead = User::factory()->teamleitung()->create();
        app()->instance('currentOrganization', $lead->organization);

        /** @var ProcedureTemplate $template */
        $template = app(ProcedureTemplateService::class)->create($lead->organization, $lead, [
            'code' => 'PUB',
            'name' => 'Publish-Test',
        ]);
        $version = $template->versions()->firstOrFail();

        $this->actingAs($lead)
            ->post(route('procedures.versions.publish', [$template, $version->id]))
            ->assertRedirect(route('procedures.edit', $template));

        $this->assertNotNull($version->fresh()->published_at);

        // Nach Publish: kein Draft → Editor zeigt read-only Hinweis.
        $this->actingAs($lead)
            ->get(route('procedures.edit', $template))
            ->assertOk()
            ->assertSee(__('procedure.notice.noDraft'));
    }

    public function test_run_print_view_renders_steps(): void {
        $lead = User::factory()->teamleitung()->create();
        app()->instance('currentOrganization', $lead->organization);

        /** @var ProcedureTemplate $template */
        $template = app(ProcedureTemplateService::class)->create($lead->organization, $lead, [
            'code' => 'RUNPRINT',
            'name' => 'Druck-Test',
        ]);
        $version = $template->versions()->firstOrFail();
        $step = app(ProcedureTemplateService::class)->addStepDef($version, [
            'code' => 'CHECK',
            'step_type' => ProcedureStepType::Confirm->value,
            'label' => 'Sichtprüfung durchführen',
        ]);

        $diary = DiaryEntry::factory()->create(['organization_id' => $lead->organization_id, 'user_id' => $lead->id]);
        $run = ProcedureRun::factory()->create([
            'organization_id' => $lead->organization_id,
            'procedure_template_version_id' => $version->id,
            'subject_type' => $diary->getMorphClass(),
            'subject_id' => $diary->id,
            'status' => ProcedureRunStatus::Completed->value,
            'created_by_user_id' => $lead->id,
        ]);
        ProcedureStepRun::factory()->create([
            'procedure_run_id' => $run->id,
            'procedure_step_def_id' => $step->id,
            'status' => ProcedureStepRunStatus::Done->value,
            'executed_by_user_id' => $lead->id,
            'executed_at' => now(),
        ]);

        $this->actingAs($lead)
            ->get(route('procedure-runs.print', $run))
            ->assertOk()
            ->assertSee('Sichtprüfung durchführen')
            ->assertSee(__('procedure.print.title'));
    }

    public function test_user_without_template_create_permission_cannot_open_designer(): void {
        // UserRole::User trägt procedure.run.* aber NICHT procedure.template.create/update.
        $plain = User::factory()->user()->create();
        app()->instance('currentOrganization', $plain->organization);

        $template = ProcedureTemplate::factory()->create(['organization_id' => $plain->organization_id]);

        $this->actingAs($plain)
            ->get(route('procedures.edit', $template))
            ->assertForbidden();

        $this->actingAs($plain)
            ->post(route('procedures.store'), ['code' => 'X', 'name' => 'Verboten'])
            ->assertForbidden();
    }

    public function test_cross_org_template_returns_404(): void {
        $lead = User::factory()->teamleitung()->create();
        $other = User::factory()->teamleitung()->create();
        $foreign = ProcedureTemplate::factory()->create(['organization_id' => $other->organization_id]);

        $this->actingAs($lead)
            ->get(route('procedures.edit', $foreign))
            ->assertNotFound();
    }
}
