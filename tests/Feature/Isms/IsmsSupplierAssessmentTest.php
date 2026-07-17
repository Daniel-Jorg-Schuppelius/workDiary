<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSupplierAssessmentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\{IncidentSeverity, SupplierAssessmentStatus};
use App\Enums\Notification\NotificationEvent;
use App\Enums\Privacy\{AgreementStatus, ProcessorRole};
use App\Models\Isms\{IsmsScope, IsmsSupplierAssessment};
use App\Models\{Organization, Supplier, User};
use App\Models\Privacy\{ProcessingAgreement, Processor};
use App\Services\Isms\ReadinessService;
use App\Services\Notification\NotificationDispatcher;
use App\Services\Privacy\DataProtectionPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Lieferantenbewertung (Feature 044, MVP 2/3): CRUD, Statusmaschine,
 * optionaler Supplier-Bezug, Berechtigungen, Cross-Org-Isolation, Einfluss
 * überfälliger Reviews auf die Auditbereitschaft und der Fristen-Scanner.
 */
class IsmsSupplierAssessmentTest extends TestCase {
    use RefreshDatabase;

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    public function test_admin_can_create_assessment_with_freetext_supplier(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('isms.suppliers.index'))
            ->post(route('isms.suppliers.store'), [
                'supplier_name' => 'Acme Cloud GmbH',
                'criticality' => IncidentSeverity::High->value,
                'risk_rating' => IncidentSeverity::Medium->value,
                'has_nda' => '1',
                'service_description' => 'Hosting',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('isms_supplier_assessments', [
            'supplier_name' => 'Acme Cloud GmbH',
            'organization_id' => $admin->organization_id,
            'assessment_no' => 1,
            'criticality' => IncidentSeverity::High->value,
            'risk_rating' => IncidentSeverity::Medium->value,
            'has_nda' => true,
            'has_dpa' => false,
            'status' => SupplierAssessmentStatus::Draft->value,
        ]);
    }

    public function test_supplier_master_data_link_fills_display_name(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);
        $supplier = Supplier::factory()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Linked Supplier AG',
        ]);

        $this->actingAs($admin)
            ->post(route('isms.suppliers.store'), [
                'supplier_id' => $supplier->sqid,
                // supplier_name leer — soll aus dem Lieferantennamen befüllt werden.
                'supplier_name' => '',
            ])
            ->assertRedirect();

        $assessment = IsmsSupplierAssessment::query()->firstOrFail();
        $this->assertSame($supplier->id, $assessment->supplier_id);
        $this->assertSame('Linked Supplier AG', $assessment->supplier_name);
        $this->assertSame('Linked Supplier AG', $assessment->displayName());
    }

    public function test_free_text_name_required_without_supplier_link(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('isms.suppliers.index'))
            ->post(route('isms.suppliers.store'), ['supplier_name' => ''])
            ->assertSessionHasErrors('supplier_name');

        $this->assertDatabaseCount('isms_supplier_assessments', 0);
    }

    public function test_status_machine_rejects_illegal_transition(): void {
        $admin = User::factory()->admin()->create();
        $assessment = $this->makeAssessment($admin, ['status' => SupplierAssessmentStatus::Draft->value]);

        // draft → approved ist NICHT erlaubt (erst assessed/flagged).
        $this->actingAs($admin)
            ->from(route('isms.suppliers.index'))
            ->post(route('isms.suppliers.transition', $assessment), ['status' => SupplierAssessmentStatus::Approved->value])
            ->assertSessionHasErrors('status');
        $this->assertSame(SupplierAssessmentStatus::Draft, $assessment->refresh()->status);

        // draft → assessed → approved ist erlaubt; die Freigabe setzt last_review_on.
        $this->actingAs($admin)
            ->post(route('isms.suppliers.transition', $assessment), ['status' => SupplierAssessmentStatus::Assessed->value])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('isms.suppliers.transition', $assessment), ['status' => SupplierAssessmentStatus::Approved->value])
            ->assertRedirect();

        $assessment->refresh();
        $this->assertSame(SupplierAssessmentStatus::Approved, $assessment->status);
        $this->assertNotNull($assessment->last_review_on);
    }

    public function test_overdue_review_feeds_dashboard_unchecked_suppliers(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);
        $scope = IsmsScope::factory()->default()->create(['organization_id' => $admin->organization_id]);

        // Überfälliger Review, nicht freigegeben ⇒ zählt.
        $overdue = IsmsSupplierAssessment::factory()->reviewOverdue()->create([
            'organization_id' => $admin->organization_id,
            'isms_scope_id' => $scope->id,
        ]);

        // Überfällig, aber FREIGEGEBEN ⇒ zählt nicht (gilt als geprüft).
        IsmsSupplierAssessment::factory()->create([
            'organization_id' => $admin->organization_id,
            'isms_scope_id' => $scope->id,
            'status' => SupplierAssessmentStatus::Approved->value,
            'next_review_on' => now()->subDays(10)->toDateString(),
        ]);

        // Review in der Zukunft ⇒ zählt nicht.
        IsmsSupplierAssessment::factory()->create([
            'organization_id' => $admin->organization_id,
            'isms_scope_id' => $scope->id,
            'status' => SupplierAssessmentStatus::Assessed->value,
            'next_review_on' => now()->addMonth()->toDateString(),
        ]);

        $readiness = app(ReadinessService::class)->forScope($scope->refresh());

        $this->assertSame(1, $readiness['suppliers']['overdue_count']);
        $this->assertTrue($readiness['suppliers']['overdue']->contains('id', $overdue->id));
    }

    public function test_scanner_reports_overdue_review_once(): void {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->makeAssessment($admin, [
            'status' => SupplierAssessmentStatus::Assessed->value,
            'next_review_on' => now()->subDays(4)->toDateString(),
            'owner_user_id' => $owner->id,
        ]);

        $dispatcher = app(NotificationDispatcher::class);
        $sent = $dispatcher->notify(
            NotificationEvent::IsmsSupplierReviewOverdue,
            IsmsSupplierAssessment::query()->firstOrFail(),
            $owner,
            ['title' => 'x', 'message' => 'x', 'url' => null],
            dedup: true,
        );
        $this->assertGreaterThanOrEqual(1, $sent);

        $again = $dispatcher->notify(
            NotificationEvent::IsmsSupplierReviewOverdue,
            IsmsSupplierAssessment::query()->firstOrFail(),
            $owner,
            ['title' => 'x', 'message' => 'x', 'url' => null],
            dedup: true,
        );
        $this->assertSame(0, $again);
    }

    public function test_review_overdue_scope_excludes_approved(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);
        IsmsSupplierAssessment::factory()->create([
            'organization_id' => $admin->organization_id,
            'status' => SupplierAssessmentStatus::Approved->value,
            'next_review_on' => now()->subDays(5)->toDateString(),
        ]);

        $this->assertSame(0, IsmsSupplierAssessment::query()->reviewOverdue()->count());
    }

    public function test_regular_user_cannot_manage(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('isms.suppliers.index'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('isms.suppliers.store'), ['supplier_name' => 'X'])
            ->assertForbidden();
    }

    public function test_cross_organization_assessment_is_not_accessible(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-sa-cross']);
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        $foreign = $this->makeAssessment($otherAdmin);

        $this->actingAs($admin)
            ->put(route('isms.suppliers.update', $foreign), ['supplier_name' => 'Hijack'])
            ->assertNotFound();
    }

    public function test_foreign_supplier_link_is_dropped(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-sa-supplier-cross']);
        app()->instance('currentOrganization', $otherOrg);
        $foreignSupplier = Supplier::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'Foreign Co']);

        app()->instance('currentOrganization', $admin->organization);
        $this->actingAs($admin)
            ->post(route('isms.suppliers.store'), [
                'supplier_id' => $foreignSupplier->id,
                'supplier_name' => 'Fallback Name',
            ])
            // Validierung lehnt den fremden Supplier ab (Rule::exists org-gescopt).
            ->assertSessionHasErrors('supplier_id');
    }

    public function test_readiness_assessment_is_never_certified_label(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);
        IsmsScope::factory()->default()->create(['organization_id' => $admin->organization_id]);

        $response = $this->actingAs($admin)->get(route('isms.readiness'))->assertOk();

        $assessment = $response->viewData('assessment');
        $this->assertTrue($assessment['is_self_assessment']);
        // Eine frische Org ohne Daten ist NICHT auditbereit (SoA leer ⇒ rote Domäne).
        $this->assertFalse($assessment['audit_ready']);

        // 046-Prinzip: das Ergebnis behauptet nie automatisch Konformität.
        $content = $response->getContent();
        $this->assertStringNotContainsStringIgnoringCase('zertifiziert', $content === false ? '' : $content);
    }

    // ── AVV-Kopplung (Feature 044, Welle D) ────────────────────────────────

    public function test_admin_can_link_processing_agreement(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);
        $agreement = $this->makeAgreement((int) $admin->organization_id);

        $this->actingAs($admin)
            ->from(route('isms.suppliers.index'))
            ->post(route('isms.suppliers.store'), [
                'supplier_name' => 'Acme Cloud GmbH',
                // Sqid des AVV — wird im Controller org-gescopt dekodiert/validiert.
                'processing_agreement_id' => $agreement->sqid,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('isms_supplier_assessments', [
            'supplier_name' => 'Acme Cloud GmbH',
            'organization_id' => $admin->organization_id,
            'processing_agreement_id' => $agreement->id,
        ]);
    }

    public function test_processing_agreement_link_can_be_removed(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);
        $agreement = $this->makeAgreement((int) $admin->organization_id);
        $assessment = IsmsSupplierAssessment::factory()->create([
            'organization_id' => $admin->organization_id,
            'supplier_name' => 'Acme',
            'processing_agreement_id' => $agreement->id,
        ]);

        $this->actingAs($admin)
            ->from(route('isms.suppliers.index'))
            ->put(route('isms.suppliers.update', $assessment), [
                'supplier_name' => 'Acme',
                'processing_agreement_id' => '', // Verknüpfung entfernen
            ])
            ->assertRedirect();

        $this->assertNull($assessment->refresh()->processing_agreement_id);
    }

    public function test_foreign_organization_processing_agreement_is_rejected(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-sa-avv-cross']);
        $foreignAgreement = $this->makeAgreement((int) $otherOrg->id);

        app()->instance('currentOrganization', $admin->organization);
        $this->actingAs($admin)
            ->from(route('isms.suppliers.index'))
            ->post(route('isms.suppliers.store'), [
                'supplier_name' => 'Acme',
                // Fremd-Org-AVV: org-gescopte exists-Rule lehnt ab (Cross-Tenant).
                'processing_agreement_id' => $foreignAgreement->sqid,
            ])
            ->assertSessionHasErrors('processing_agreement_id');

        $this->assertDatabaseCount('isms_supplier_assessments', 0);
    }

    public function test_detail_links_agreement_when_viewer_may_read_it(): void {
        $admin = User::factory()->admin()->create();
        $org = Organization::findOrFail($admin->organization_id);
        app()->instance('currentOrganization', $org);

        // Betrachter erhält Datenschutz-Leserecht (AVV sichtbar) über die
        // Datenschutz-Rolle.
        DataProtectionPermissions::seedOrganization($org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $admin->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);

        $agreement = $this->makeAgreement((int) $org->id, 'Sichtbares Hosting-AVV');
        IsmsSupplierAssessment::factory()->create([
            'organization_id' => $org->id,
            'processing_agreement_id' => $agreement->id,
        ]);

        // sort=review nutzt IS NULL statt FIELD() (SQLite-kompatibel).
        $this->actingAs($admin)
            ->get(route('isms.suppliers.index', ['sort' => 'review']))
            ->assertOk()
            ->assertSee('Sichtbares Hosting-AVV')
            ->assertSee(route('dataprotection.agreements.show', $agreement), false);
    }

    public function test_detail_shows_agreement_without_link_without_read_permission(): void {
        $admin = User::factory()->admin()->create();
        $org = Organization::findOrFail($admin->organization_id);
        app()->instance('currentOrganization', $org);

        // KEIN Datenschutz-Leserecht: Titel wird angezeigt, aber nicht verlinkt.
        $agreement = $this->makeAgreement((int) $org->id, 'Verstecktes Hosting-AVV');
        IsmsSupplierAssessment::factory()->create([
            'organization_id' => $org->id,
            'processing_agreement_id' => $agreement->id,
        ]);

        // sort=review nutzt IS NULL statt FIELD() (SQLite-kompatibel).
        $this->actingAs($admin)
            ->get(route('isms.suppliers.index', ['sort' => 'review']))
            ->assertOk()
            ->assertSee('Verstecktes Hosting-AVV')
            ->assertDontSee(route('dataprotection.agreements.show', $agreement), false);
    }

    private function makeAgreement(int $orgId, string $title = 'Hosting-AVV'): ProcessingAgreement {
        $processor = Processor::create([
            'organization_id' => $orgId,
            'name' => 'Cloud GmbH',
            'role' => ProcessorRole::Processor->value,
        ]);

        return ProcessingAgreement::create([
            'organization_id' => $orgId,
            'processor_id' => $processor->id,
            'title' => $title,
            'version' => '1.0',
            'status' => AgreementStatus::Active->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeAssessment(User $owner, array $overrides = []): IsmsSupplierAssessment {
        app()->instance('currentOrganization', $owner->organization);

        return IsmsSupplierAssessment::factory()->create([
            'organization_id' => $owner->organization_id,
            ...$overrides,
        ]);
    }
}
