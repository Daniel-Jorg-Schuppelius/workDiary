<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\AssetCompliance;

use App\Enums\Asset\AssetBlockReason;
use App\Enums\AssetCompliance\{AssetComplianceBlockMode, AssetComplianceStatus};
use App\Models\{Asset, AssetBlock, User};
use App\Models\AssetCompliance\AssetComplianceProfile;
use App\Services\Asset\AssetUsageGuard;
use App\Services\AssetCompliance\AssetComplianceService;
use Database\Seeders\AssetComplianceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 075 (MVP-282–294): Katalog-Profile mit Org-Override (P1),
 * Prüfpflichten mit Fälligkeit/Toleranz/Nachfrist, unveränderbare
 * Prüfprotokolle mit Grenzwert-Snapshot und Zertifikat (MVP-286/287),
 * Einsatzsperren über das gemeinsame Modell inkl. Verleih-Durchgriff
 * (D12/MVP-288), Maßnahmenpfad (MVP-289), externe Prüfer (MVP-290) und
 * Modul-Gating.
 */
final class AssetComplianceLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Messschieber']);
        $this->seed(AssetComplianceCatalogSeeder::class);
    }

    private function globalProfile(string $code = 'calibration_annual'): AssetComplianceProfile {
        return AssetComplianceProfile::query()
            ->whereNull('organization_id')
            ->where('code', $code)
            ->firstOrFail();
    }

    public function test_catalog_profiles_are_seeded_and_org_override_wins(): void {
        $service = app(AssetComplianceService::class);

        $effective = $service->effectiveProfiles($this->organization->id);
        $this->assertTrue($effective->contains('code', 'calibration_annual'));
        $this->assertTrue($effective->contains('code', 'hu_vehicle'));

        // Org-Profil mit gleichem Code überschreibt die globale Vorlage (P1).
        AssetComplianceProfile::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'calibration_annual',
            'name' => 'Kalibrierung (eigene Frist)',
            'inspection_kind' => 'calibration',
            'interval_months' => 6,
            'blocking_mode' => AssetComplianceBlockMode::Warn->value,
            'is_active' => true,
        ]);

        $effective = $service->effectiveProfiles($this->organization->id);
        $calibration = $effective->firstWhere('code', 'calibration_annual');
        $this->assertSame($this->organization->id, (int) $calibration->organization_id);
        $this->assertSame(6, (int) $calibration->interval_months);
    }

    public function test_assignment_computes_due_date_and_mirrors_asset(): void {
        $service = app(AssetComplianceService::class);
        $profile = $this->globalProfile();

        $assignment = $service->assign($profile, $this->asset, $this->admin, [
            'last_done_on' => now()->subMonths(6)->toDateString(),
        ]);

        $this->assertSame(
            now()->subMonths(6)->addMonthsNoOverflow(12)->toDateString(),
            $assignment->next_due_on->toDateString(),
        );
        // Spiegel: frühester Prüftermin steht am Asset (bestehende Gates).
        $this->assertSame(
            $assignment->next_due_on->toDateString(),
            $this->asset->fresh()->next_inspection_on?->toDateString(),
        );
    }

    public function test_passed_inspection_with_certificate_extends_validity_and_is_immutable(): void {
        $service = app(AssetComplianceService::class);
        $profile = $this->globalProfile(); // requires_certificate = true
        $assignment = $service->assign($profile, $this->asset, $this->admin, [
            'next_due_on' => now()->toDateString(),
        ]);

        // Zertifikatspflicht: ohne Nachweis keine bestandene Prüfung.
        try {
            $service->recordInspection($assignment, $this->admin, ['result' => 'passed']);
            $this->fail('Zertifikatspflicht muss durchgesetzt werden.');
        } catch (\InvalidArgumentException) {
            // erwartet
        }

        $event = $service->recordInspection($assignment, $this->admin, [
            'result' => 'passed',
            'signature_name' => 'Prüfer Demo',
            'certificate' => [
                'certificate_no' => 'KAL-0815',
                'issuer' => 'DAkkS-Labor',
                'issued_on' => now()->toDateString(),
            ],
        ]);

        $this->assertNotNull($event->valid_until);
        $this->assertSame(
            now()->addMonthsNoOverflow(12)->toDateString(),
            $assignment->fresh()->next_due_on->toDateString(),
        );
        $this->assertDatabaseHas('asset_calibration_certificates', ['certificate_no' => 'KAL-0815']);

        // Nachweise sind append-only (Korrektur nur versioniert).
        $event->note = 'Manipulation';
        try {
            $event->save();
            $this->fail('Update eines Prüfereignisses muss blockiert sein.');
        } catch (\RuntimeException) {
        }
        $this->assertNotSame('Manipulation', (string) $event->fresh()->note);
    }

    public function test_result_lines_snapshot_limits_and_detect_violations(): void {
        $service = app(AssetComplianceService::class);

        $profile = AssetComplianceProfile::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'gauge_check',
            'name' => 'Lehrenprüfung',
            'inspection_kind' => 'calibration',
            'interval_months' => 12,
            'blocking_mode' => AssetComplianceBlockMode::Warn->value,
            'is_active' => true,
        ]);
        $requirement = $profile->requirements()->create([
            'organization_id' => $this->organization->id,
            'label' => 'Abweichung',
            'unit' => 'mm',
            'limit_min' => '-0.0500',
            'limit_max' => '0.0500',
        ]);

        $assignment = $service->assign($profile, $this->asset, $this->admin, []);
        $event = $service->recordInspection($assignment, $this->admin, [
            'result' => 'failed',
            'follow_up' => 'repair',
            'follow_up_note' => 'Nachschleifen erforderlich.',
            'results' => [
                ['requirement_id' => $requirement->id, 'value' => 0.12],
            ],
        ]);

        $line = $event->results()->firstOrFail();
        $this->assertFalse((bool) $line->passed);
        $this->assertSame('0.0500', (string) $line->limit_max);
    }

    /** B1/MVP-007: das Prüfformular sendet Requirement-Sqids (Konvention: Sqid in Formularen). */
    public function test_record_endpoint_accepts_requirement_sqids(): void {
        $service = app(AssetComplianceService::class);

        $profile = AssetComplianceProfile::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'gauge_check_http',
            'name' => 'Lehrenprüfung (HTTP)',
            'inspection_kind' => 'calibration',
            'interval_months' => 12,
            'blocking_mode' => AssetComplianceBlockMode::Warn->value,
            'is_active' => true,
        ]);
        $requirement = $profile->requirements()->create([
            'organization_id' => $this->organization->id,
            'label' => 'Abweichung',
            'unit' => 'mm',
            'limit_min' => '-0.0500',
            'limit_max' => '0.0500',
        ]);
        $assignment = $service->assign($profile, $this->asset, $this->admin, []);

        $this->actingAs($this->admin)
            ->post(route('asset-compliance.inspections.record', $assignment), [
                'result' => 'passed',
                'results' => [
                    ['requirement_id' => $requirement->sqid, 'value' => 0.01],
                ],
            ])->assertRedirect();

        $line = $assignment->fresh()->events()->latest('id')->firstOrFail()
            ->results()->firstOrFail();
        $this->assertSame($requirement->id, (int) $line->asset_compliance_requirement_id);
        $this->assertTrue((bool) $line->passed);
    }

    public function test_failed_inspection_blocks_asset_across_modules(): void {
        $service = app(AssetComplianceService::class);
        $profile = $this->globalProfile('uvv_general');
        $assignment = $service->assign($profile, $this->asset, $this->admin, []);

        $service->recordInspection($assignment, $this->admin, [
            'result' => 'failed',
            'note' => 'Schutzeinrichtung defekt',
        ]);

        // Sperre im gemeinsamen Modell (D12) …
        $this->assertDatabaseHas('asset_blocks', [
            'asset_id' => $this->asset->id,
            'reason' => AssetBlockReason::InspectionFailed->value,
        ]);
        $this->assertSame(AssetComplianceStatus::Blocked, $service->statusFor($this->asset));

        // … wirkt in Einsatz UND Verleih (MVP-288).
        $this->assertFalse(app(AssetUsageGuard::class)->isUsable($this->asset, 'usage'));
        \App\Models\Rental\RentalProfile::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $this->asset->id,
            'is_rentable' => true,
        ]);
        $this->assertFalse(app(\App\Services\Rental\RentalAvailabilityService::class)
            ->isAvailable($this->asset, now()->addDay(), now()->addDays(2)));

        // Bestandene Nachprüfung hebt die Prüfsperre auf.
        $service->recordInspection($assignment->fresh(), $this->admin, ['result' => 'passed']);
        $this->assertSame(0, AssetBlock::query()->where('asset_id', $this->asset->id)->active()->count());
        $this->assertTrue(app(AssetUsageGuard::class)->isUsable($this->asset, 'usage'));
    }

    public function test_scan_blocks_overdue_assignments_after_grace_idempotently(): void {
        \Illuminate\Support\Facades\Notification::fake();
        $service = app(AssetComplianceService::class);
        $profile = $this->globalProfile(); // block_after_grace, grace 14 Tage
        $responsible = User::factory()->create(['organization_id' => $this->organization->id]);

        $service->assign($profile, $this->asset, $this->admin, [
            'next_due_on' => now()->subDays(30)->toDateString(),
            'responsible_user_id' => $responsible->id,
        ]);

        $service->scanAssignments($this->organization);
        $service->scanAssignments($this->organization);

        // Genau EINE Sperre trotz mehrfacher Läufe.
        $this->assertSame(1, AssetBlock::query()
            ->where('asset_id', $this->asset->id)
            ->where('reason', AssetBlockReason::InspectionOverdue->value)
            ->count());

        // Benachrichtigung idempotent je Pflicht.
        $this->assertSame(1, \App\Models\Notification\NotificationDispatchLog::query()
            ->where('event', \App\Enums\Notification\NotificationEvent::AssetInspectionDue->value)
            ->where('stage', \App\Models\Notification\NotificationDispatchLog::STAGE_INITIAL)
            ->count());

        $this->assertSame(AssetComplianceStatus::Blocked, $service->statusFor($this->asset));
    }

    public function test_exception_release_is_context_bound_via_http(): void {
        $service = app(AssetComplianceService::class);
        $profile = $this->globalProfile('uvv_general');
        $assignment = $service->assign($profile, $this->asset, $this->admin, []);
        $service->recordInspection($assignment, $this->admin, ['result' => 'failed']);

        $block = AssetBlock::query()->where('asset_id', $this->asset->id)->active()->firstOrFail();

        // Zu kurze Begründung wird abgelehnt.
        $this->actingAs($this->admin)->post(route('asset-compliance.blocks.exception', $block), [
            'context' => 'rental',
            'reason_text' => 'kurz',
            'valid_until' => now()->addWeek()->toDateString(),
        ])->assertSessionHasErrors();

        $this->actingAs($this->admin)->post(route('asset-compliance.blocks.exception', $block), [
            'context' => 'rental',
            'reason_text' => 'Einsatz nach Rücksprache mit der Sicherheitsfachkraft freigegeben.',
            'valid_until' => now()->addWeek()->toDateString(),
        ])->assertRedirect();

        $guard = app(AssetUsageGuard::class);
        $this->assertTrue($guard->isUsable($this->asset, 'rental'));
        $this->assertFalse($guard->isUsable($this->asset, 'dispatch'));
    }

    public function test_external_inspector_gets_limited_access_to_schedule(): void {
        $service = app(AssetComplianceService::class);
        $profile = $this->globalProfile();
        $assignment = $service->assign($profile, $this->asset, $this->admin, []);

        $schedule = \App\Models\AssetCompliance\AssetInspectionSchedule::query()->create([
            'organization_id' => $this->organization->id,
            'asset_compliance_assignment_id' => $assignment->id,
            'asset_id' => $this->asset->id,
            'due_on' => now()->addWeek()->toDateString(),
            'status' => 'planned',
        ]);

        // Einladung über den generischen Externe-Beteiligte-Weg (MVP-290).
        $this->actingAs($this->admin)->post(route('external.store', ['type' => 'inspection', 'id' => $schedule->sqid]), [
            'name' => 'TÜV Prüfdienst',
            'party' => 'inspector',
            'abilities' => ['upload', 'confirm'],
            'ttl_days' => 14,
        ])->assertRedirect();

        $participant = \App\Models\ExternalParticipant::query()
            ->where('subject_type', $schedule->getMorphClass())
            ->where('subject_id', $schedule->id)
            ->firstOrFail();
        $this->assertNotNull($participant->token_hash);
        $this->assertContains('upload', (array) $participant->abilities);
    }

    public function test_claim_follow_up_opens_linked_claim(): void {
        $service = app(AssetComplianceService::class);
        $profile = $this->globalProfile('uvv_general');
        $assignment = $service->assign($profile, $this->asset, $this->admin, []);

        $event = $service->recordInspection($assignment, $this->admin, [
            'result' => 'failed',
            'follow_up' => 'claim',
            'follow_up_note' => 'Herstellerseitiger Mangel — Gewährleistung prüfen.',
        ]);

        $claim = \App\Models\Claims\ClaimCase::query()->where('asset_id', $this->asset->id)->first();
        $this->assertNotNull($claim);
        $this->assertDatabaseHas('claim_case_links', [
            'claim_case_id' => $claim->id,
            'linkable_type' => $event->getMorphClass(),
            'linkable_id' => $event->id,
        ]);
    }

    public function test_status_derivation_walks_due_soon_and_overdue(): void {
        $service = app(AssetComplianceService::class);
        $profile = $this->globalProfile(); // warn 30 Tage, Toleranz 0

        $this->assertSame(AssetComplianceStatus::NotApplicable, $service->statusFor($this->asset));

        $assignment = $service->assign($profile, $this->asset, $this->admin, [
            'next_due_on' => now()->addMonths(3)->toDateString(),
        ]);
        $this->assertSame(AssetComplianceStatus::Valid, $service->statusFor($this->asset));

        $assignment->forceFill(['next_due_on' => now()->addDays(10)->toDateString()])->save();
        $this->assertSame(AssetComplianceStatus::DueSoon, $service->statusFor($this->asset));

        // Toleranz beachten: erst nach Toleranzablauf überfällig.
        $assignment->forceFill(['next_due_on' => now()->subDays(20)->toDateString()])->save();
        $this->assertSame(AssetComplianceStatus::Overdue, $service->statusFor($this->asset));
    }

    public function test_module_gating_blocks_without_license(): void {
        $freeOrg = \App\Models\Organization::factory()->free()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($freeOrg->id);
        $freeAdmin = User::factory()->admin()->create(['organization_id' => $freeOrg->id]);

        $this->actingAs($freeAdmin)->get(route('asset-compliance.index'))->assertStatus(423);
    }
}
