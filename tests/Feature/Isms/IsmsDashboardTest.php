<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsDashboardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\NormConformityStatus;
use App\Models\Isms\{IsmsApplicabilityStatement, IsmsAudit, IsmsAuditFinding, IsmsCertificate, IsmsControl, IsmsCorrectiveAction, IsmsNormStatus, IsmsRequirement, IsmsRisk, IsmsRiskAssessment, IsmsScope, IsmsSoftwareProduct};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Auditbereitschafts-Dashboard + Register-Exporte (Feature 044, MVP 1):
 * KPI-Berechnungen des ReadinessService (je Block ein gezieltes
 * Szenario), Scope-Trennung, Permission-Verweigerung sowie die
 * JSON-/CSV-Direkt-Exporte (meta-Block, CSV-Header/BOM, Gates).
 */
class IsmsDashboardTest extends TestCase {
    use RefreshDatabase;

    // ── KPI-Blöcke ─────────────────────────────────────────────────────

    public function test_high_risk_counts_open_risks_above_threshold_only(): void {
        [$admin, $scope] = $this->makeAdminWithScope();

        $high = $this->makeRisk($admin, $scope, likelihood: 5, impact: 5);
        $this->makeRisk($admin, $scope, likelihood: 3, impact: 4); // Score 12 = nicht hoch
        IsmsRisk::factory()->closed()->create([
            'organization_id' => $admin->organization_id,
            'isms_scope_id' => $scope->id,
            'likelihood' => 5,
            'impact' => 5,
            'score' => 25,
        ]);

        $readiness = $this->readiness($admin);

        $this->assertSame(1, $readiness['high_risks']['count'], 'Nur offene Risiken mit Score > 12 zählen');
        $this->assertTrue($readiness['high_risks']['top']->contains('id', $high->id));
    }

    public function test_overdue_reviews_and_unassessed_risks_are_counted(): void {
        [$admin, $scope] = $this->makeAdminWithScope();

        // Überfällig: jüngste freigegebene Netto-Bewertung abgelaufen.
        $overdue = $this->makeRisk($admin, $scope);
        IsmsRiskAssessment::factory()->net()->approved()->create([
            'organization_id' => $admin->organization_id,
            'isms_risk_id' => $overdue->id,
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        // Aktuell: freigegebene Netto-Bewertung mit Zukunftsdatum.
        $current = $this->makeRisk($admin, $scope);
        IsmsRiskAssessment::factory()->net()->approved()->create([
            'organization_id' => $admin->organization_id,
            'isms_risk_id' => $current->id,
            'valid_until' => now()->addMonth()->toDateString(),
        ]);

        // Unbewertet: Risiko ohne jede Bewertung.
        $unassessed = $this->makeRisk($admin, $scope);

        // Geschlossene Risiken bleiben außen vor (auch mit abgelaufener Bewertung).
        $closed = IsmsRisk::factory()->closed()->create([
            'organization_id' => $admin->organization_id,
            'isms_scope_id' => $scope->id,
        ]);
        IsmsRiskAssessment::factory()->net()->approved()->create([
            'organization_id' => $admin->organization_id,
            'isms_risk_id' => $closed->id,
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $readiness = $this->readiness($admin);

        $this->assertSame(1, $readiness['reviews']['overdue_count']);
        $this->assertTrue($readiness['reviews']['overdue']->contains('id', $overdue->id));
        $this->assertSame(1, $readiness['reviews']['unassessed_count']);
        $this->assertTrue($readiness['reviews']['unassessed']->contains('id', $unassessed->id));
    }

    public function test_evidence_gap_disappears_with_evidence_note_or_implemented_control(): void {
        [$admin, $scope] = $this->makeAdminWithScope();

        // Lücke: anwendbar, ohne Evidenz-Notiz und ohne umgesetzte Maßnahme.
        $gap = $this->makeStatement($admin, $scope, refNo: 'A.5.1');

        // Keine Lücke: Evidenz-Notiz vorhanden.
        $this->makeStatement($admin, $scope, refNo: 'A.5.2', evidenceNote: 'Richtlinie INF-01, Abschnitt 3.');

        // Keine Lücke: gemappte Maßnahme mit Umsetzungsstatus implemented.
        $withControl = $this->makeStatement($admin, $scope, refNo: 'A.5.3');
        $control = IsmsControl::factory()->implemented()->create(['organization_id' => $admin->organization_id]);
        $control->requirements()->attach($withControl->isms_requirement_id);

        // Keine Lücke: nicht anwendbar.
        $requirement = IsmsRequirement::factory()->catalog(refNo: 'A.5.4', title: 'Nicht zutreffende Anforderung')->create(['organization_id' => $admin->organization_id]);
        IsmsApplicabilityStatement::factory()->notApplicable()->create([
            'organization_id' => $admin->organization_id,
            'isms_scope_id' => $scope->id,
            'isms_requirement_id' => $requirement->id,
        ]);

        $readiness = $this->readiness($admin);

        $this->assertSame(1, $readiness['evidence_gaps']['count']);
        $this->assertTrue($readiness['evidence_gaps']['top']->contains('id', $gap->id));
    }

    public function test_overdue_corrective_actions_and_open_nonconformities_are_counted(): void {
        [$admin, $scope] = $this->makeAdminWithScope();

        $audit = IsmsAudit::factory()->inProgress()->create([
            'organization_id' => $admin->organization_id,
            'isms_scope_id' => $scope->id,
        ]);

        // Offene Nichtkonformität mit überfälliger Korrekturmaßnahme.
        $finding = IsmsAuditFinding::factory()->nonconformity()->create([
            'organization_id' => $admin->organization_id,
            'isms_audit_id' => $audit->id,
        ]);
        $overdueAction = IsmsCorrectiveAction::factory()->overdue()->create([
            'organization_id' => $admin->organization_id,
            'isms_audit_finding_id' => $finding->id,
        ]);

        // Umgesetzte Maßnahme mit überschrittener Frist zählt NICHT mehr.
        IsmsCorrectiveAction::factory()->done()->create([
            'organization_id' => $admin->organization_id,
            'isms_audit_finding_id' => $finding->id,
            'due_on' => now()->subDays(5)->toDateString(),
        ]);

        // Geschlossene Nichtkonformität zählt nicht; Beobachtung ebenfalls nicht.
        IsmsAuditFinding::factory()->nonconformity()->status(\App\Enums\Isms\FindingStatus::Closed)->create([
            'organization_id' => $admin->organization_id,
            'isms_audit_id' => $audit->id,
        ]);
        IsmsAuditFinding::factory()->create([
            'organization_id' => $admin->organization_id,
            'isms_audit_id' => $audit->id,
        ]);

        $readiness = $this->readiness($admin);

        $this->assertSame(1, $readiness['actions']['overdue_count']);
        $this->assertTrue($readiness['actions']['overdue']->contains('id', $overdueAction->id));
        $this->assertSame(1, $readiness['nonconformities']['open_count']);
        $this->assertTrue($readiness['nonconformities']['open']->contains('id', $finding->id));
    }

    public function test_certificate_and_software_blocks_flag_upcoming_dates(): void {
        [$admin, $scope] = $this->makeAdminWithScope();

        $status = IsmsNormStatus::factory()->status(NormConformityStatus::Certified)->create([
            'organization_id' => $admin->organization_id,
            'isms_scope_id' => $scope->id,
            'norm' => 'ISO/IEC 27001',
            'edition' => '2022',
        ]);
        IsmsCertificate::factory()->expiringInDays(30)->create([
            'organization_id' => $admin->organization_id,
            'isms_norm_status_id' => $status->id,
        ]);

        IsmsSoftwareProduct::factory()->endOfLife()->create(['organization_id' => $admin->organization_id]);
        IsmsSoftwareProduct::factory()->create(['organization_id' => $admin->organization_id]);

        $readiness = $this->readiness($admin);

        $certificate = $readiness['certificates']->firstWhere('norm', 'ISO/IEC 27001:2022');
        $this->assertNotNull($certificate);
        $this->assertTrue($certificate['expiring'], 'Ablauf < 90 Tage wird markiert');
        $this->assertSame(1, $readiness['software']['eol_count']);
    }

    public function test_dashboard_separates_scopes(): void {
        [$admin, $scope] = $this->makeAdminWithScope();
        $otherScope = IsmsScope::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Standort Nord']);

        $this->makeRisk($admin, $otherScope, likelihood: 5, impact: 5);
        $this->makeStatement($admin, $otherScope, refNo: 'A.8.1');

        // Default-Scope: keine hohen Risiken, keine Lücken.
        $readiness = $this->readiness($admin);
        $this->assertSame(0, $readiness['high_risks']['count']);
        $this->assertSame(0, $readiness['evidence_gaps']['count']);

        // Gewählter Scope: beide Kennzahlen sichtbar.
        $response = $this->actingAs($admin)
            ->get(route('isms.dashboard', ['scope' => $otherScope->sqid]))
            ->assertOk();
        $readiness = $response->viewData('readiness');
        $this->assertSame(1, $readiness['high_risks']['count']);
        $this->assertSame(1, $readiness['evidence_gaps']['count']);
    }

    // ── Berechtigungen ─────────────────────────────────────────────────

    public function test_regular_user_cannot_access_dashboard_or_exports(): void {
        $user = User::factory()->user()->create();
        app()->instance('currentOrganization', $user->organization);

        $this->actingAs($user)->get(route('isms.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('isms.risks.export', ['format' => 'json']))->assertForbidden();
        $this->actingAs($user)->get(route('isms.requirements.export', ['format' => 'csv']))->assertForbidden();
        $this->actingAs($user)->get(route('isms.controls.export', ['format' => 'json']))->assertForbidden();
    }

    public function test_geschaeftsfuehrung_can_view_dashboard(): void {
        $gf = User::factory()->geschaeftsfuehrung()->create();
        app()->instance('currentOrganization', $gf->organization);
        IsmsScope::factory()->default()->create(['organization_id' => $gf->organization_id]);

        $this->actingAs($gf)->get(route('isms.dashboard'))->assertOk();
    }

    // ── Exporte ────────────────────────────────────────────────────────

    public function test_risk_export_json_contains_meta_and_rows(): void {
        [$admin, $scope] = $this->makeAdminWithScope();
        $risk = $this->makeRisk($admin, $scope, likelihood: 4, impact: 4);

        $response = $this->actingAs($admin)
            ->get(route('isms.risks.export', ['format' => 'json']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8');

        $payload = json_decode($response->streamedContent(), true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('meta', $payload);
        $this->assertSame($admin->organization->name, $payload['meta']['organisation']);
        $this->assertArrayHasKey('generated_at', $payload['meta']);
        $this->assertArrayHasKey('app_version', $payload['meta']);
        $this->assertSame(1, $payload['meta']['row_count']);
        $this->assertSame($risk->displayNo(), $payload['rows'][0]['no']);
        $this->assertSame(16, $payload['rows'][0]['score']);
    }

    public function test_risk_export_csv_has_bom_header_and_data(): void {
        [$admin, $scope] = $this->makeAdminWithScope();
        $risk = $this->makeRisk($admin, $scope);

        $response = $this->actingAs($admin)
            ->get(route('isms.risks.export', ['format' => 'csv']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'CSV beginnt mit UTF-8-BOM');
        $this->assertStringContainsString(
            __('isms.field.risk_no') . ';' . __('isms.field.title') . ';' . __('isms.field.scope'),
            $content,
            'CSV-Header mit Semikolon-Trennung',
        );
        $this->assertStringContainsString('# ' . __('isms.export.meta_generated_at'), $content);
        $this->assertStringContainsString($risk->title, $content);
    }

    public function test_soa_export_contains_only_selected_scope(): void {
        [$admin, $scope] = $this->makeAdminWithScope();
        $otherScope = IsmsScope::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Standort Süd']);

        $this->makeStatement($admin, $scope, refNo: 'A.5.1');
        $this->makeStatement($admin, $otherScope, refNo: 'A.9.9');

        $response = $this->actingAs($admin)
            ->get(route('isms.requirements.export', ['scope' => $scope->sqid, 'format' => 'json']))
            ->assertOk();

        $payload = json_decode($response->streamedContent(), true);

        $this->assertSame($scope->name, $payload['meta']['scope']);
        $this->assertCount(1, $payload['rows']);
        $this->assertSame('A.5.1', $payload['rows'][0]['ref_no']);
    }

    public function test_controls_export_json_lists_requirement_refs(): void {
        [$admin, $scope] = $this->makeAdminWithScope();

        $requirement = IsmsRequirement::factory()->catalog()->create(['organization_id' => $admin->organization_id]);
        $control = IsmsControl::factory()->implemented()->create(['organization_id' => $admin->organization_id]);
        $control->requirements()->attach($requirement->id);

        $payload = json_decode(
            $this->actingAs($admin)
                ->get(route('isms.controls.export', ['format' => 'json']))
                ->assertOk()
                ->streamedContent(),
            true,
        );

        $this->assertSame($control->title, $payload['rows'][0]['title']);
        $this->assertSame('implemented', $payload['rows'][0]['implementation_status']);
        $this->assertStringContainsString('ISO/IEC 27001:2022 A.5.1', (string) $payload['rows'][0]['requirements']);
    }

    public function test_export_rejects_unknown_format(): void {
        [$admin] = $this->makeAdminWithScope();

        $this->actingAs($admin)->get(route('isms.risks.export', ['format' => 'xml']))->assertNotFound();
    }

    // ── Helfer ─────────────────────────────────────────────────────────

    /**
     * Admin + Default-Scope in einer frischen Organisation (Org-Binding
     * gesetzt, Muster IsmsConformityTest).
     *
     * @return array{0: User, 1: IsmsScope}
     */
    private function makeAdminWithScope(): array {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $scope = IsmsScope::factory()->default()->create(['organization_id' => $admin->organization_id]);

        return [$admin, $scope];
    }

    /** Offenes Risiko im angegebenen Scope (Score = likelihood × impact). */
    private function makeRisk(User $owner, IsmsScope $scope, int $likelihood = 2, int $impact = 2): IsmsRisk {
        return IsmsRisk::factory()->create([
            'organization_id' => $owner->organization_id,
            'isms_scope_id' => $scope->id,
            'likelihood' => $likelihood,
            'impact' => $impact,
            'score' => $likelihood * $impact,
        ]);
    }

    /** Anwendbare SoA-Aussage zu einer frischen Katalog-Anforderung. */
    private function makeStatement(User $owner, IsmsScope $scope, string $refNo, ?string $evidenceNote = null): IsmsApplicabilityStatement {
        $requirement = IsmsRequirement::factory()
            ->catalog(refNo: $refNo, title: 'Anforderung ' . $refNo)
            ->create(['organization_id' => $owner->organization_id]);

        return IsmsApplicabilityStatement::factory()->create([
            'organization_id' => $owner->organization_id,
            'isms_scope_id' => $scope->id,
            'isms_requirement_id' => $requirement->id,
            'evidence_note' => $evidenceNote,
        ]);
    }

    /**
     * Lädt das Dashboard des Default-Scopes und liefert die Readiness-Daten.
     *
     * @return array<string, mixed>
     */
    private function readiness(User $actor): array {
        $response = $this->actingAs($actor)->get(route('isms.dashboard'))->assertOk();

        /** @var array<string, mixed> */
        return $response->viewData('readiness');
    }
}
