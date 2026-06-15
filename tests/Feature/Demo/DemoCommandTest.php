<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Demo;

use App\Models\{Customer, DiaryEntry, Organization, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCommandTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    public function test_demo_seed_command_creates_end_to_end_demo_org(): void {
        $org = Organization::factory()->create(['is_demo' => false]);
        User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->artisan('demo:seed', ['org' => $org->id, '--industry' => 'elektro'])
            ->assertSuccessful();

        $org->refresh();
        $this->assertTrue((bool) $org->is_demo);
        $this->assertNotNull($org->demo_seeded_at);

        $this->assertTrue(Customer::query()->withoutGlobalScopes()
            ->where('organization_id', $org->id)->where('name', 'Wohnbau Muster eG')->exists());
        $this->assertGreaterThanOrEqual(26, DiaryEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $org->id)->count());
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'event' => 'demo.seeded',
        ]);
    }

    public function test_demo_reset_command_skips_non_demo_organization(): void {
        $realOrg = Organization::factory()->create(['is_demo' => false]);
        $admin = User::factory()->admin()->create(['organization_id' => $realOrg->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $realOrg->id,
            'created_by' => $admin->id,
            'name' => 'Echtkunde AG',
        ]);

        $this->artisan('demo:reset', ['org' => $realOrg->id])
            ->expectsOutputToContain('Übersprungen')
            ->assertSuccessful();

        // Echtdaten unberührt, kein Reset-Audit.
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'organization_id' => $realOrg->id,
            'name' => 'Echtkunde AG',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'organization_id' => $realOrg->id,
            'event' => 'demo.reset',
        ]);
        $this->assertFalse((bool) $realOrg->fresh()->is_demo);
    }

    public function test_demo_reset_all_only_targets_demo_orgs(): void {
        $demoOrg = Organization::factory()->create(['is_demo' => false]);
        User::factory()->admin()->create(['organization_id' => $demoOrg->id]);
        $this->artisan('demo:seed', ['org' => $demoOrg->id])->assertSuccessful();

        $realOrg = Organization::factory()->create(['is_demo' => false]);
        $admin = User::factory()->admin()->create(['organization_id' => $realOrg->id]);
        Customer::factory()->create([
            'organization_id' => $realOrg->id,
            'created_by' => $admin->id,
            'name' => 'Bleibt-Bestehen GmbH',
        ]);

        $this->artisan('demo:reset', ['--all' => true])->assertSuccessful();

        // Demo-Org wurde zurückgesetzt (Audit), echte Org nicht.
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $demoOrg->id,
            'event' => 'demo.reset',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'organization_id' => $realOrg->id,
            'event' => 'demo.reset',
        ]);
        $this->assertTrue(Customer::query()->withoutGlobalScopes()
            ->where('organization_id', $realOrg->id)->where('name', 'Bleibt-Bestehen GmbH')->exists());
    }
}
