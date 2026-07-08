<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionReviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Enums\Document\{DocumentStatus, DocumentType};
use App\Models\{AuditLog, Document, Organization, TimeExport, User};
use App\Models\Privacy\RetentionProposal;
use App\Services\Privacy\Retention\{RetentionRegistry, RetentionScanService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Restpunkte 66+67: Retention-Scan erzeugt VORSCHLÄGE statt zu löschen;
 * GoBD-Ausnahmen verhindern Vorschläge; zweistufiger Bestätigungspfad
 * (approve → purge) löscht erst danach; Fristen folgen dem Rechtsraum
 * (legal_region, Fallback DE); Scan ist idempotent.
 */
final class RetentionReviewTest extends TestCase {
    use RefreshDatabase;

    protected function tearDown(): void {
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function makeOrg(string $region = 'DE'): Organization {
        $org = Organization::factory()->create(['legal_region' => $region]);
        app()->instance('currentOrganization', $org);

        return $org;
    }

    private function oldExport(Organization $org): TimeExport {
        $export = TimeExport::query()->create([
            'organization_id' => $org->id,
            'profile' => 'generic',
            'period_year' => 2015,
            'period_month' => 6,
            'scope' => 'organization',
            'status' => 'ready',
        ]);
        TimeExport::query()->whereKey($export->id)->update(['created_at' => now()->subYears(11)]);

        return $export->refresh();
    }

    public function test_scan_proposes_instead_of_deleting(): void {
        $org = $this->makeOrg();
        $export = $this->oldExport($org);

        $result = app(RetentionScanService::class)->scan($org);

        $this->assertSame(1, $result['proposed']);
        $this->assertNotNull($export->fresh(), 'Datensatz darf beim Scan NICHT gelöscht werden.');
        $proposal = RetentionProposal::query()->firstOrFail();
        $this->assertSame('exports', $proposal->area);
        $this->assertSame(RetentionProposal::STATUS_PENDING, $proposal->status);

        // Idempotenz: zweiter Scan erzeugt keinen Doppel-Vorschlag.
        $again = app(RetentionScanService::class)->scan($org);
        $this->assertSame(0, $again['proposed']);
        $this->assertSame(1, RetentionProposal::query()->count());
    }

    public function test_gobd_exemption_blocks_active_invoice_documents(): void {
        $org = $this->makeOrg();
        $user = User::factory()->create(['organization_id' => $org->id]);

        $active = Document::query()->create([
            'organization_id' => $org->id,
            'title' => 'Alte Rechnung (aktiv)',
            'document_type' => DocumentType::Invoice->value,
            'status' => DocumentStatus::Active->value,
            'created_by_user_id' => $user->id,
        ]);
        Document::query()->whereKey($active->id)->update(['created_at' => now()->subYears(11)]);

        $archived = Document::query()->create([
            'organization_id' => $org->id,
            'title' => 'Alte Rechnung (archiviert)',
            'document_type' => DocumentType::Invoice->value,
            'status' => DocumentStatus::Archived->value,
            'created_by_user_id' => $user->id,
        ]);
        Document::query()->whereKey($archived->id)->update(['created_at' => now()->subYears(11)]);

        $result = app(RetentionScanService::class)->scan($org);

        $this->assertSame(1, $result['proposed']);
        $this->assertSame(1, $result['exempt']);
        $this->assertSame(
            (string) $archived->id,
            (string) RetentionProposal::query()->where('area', 'documents_invoice')->firstOrFail()->subject_id,
        );
    }

    public function test_two_step_confirmation_then_purge(): void {
        $org = $this->makeOrg();
        $export = $this->oldExport($org);
        app(RetentionScanService::class)->scan($org);
        $proposal = RetentionProposal::query()->firstOrFail();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $service = app(RetentionScanService::class);

        // Purge ohne Bestätigung ist gesperrt.
        try {
            $service->purge($proposal, $admin);
            $this->fail('Purge ohne approve muss scheitern.');
        } catch (\RuntimeException) {
            // erwartet
        }

        $service->approve($proposal->fresh(), $admin);
        $this->assertNotNull($export->fresh(), 'approve löscht noch nicht.');

        $service->purge($proposal->fresh(), $admin);
        $this->assertNull($export->fresh());
        $this->assertSame(RetentionProposal::STATUS_PURGED, $proposal->fresh()->status);
        $this->assertSame(1, AuditLog::query()->where('event', 'retention.approved')->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'retention.purged')->count());
    }

    public function test_region_switch_changes_cutoff(): void {
        $de = $this->makeOrg('DE');
        $registry = app(RetentionRegistry::class);
        $this->assertSame(10, $registry->yearsFor($de, 'exports'));

        $at = Organization::factory()->create(['legal_region' => 'AT']);
        $this->assertSame(7, $registry->yearsFor($at, 'exports'));
        $this->assertStringContainsString('BAO', (string) $registry->basisFor($at, 'exports'));

        // Unbekannte Region → DE-Fallback.
        $xx = Organization::factory()->create(['legal_region' => 'XX']);
        $this->assertSame(10, $registry->yearsFor($xx, 'exports'));
    }

    public function test_review_page_renders_and_rejects_keep_data(): void {
        $org = $this->makeOrg();
        $export = $this->oldExport($org);
        app(RetentionScanService::class)->scan($org);
        \App\Services\Privacy\DataProtectionPermissions::seedOrganization($org);
        $admin = User::factory()->create(['organization_id' => $org->id]);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $admin->assignRole(\App\Services\Privacy\DataProtectionPermissions::ROLE_DATENSCHUTZ);
        $proposal = RetentionProposal::query()->firstOrFail();

        $this->actingAs($admin)->get(route('dataprotection.retention.index'))
            ->assertOk()
            ->assertSee('Lohn-/Zeitexporte');

        $this->actingAs($admin)
            ->post(route('dataprotection.retention.decide', $proposal), ['action' => 'reject'])
            ->assertRedirect();

        $this->assertSame(RetentionProposal::STATUS_REJECTED, $proposal->fresh()->status);
        $this->assertNotNull($export->fresh());
    }
}
