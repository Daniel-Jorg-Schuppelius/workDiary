<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDeviationReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Procedure\{ProcedureDeviationProposedAction, ProcedureDeviationSeverity, ProcedureDeviationType};
use App\Models\{AuditLog, DiaryEntry, OpenIssue, ProcedureDeviation, ProcedureRun, ProcedureStepDef, ProcedureStepRun, ProcedureTemplate, ProcedureTemplateVersion, User};
use App\Services\Reporting\ProcedureDeviationReportBuilder;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Prozedur-Abweichungs-Report (Feature 026, MVP-713 — Vollscan G8): Kennzahlen
 * je Typ/Schweregrad, Folgemaßnahmen-Quote, Top-Prozeduren, Ø Zeit bis
 * Entscheidung, Filter, Recht und Exporte (CSV/XLSX/PDF + Audit).
 */
final class ProcedureDeviationReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function deviation(ProcedureTemplate $template, array $attributes = []): ProcedureDeviation {
        // Eine veröffentlichte Version je Vorlage (Unique template+version).
        $version = ProcedureTemplateVersion::query()->where('procedure_template_id', $template->id)->first()
            ?? ProcedureTemplateVersion::factory()->published()->create(['procedure_template_id' => $template->id]);
        $stepDef = ProcedureStepDef::factory()->create(['procedure_template_version_id' => $version->id, 'label' => 'Prüfschritt']);
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id]);
        $run = ProcedureRun::factory()->create([
            'organization_id' => $this->organization->id,
            'procedure_template_version_id' => $version->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'created_by_user_id' => $this->admin->id,
        ]);
        $stepRun = ProcedureStepRun::factory()->create(['procedure_run_id' => $run->id, 'procedure_step_def_id' => $stepDef->id]);

        return ProcedureDeviation::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'procedure_step_run_id' => $stepRun->id,
            'created_by_user_id' => $this->admin->id,
            'created_at' => '2026-06-10 08:00:00',
            'updated_at' => '2026-06-10 08:00:00',
        ], $attributes));
    }

    private function template(string $name): ProcedureTemplate {
        return ProcedureTemplate::factory()->create(['organization_id' => $this->organization->id, 'name' => $name]);
    }

    public function test_builder_aggregates_types_severities_follow_ups_and_decision_time(): void {
        $backup = $this->template('Backup-Prozedur');
        $update = $this->template('Update-Prozedur');
        $issue = OpenIssue::factory()->create(['organization_id' => $this->organization->id]);

        $this->deviation($backup, [
            'deviation_type' => ProcedureDeviationType::NotPossible->value,
            'severity' => ProcedureDeviationSeverity::High->value,
            'proposed_action' => ProcedureDeviationProposedAction::OpenIssue->value,
            'open_issue_id' => $issue->id,
            'risk_accepted_by_user_id' => $this->admin->id,
            'risk_accepted_at' => '2026-06-10 20:00:00', // 12 h nach Anlage
        ]);
        $this->deviation($backup, [
            'deviation_type' => ProcedureDeviationType::SafetyBlock->value,
            'severity' => ProcedureDeviationSeverity::Critical->value,
            'risk_accepted_by_user_id' => $this->admin->id,
            'risk_accepted_at' => '2026-06-11 08:00:00', // 24 h nach Anlage
        ]);
        $this->deviation($update, [
            'deviation_type' => ProcedureDeviationType::Partial->value,
            'severity' => ProcedureDeviationSeverity::Medium->value,
        ]);
        // Außerhalb des Zeitraums: zählt nicht.
        $this->deviation($update, ['created_at' => '2026-05-01 08:00:00']);

        $result = app(ProcedureDeviationReportBuilder::class)->build(
            CarbonImmutable::parse('2026-06-01')->startOfDay(),
            CarbonImmutable::parse('2026-06-30')->endOfDay(),
        );

        $this->assertSame(3, $result['total']);
        $this->assertSame(1, $result['byType']['not_possible']);
        $this->assertSame(1, $result['byType']['safety_block']);
        $this->assertSame(1, $result['byType']['partial']);
        $this->assertSame(1, $result['bySeverity']['critical']);
        $this->assertSame(1, $result['bySeverity']['high']);
        $this->assertSame(1, $result['followUpCount']);
        $this->assertSame(33.3, $result['followUpRate']);
        $this->assertSame(2, $result['riskAcceptedCount']);
        $this->assertSame(18.0, $result['avgDecisionHours']);
        $this->assertSame('Backup-Prozedur', $result['topTemplates'][0]['templateName']);
        $this->assertSame(2, $result['topTemplates'][0]['count']);
    }

    public function test_builder_filters_by_template_type_severity_risk_and_follow_up(): void {
        $backup = $this->template('Backup-Prozedur');
        $update = $this->template('Update-Prozedur');
        $issue = OpenIssue::factory()->create(['organization_id' => $this->organization->id]);

        $this->deviation($backup, [
            'deviation_type' => ProcedureDeviationType::NotPossible->value,
            'severity' => ProcedureDeviationSeverity::High->value,
            'open_issue_id' => $issue->id,
            'risk_accepted_by_user_id' => $this->admin->id,
            'risk_accepted_at' => '2026-06-10 20:00:00',
        ]);
        $this->deviation($update, [
            'deviation_type' => ProcedureDeviationType::Partial->value,
            'severity' => ProcedureDeviationSeverity::Medium->value,
        ]);

        $from = CarbonImmutable::parse('2026-06-01')->startOfDay();
        $to = CarbonImmutable::parse('2026-06-30')->endOfDay();
        $builder = app(ProcedureDeviationReportBuilder::class);

        $this->assertSame(1, $builder->build($from, $to, templateId: $backup->id)['total']);
        $this->assertSame(1, $builder->build($from, $to, type: ProcedureDeviationType::Partial)['total']);
        $this->assertSame(1, $builder->build($from, $to, severity: ProcedureDeviationSeverity::High)['total']);
        $this->assertSame(1, $builder->build($from, $to, riskAccepted: true)['total']);
        $this->assertSame(1, $builder->build($from, $to, riskAccepted: false)['total']);
        $this->assertSame(1, $builder->build($from, $to, withFollowUp: true)['total']);
        $this->assertSame(1, $builder->build($from, $to, withFollowUp: false)['total']);
        $this->assertSame(0, $builder->build($from, $to, templateId: $update->id, withFollowUp: true)['total']);
    }

    public function test_route_renders_with_filters_and_sqid_template_parameter(): void {
        $backup = $this->template('Backup-Prozedur');
        $this->deviation($backup);

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.procedure-deviations', [
                'template' => Sqid::encode(ProcedureTemplate::class, $backup->id),
                'severity' => 'high',
                'risk' => 'no',
            ]));

        $response->assertOk();
        $response->assertSeeText('Backup-Prozedur');
        $response->assertSeeText('Prüfschritt');
        $response->assertSee('<figure', false);
    }

    public function test_route_is_forbidden_without_deviation_view_permission(): void {
        // Callcenter-Rolle trägt kein procedure.deviation.view.
        $user = $this->userWithRole('callcenter');

        $this->actingAs($user)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.procedure-deviations'))
            ->assertForbidden();
    }

    public function test_csv_xlsx_and_pdf_exports_are_audited(): void {
        $this->deviation($this->template('Backup-Prozedur'), [
            'deviation_type' => ProcedureDeviationType::FailedCheck->value,
            'reason_text' => 'Prüfwert außerhalb der Toleranz, Nachprüfung nötig.',
        ]);

        $csv = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.procedure-deviations', ['export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('Content-Type'));
        $body = (string) $csv->getContent();
        $this->assertStringContainsString('#report:procedure-deviations', $body);
        $this->assertStringContainsString('Backup-Prozedur', $body);
        $this->assertStringContainsString('failed_check', $body);

        $xlsx = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.procedure-deviations', ['export' => 'xlsx']));
        $xlsx->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $xlsx->headers->get('Content-Type'));

        $pdf = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.procedure-deviations', ['export' => 'pdf']));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        $exports = AuditLog::query()->where('event', 'report.exported')->get()
            ->filter(static fn(AuditLog $log): bool => ($log->changes['report_code'] ?? null) === 'procedure-deviations');
        $this->assertSame(['csv', 'xlsx', 'pdf'], $exports->map(static fn(AuditLog $log): string => (string) $log->changes['format'])->values()->all());
    }
}
