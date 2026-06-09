<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanDowngradeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Customer, Organization, PlanModuleGrace, User, Vehicle};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Downgrade-Lebenszyklus: Karenz anlegen/aufheben, Zugriff waehrend Karenz,
 * Sperre nach Ablauf, sowie Purge mit Aufbewahrungs-Schutz, Dry-Run und
 * Org-Isolation.
 */
class PlanDowngradeTest extends TestCase {
    use RefreshDatabase;

    private function expireGrace(Organization $org, ?string $module = null): void {
        $q = PlanModuleGrace::query()->where('organization_id', $org->id);
        if ($module !== null) {
            $q->where('module', $module);
        }
        $q->update(['grace_until' => Carbon::now()->subDay()]);
    }

    public function test_downgrade_creates_grace_for_lost_modules(): void {
        $org = Organization::factory()->enterprise()->create();

        $org->update(['plan' => Organization::PLAN_FREE]);

        $this->assertDatabaseHas('plan_module_grace', [
            'organization_id' => $org->id,
            'module' => 'module.fuhrpark',
            'purged_at' => null,
        ]);
        $grace = PlanModuleGrace::where('organization_id', $org->id)->where('module', 'module.fuhrpark')->firstOrFail();
        $this->assertTrue(
            $grace->grace_until->between(Carbon::now()->addDays(29), Carbon::now()->addDays(31)),
            'Karenz ~30 Tage.'
        );
    }

    public function test_upgrade_cancels_pending_grace(): void {
        $org = Organization::factory()->enterprise()->create();
        $org->update(['plan' => Organization::PLAN_FREE]);
        $this->assertNotSame(0, PlanModuleGrace::where('organization_id', $org->id)->count());

        $org->update(['plan' => Organization::PLAN_ENTERPRISE]);

        $this->assertSame(
            0,
            PlanModuleGrace::where('organization_id', $org->id)->whereNull('purged_at')->count(),
            'Re-Upgrade hebt offene Karenz auf.'
        );
    }

    public function test_access_is_cut_immediately_on_downgrade(): void {
        $org = Organization::factory()->enterprise()->create();
        $org->update(['plan' => Organization::PLAN_FREE]); // Karenz laeuft NUR fuer die Daten
        $user = User::factory()->create(['organization_id' => $org->id]);

        // Zugriff ist sofort weg, unabhaengig von der laufenden Daten-Karenz.
        $this->actingAs($user)->get(route('vehicles.index'))->assertStatus(423);
    }

    public function test_grace_banner_renders_for_downgraded_org(): void {
        $org = Organization::factory()->enterprise()->create();
        $org->update(['plan' => Organization::PLAN_FREE]);
        $user = User::factory()->create(['organization_id' => $org->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Geplante Datenlöschung nach Downgrade');
        $response->assertSee('Fuhrpark'); // purgebar → im Banner
        $response->assertDontSee('Hinweisgebersystem'); // aufbewahrungspflichtig → NICHT im Banner
    }

    public function test_reupgrade_restores_access(): void {
        $org = Organization::factory()->enterprise()->create();
        $org->update(['plan' => Organization::PLAN_FREE]);
        $org->update(['plan' => Organization::PLAN_ENTERPRISE]); // Upgrade vor Stichtag
        $user = User::factory()->create(['organization_id' => $org->id]);

        $response = $this->actingAs($user)->get(route('vehicles.index'));

        $this->assertNotSame(423, $response->status(), 'Re-Upgrade stellt den Zugriff wieder her.');
    }

    public function test_purge_deletes_purgeable_module_data_after_expiry(): void {
        $org = Organization::factory()->enterprise()->create();
        $vehicle = Vehicle::factory()->create(['organization_id' => $org->id]);
        $org->update(['plan' => Organization::PLAN_FREE]);
        $this->expireGrace($org);

        $this->artisan('plans:purge')->assertExitCode(0);

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
        $this->assertNotNull(
            PlanModuleGrace::where('organization_id', $org->id)->where('module', 'module.fuhrpark')->value('purged_at')
        );
    }

    public function test_purge_never_deletes_retention_module_data(): void {
        $org = Organization::factory()->enterprise()->create();
        $customer = Customer::factory()->create(['organization_id' => $org->id]);
        $org->update(['plan' => Organization::PLAN_FREE]);
        $this->expireGrace($org);

        $this->artisan('plans:purge')->assertExitCode(0);

        // module.vertrieb ist aufbewahrungspflichtig → Daten bleiben erhalten.
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_dry_run_deletes_nothing(): void {
        $org = Organization::factory()->enterprise()->create();
        $vehicle = Vehicle::factory()->create(['organization_id' => $org->id]);
        $org->update(['plan' => Organization::PLAN_FREE]);
        $this->expireGrace($org);

        $this->artisan('plans:purge', ['--dry-run' => true])->assertExitCode(0);

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id]);
        $this->assertNull(
            PlanModuleGrace::where('organization_id', $org->id)->where('module', 'module.fuhrpark')->value('purged_at')
        );
    }

    public function test_purge_is_organization_scoped(): void {
        $orgA = Organization::factory()->enterprise()->create();
        $orgB = Organization::factory()->enterprise()->create();
        $vehicleA = Vehicle::factory()->create(['organization_id' => $orgA->id]);
        $vehicleB = Vehicle::factory()->create(['organization_id' => $orgB->id]);
        $orgA->update(['plan' => Organization::PLAN_FREE]);
        $this->expireGrace($orgA);

        $this->artisan('plans:purge', ['--org' => $orgA->id])->assertExitCode(0);

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicleA->id]);
        $this->assertDatabaseHas('vehicles', ['id' => $vehicleB->id]);
    }
}
