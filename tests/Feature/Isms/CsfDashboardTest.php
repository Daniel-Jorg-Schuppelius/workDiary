<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsfDashboardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\{ControlImplementationStatus, RequirementSource};
use App\Models\Isms\{IsmsApplicabilityStatement, IsmsRequirement, IsmsScope};
use App\Models\User;
use App\Services\Isms\CsfReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NIST-CSF-2.0-Funktionsabdeckung + Crosswalk (Nachtrag NIST): die
 * Abdeckung je CSF-Funktion wird direkt aus der NIST-SoA ODER abgeleitet
 * aus der ISO/IEC-27001-SoA über den Crosswalk berechnet (Vorrang: direkt).
 * Prüft die beiden Quellen, den Vorrang, die Crosswalk-Sicht und die
 * Berechtigungen.
 */
class CsfDashboardTest extends TestCase {
    use RefreshDatabase;

    public function test_regular_user_cannot_access_csf_views(): void {
        $user = User::factory()->user()->create();
        app()->instance('currentOrganization', $user->organization);

        $this->actingAs($user)->get(route('isms.csf'))->assertForbidden();
        $this->actingAs($user)->get(route('isms.csf.crosswalk'))->assertForbidden();
    }

    public function test_admin_can_view_csf_dashboard_and_crosswalk(): void {
        [$admin] = $this->makeAdminWithScope();

        $this->actingAs($admin)->get(route('isms.csf'))->assertOk();
        $this->actingAs($admin)->get(route('isms.csf.crosswalk'))->assertOk();
    }

    public function test_coverage_is_derived_from_iso_soa_when_no_nist_catalog(): void {
        [$admin, $scope] = $this->makeAdminWithScope();

        // GV.SC ist auf A.5.19–A.5.23 gemappt (5 ISO-Referenzen): 4 umgesetzt,
        // 1 offen ⇒ anwendbar 5, abgedeckt 4, Quote 80 %.
        $this->makeIsoStatement($admin, $scope, 'A.5.19', ControlImplementationStatus::Implemented);
        $this->makeIsoStatement($admin, $scope, 'A.5.20', ControlImplementationStatus::Implemented);
        $this->makeIsoStatement($admin, $scope, 'A.5.21', ControlImplementationStatus::Implemented);
        $this->makeIsoStatement($admin, $scope, 'A.5.22', ControlImplementationStatus::Partial);
        $this->makeIsoStatement($admin, $scope, 'A.5.23', ControlImplementationStatus::Open);

        $readiness = app(CsfReadinessService::class)->forScope($scope);

        $this->assertFalse($readiness['has_nist']);
        $this->assertTrue($readiness['has_crosswalk']);

        $gv = $this->functionRow($readiness, 'GV');
        $this->assertSame('mapped', $gv['mode']);
        $this->assertSame(5, $gv['mapped']['applicable']);
        $this->assertSame(4, $gv['mapped']['covered']);
        $this->assertSame(80, $gv['quote']);
    }

    public function test_direct_nist_soa_takes_precedence_over_crosswalk(): void {
        [$admin, $scope] = $this->makeAdminWithScope();

        // Direkte NIST-Anforderung (GV.OC) umgesetzt.
        $this->makeNistStatement($admin, $scope, 'GV.OC', ControlImplementationStatus::Implemented);
        // Zusätzlich eine gemappte ISO-Referenz der GV-Funktion (GV.SC → A.5.19).
        $this->makeIsoStatement($admin, $scope, 'A.5.19', ControlImplementationStatus::Open);

        $readiness = app(CsfReadinessService::class)->forScope($scope);

        $this->assertTrue($readiness['has_nist']);

        $gv = $this->functionRow($readiness, 'GV');
        $this->assertSame('direct', $gv['mode'], 'Direkte NIST-SoA hat Vorrang vor dem Crosswalk');
        $this->assertSame(1, $gv['direct']['applicable']);
        $this->assertSame(1, $gv['direct']['covered']);
        $this->assertSame(100, $gv['quote']);
    }

    public function test_crosswalk_page_lists_mappings_with_iso_coverage(): void {
        [$admin, $scope] = $this->makeAdminWithScope();

        $this->makeIsoStatement($admin, $scope, 'A.5.19', ControlImplementationStatus::Implemented);

        // Rendering-Wiring der Route.
        $this->actingAs($admin)->get(route('isms.csf.crosswalk'))->assertOk();

        // Datenberechnung typsicher über den Service.
        $crosswalk = app(CsfReadinessService::class)->crosswalkForScope($scope);
        if ($crosswalk === null) {
            $this->fail('Es sollte ein Crosswalk existieren.');
        }

        $gvSc = collect($crosswalk['rows'])->firstWhere('source_ref', 'GV.SC');
        $this->assertNotNull($gvSc);
        $this->assertContains('A.5.19', array_column($gvSc['targets'], 'ref'));
        $this->assertGreaterThanOrEqual(1, $gvSc['coverage']['covered']);
    }

    public function test_coverage_separates_scopes(): void {
        [$admin, $scope] = $this->makeAdminWithScope();
        $other = IsmsScope::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Standort Nord']);

        // Nur im anderen Scope umgesetzt.
        $this->makeIsoStatement($admin, $other, 'A.5.19', ControlImplementationStatus::Implemented);

        $service = app(CsfReadinessService::class);

        // Default-Scope: keine Abdeckung.
        $gvDefault = $this->functionRow($service->forScope($scope), 'GV');
        $this->assertSame(0, $gvDefault['mapped']['covered']);

        // Anderer Scope: Abdeckung sichtbar (auch über die Route erreichbar).
        $this->actingAs($admin)->get(route('isms.csf', ['scope' => $other->sqid]))->assertOk();
        $gvOther = $this->functionRow($service->forScope($other), 'GV');
        $this->assertSame(1, $gvOther['mapped']['covered']);
    }

    // ── Helfer ─────────────────────────────────────────────────────────

    /** @return array{0: User, 1: IsmsScope} */
    private function makeAdminWithScope(): array {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);
        $scope = IsmsScope::factory()->default()->create(['organization_id' => $admin->organization_id]);

        return [$admin, $scope];
    }

    private function makeIsoStatement(User $owner, IsmsScope $scope, string $refNo, ControlImplementationStatus $status): void {
        $requirement = IsmsRequirement::factory()
            ->catalog(refNo: $refNo, title: 'ISO ' . $refNo)
            ->create(['organization_id' => $owner->organization_id]);

        $this->makeStatement($owner, $scope, $requirement, $status);
    }

    private function makeNistStatement(User $owner, IsmsScope $scope, string $refNo, ControlImplementationStatus $status): void {
        $requirement = IsmsRequirement::factory()->create([
            'organization_id' => $owner->organization_id,
            'norm' => 'NIST CSF',
            'edition' => '2.0',
            'ref_no' => $refNo,
            'title' => 'CSF ' . $refNo,
            'source' => RequirementSource::Catalog->value,
        ]);

        $this->makeStatement($owner, $scope, $requirement, $status);
    }

    private function makeStatement(User $owner, IsmsScope $scope, IsmsRequirement $requirement, ControlImplementationStatus $status): void {
        IsmsApplicabilityStatement::factory()->create([
            'organization_id' => $owner->organization_id,
            'isms_scope_id' => $scope->id,
            'isms_requirement_id' => $requirement->id,
            'applicable' => true,
            'implementation_status' => $status->value,
        ]);
    }

    /**
     * Liefert die Zeile einer CSF-Funktion aus dem Readiness-Ergebnis.
     *
     * @param  array{functions: list<array{ref: string, title: string, direct: array{total: int, applicable: int, covered: int, quote: int}, mapped: array{total: int, applicable: int, covered: int, quote: int}, mode: string, quote: int, tone: string}>, ...}  $readiness
     * @return array{ref: string, title: string, direct: array{total: int, applicable: int, covered: int, quote: int}, mapped: array{total: int, applicable: int, covered: int, quote: int}, mode: string, quote: int, tone: string}
     */
    private function functionRow(array $readiness, string $ref): array {
        foreach ($readiness['functions'] as $row) {
            if ($row['ref'] === $ref) {
                return $row;
            }
        }

        $this->fail("CSF-Funktion {$ref} fehlt im Ergebnis.");
    }
}
