<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sustainability;

use App\Enums\User\UserRole;
use App\Models\{Organization, User};
use App\Models\Sustainability\{SustainabilityActivityRecord, SustainabilityCriterion, SustainabilityFactorSet, SustainabilityMeasure};
use App\Services\Sustainability\{EmissionCalculationService, SustainabilityAssessmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 071, MVP-224–231: Faktor-Auflösung stichtagsbezogen mit
 * Org-Override (Stichtagstest, Definition-of-Flexibility Nr. 7),
 * fehlende Faktoren warnen statt still 0, Bewertung mit erklärbarem
 * Score + Snapshot-Freeze + Versionierung, Wirksamkeitsprüfung erst
 * nach Umsetzung, Zielpfad-Interpolation; Rechte-/Tenant-Schutz.
 */
final class SustainabilityLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function globalFactorSet(): SustainabilityFactorSet {
        $set = SustainabilityFactorSet::query()->create([
            'organization_id' => null, 'name' => 'Standard', 'source' => 'UBA', 'region' => 'DE', 'year' => 2026, 'active' => true,
        ]);
        $set->factors()->create([
            'activity_code' => 'electricity_kwh', 'label' => 'Strom', 'unit_code' => 'kg_co2e_per_kwh',
            'factor' => '0.400000', 'scope' => 2, 'valid_from' => '2025-01-01', 'valid_to' => '2025-12-31', 'quality' => 'high',
        ]);
        $set->factors()->create([
            'activity_code' => 'electricity_kwh', 'label' => 'Strom', 'unit_code' => 'kg_co2e_per_kwh',
            'factor' => '0.344000', 'scope' => 2, 'valid_from' => '2026-01-01', 'quality' => 'high',
        ]);

        return $set;
    }

    public function test_factor_resolution_is_effective_dated_with_org_override(): void {
        $this->globalFactorSet();
        $service = app(EmissionCalculationService::class);

        // Stichtagswechsel: 2025er Periode nutzt den alten Satz, 2026 den neuen.
        $old = $service->resolveFactor($this->organization->id, 'electricity_kwh', new \DateTimeImmutable('2025-06-30'));
        $new = $service->resolveFactor($this->organization->id, 'electricity_kwh', new \DateTimeImmutable('2026-06-30'));
        $this->assertSame('0.400000', (string) $old?->factor);
        $this->assertSame('0.344000', (string) $new?->factor);

        // Org-Override gewinnt vor dem globalen Set (P1).
        $orgSet = SustainabilityFactorSet::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'Org-Override', 'region' => 'DE', 'year' => 2026, 'active' => true,
        ]);
        $orgSet->factors()->create([
            'activity_code' => 'electricity_kwh', 'label' => 'Ökostromvertrag', 'unit_code' => 'kg_co2e_per_kwh',
            'factor' => '0.050000', 'scope' => 2, 'valid_from' => '2026-01-01', 'quality' => 'high',
        ]);
        $override = $service->resolveFactor($this->organization->id, 'electricity_kwh', new \DateTimeImmutable('2026-06-30'));
        $this->assertSame('0.050000', (string) $override?->factor);
    }

    public function test_aggregate_computes_co2e_and_warns_on_missing_factors(): void {
        $this->globalFactorSet();
        SustainabilityActivityRecord::query()->create([
            'organization_id' => $this->organization->id,
            'activity_code' => 'electricity_kwh', 'amount' => '1000', 'unit' => 'kWh',
            'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
            'data_quality' => 'measured', 'created_by' => $this->admin->id,
        ]);
        SustainabilityActivityRecord::query()->create([
            'organization_id' => $this->organization->id,
            'activity_code' => 'waste_kg', 'amount' => '50', 'unit' => 'kg',
            'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
            'data_quality' => 'estimated', 'created_by' => $this->admin->id,
        ]);

        $aggregate = app(EmissionCalculationService::class)->aggregate($this->organization->id, '2026-01-01', '2026-12-31');
        $this->assertEqualsWithDelta(344.0, $aggregate['co2e_total_kg'], 0.01);
        $this->assertEqualsWithDelta(344.0, $aggregate['co2e_by_scope'][2], 0.01);
        $this->assertContains('waste_kg', $aggregate['missing_factors'], 'Fehlender Faktor muss gewarnt werden (keine stille 0).');
        $this->assertSame(1, $aggregate['quality_share']['estimated']);
    }

    public function test_assessment_scoring_snapshot_freeze_and_versioning(): void {
        foreach ([['environment', 'Energieeffizienz', 3], ['environment', 'Reparierbarkeit', 2], ['governance', 'Lieferantennachweise', 1]] as [$dimension, $label, $weight]) {
            SustainabilityCriterion::query()->create([
                'organization_id' => $this->organization->id,
                'dimension' => $dimension, 'label' => $label, 'weight' => $weight, 'active' => true,
            ]);
        }

        $service = app(SustainabilityAssessmentService::class);
        $assessment = $service->createDraft($this->organization->id, null, null, 'Akkuschrauber A', $this->admin);
        $this->assertSame(3, $assessment->items()->count());

        // Ohne Scores keine Finalisierung.
        try {
            $service->finalize($assessment, $this->admin);
            $this->fail('Finalisierung ohne Scores akzeptiert.');
        } catch (\RuntimeException) {
        }

        [$a, $b, $c] = $assessment->items()->orderBy('id')->get();
        $a->update(['score' => 4, 'data_quality' => 'measured']);
        $b->update(['score' => 2, 'data_quality' => 'estimated']);
        $c->update(['score' => 5, 'data_quality' => 'calculated']);

        $final = $service->finalize($assessment->refresh(), $this->admin);
        // (4*3 + 2*2 + 5*1) / 6 = 3.5 → grün; schwächste Qualität = estimated.
        $this->assertSame('3.50', (string) $final->total_score);
        $this->assertSame('green', $final->rating);
        $this->assertSame('estimated', $final->data_quality);
        $this->assertNotEmpty(data_get($final->snapshot, 'items'));
        $this->assertNotEmpty(data_get($final->snapshot, 'methodology.scoring'));

        // Final ist eingefroren; Änderungen laufen über eine neue Version.
        $this->actingAs($this->admin)
            ->put(route('sustainability.assessments.items.update', [$final, $a]), ['score' => 1, 'data_quality' => 'measured'])
            ->assertForbidden();
        $next = $service->newVersion($final, $this->admin);
        $this->assertSame(2, $next->version);
        $this->assertSame('draft', $next->status);
    }

    public function test_measure_effectiveness_only_after_done_and_target_path(): void {
        $measure = SustainabilityMeasure::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'LED-Umrüstung', 'effort' => 'low', 'status' => 'in_progress',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->put(route('sustainability.measures.update', $measure), [
            'status' => 'in_progress', 'effectiveness' => 'effective',
        ])->assertSessionHas('error');

        $this->actingAs($this->admin)->put(route('sustainability.measures.update', $measure), [
            'status' => 'done', 'effectiveness' => 'effective', 'effectiveness_note' => '-18 % Strom',
        ])->assertSessionHas('status');
        $fresh = $measure->fresh();
        $this->assertSame('effective', $fresh->effectiveness);
        $this->assertNotNull($fresh->reviewed_at);

        // Zielpfad: lineare Interpolation Basisjahr→Zieljahr.
        $target = \App\Models\Sustainability\SustainabilityTarget::query()->create([
            'organization_id' => $this->organization->id,
            'metric' => 'co2e_total', 'label' => 'CO2e -50 % bis 2030',
            'baseline_value' => '1000', 'baseline_year' => 2026,
            'target_value' => '500', 'target_year' => 2030, 'unit' => 'kg',
        ]);
        $this->assertSame(875.0, $target->expectedFor(2027));
        $this->assertSame(500.0, $target->expectedFor(2031));
    }

    public function test_access_control_and_tenant_isolation(): void {
        $plain = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($plain)->get(route('sustainability.index'))->assertForbidden();

        // Teamleitung darf pflegen (Fachleitung).
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);
        $lead = User::factory()->create(['organization_id' => $this->organization->id]);
        $lead->syncRoles([Role::query()->where('name', UserRole::Teamleitung->value)->where('team_id', $this->organization->id)->firstOrFail()]);
        $registrar->forgetCachedPermissions();
        $this->actingAs($lead)->get(route('sustainability.index'))->assertOk();

        // Tenant-Isolation über Assessment-Route.
        SustainabilityCriterion::query()->create(['organization_id' => $this->organization->id, 'dimension' => 'environment', 'label' => 'X', 'weight' => 1, 'active' => true]);
        $assessment = app(SustainabilityAssessmentService::class)->createDraft($this->organization->id, null, null, 'Objekt', $this->admin);
        $otherOrg = Organization::factory()->create();
        $foreign = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        app()->instance('currentOrganization', $otherOrg);
        $this->actingAs($foreign)->get(route('sustainability.assessments.show', $assessment))->assertNotFound();
    }
}
