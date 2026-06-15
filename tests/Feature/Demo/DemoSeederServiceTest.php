<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoSeederServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Demo;

use App\Enums\Demo\DemoIndustry;
use App\Models\{Asset, CommunicationNote, Customer, DiaryEntry, Material, OpenIssue, Organization, Project, Protocol, TimeEntry, User};
use App\Services\Demo\DemoSeederService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class DemoSeederServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
    }

    public function test_seed_marks_organization_as_demo_and_creates_core_records(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $counts = app(DemoSeederService::class)->seed($this->organization->fresh(), $admin);

        $org = Organization::query()->findOrFail($this->organization->id);
        $this->assertTrue($org->is_demo);
        $this->assertNotNull($org->demo_seeded_at);
        $this->assertStringEndsWith('(Demo)', $org->name);

        $this->assertSame(3, $counts['customers']);
        $this->assertSame(5, $counts['projects']);
        $this->assertSame(1, $counts['main_diary_entries']);
        $this->assertSame(3, $counts['time_entries']);
        $this->assertSame(1, $counts['open_issues']);
        $this->assertSame(25, $counts['background_diary_entries']);

        // Erweitertes End-to-End-Szenario (Feature 040).
        $this->assertSame(3, $counts['materials']);
        $this->assertSame(3, $counts['material_usages']);
        $this->assertSame(1, $counts['assets']);
        $this->assertSame(1, $counts['protocols']);
        $this->assertSame(1, $counts['communication_notes']);
        $this->assertSame(DemoIndustry::default()->value, $counts['industry']);

        // Konsistenz mit DB. Hinweis: setUpOrganization legt ggf. Default-Projekte
        // an, daher wird hier auf "mindestens" geprüft, nicht auf exakte Zahl.
        $this->assertGreaterThanOrEqual(3, Customer::query()->where('organization_id', $org->id)->count());
        $this->assertGreaterThanOrEqual(5, Project::query()->where('organization_id', $org->id)->count());
        $this->assertSame(26, DiaryEntry::query()->where('organization_id', $org->id)->count());
        $this->assertGreaterThanOrEqual(3, TimeEntry::query()->where('organization_id', $org->id)->count());
        $this->assertSame(3, Material::query()->where('organization_id', $org->id)->count());
        $this->assertSame(1, Asset::query()->where('organization_id', $org->id)->count());
        $this->assertSame(1, Protocol::query()->where('organization_id', $org->id)->count());
        $this->assertSame(1, CommunicationNote::query()->where('organization_id', $org->id)->count());

        // Branchenprofil wurde installiert (Klassifikationen vorhanden).
        $this->assertDatabaseHas('classifications', ['organization_id' => $org->id]);
    }

    public function test_industry_demo_content_differs_between_branches(): void {
        $adminA = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $itCounts = app(DemoSeederService::class)->seed($this->organization->fresh(), $adminA, DemoIndustry::ItService);
        $itTitle = DiaryEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->orderBy('id')->where('title', 'like', '%Beispielauftrag%')->value('title');

        // Zweite, separate Org für die Elektro-Branche.
        $orgB = Organization::factory()->create();
        $adminB = User::factory()->admin()->create(['organization_id' => $orgB->id]);
        $elektroCounts = app(DemoSeederService::class)->seed($orgB->fresh(), $adminB, DemoIndustry::Elektro);
        $elektroTitle = DiaryEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $orgB->id)
            ->where('title', 'like', '%Beispielauftrag%')->value('title');

        $this->assertSame('it', $itCounts['branch_profile']);
        $this->assertSame('elektro', $elektroCounts['branch_profile']);
        $this->assertNotSame($itTitle, $elektroTitle);
        $this->assertStringContainsString('Server-Migration', (string) $itTitle);
        $this->assertStringContainsString('Wallbox', (string) $elektroTitle);

        // Unterschiedliche Demo-Kunden je Branche.
        $this->assertTrue(Customer::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)->where('name', 'ACME GmbH')->exists());
        $this->assertTrue(Customer::query()->withoutGlobalScopes()
            ->where('organization_id', $orgB->id)->where('name', 'Wohnbau Muster eG')->exists());
        $this->assertFalse(Customer::query()->withoutGlobalScopes()
            ->where('organization_id', $orgB->id)->where('name', 'ACME GmbH')->exists());
    }

    public function test_reset_only_touches_demo_org_and_leaves_real_org_untouched(): void {
        // Demo-Org.
        $demoAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        app(DemoSeederService::class)->seed($this->organization->fresh(), $demoAdmin);

        // Echter Mandant mit Echtdaten — darf NIEMALS angefasst werden.
        $realOrg = Organization::factory()->create(['is_demo' => false]);
        $realAdmin = User::factory()->admin()->create(['organization_id' => $realOrg->id]);
        $realCustomer = Customer::factory()->create([
            'organization_id' => $realOrg->id,
            'created_by' => $realAdmin->id,
            'name' => 'Echtkunde AG',
        ]);

        app(DemoSeederService::class)->reset($this->organization->fresh(), $demoAdmin);

        // Echtdaten unberührt.
        $this->assertDatabaseHas('customers', [
            'id' => $realCustomer->id,
            'organization_id' => $realOrg->id,
            'name' => 'Echtkunde AG',
        ]);
        $this->assertSame(1, Customer::query()->withoutGlobalScopes()->where('organization_id', $realOrg->id)->count());
        $this->assertFalse((bool) $realOrg->fresh()->is_demo);
    }

    public function test_reset_refuses_for_non_demo_organization(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->expectException(\RuntimeException::class);
        app(DemoSeederService::class)->reset($this->organization->fresh(), $admin);
    }

    public function test_reset_removes_demo_data_and_seeds_again(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $service = app(DemoSeederService::class);
        $service->seed($this->organization->fresh(), $admin);

        $org = $this->organization->fresh();
        $this->assertTrue($org->is_demo);

        $countsAfterReset = $service->reset($org, $admin);

        $this->assertSame(3, $countsAfterReset['customers']);
        $this->assertSame(5, $countsAfterReset['projects']);
        $this->assertSame(26, DiaryEntry::query()->where('organization_id', $org->id)->count());
        $this->assertSame(1, OpenIssue::query()
            ->where('organization_id', $org->id)
            ->where('subject_type', DiaryEntry::class)
            ->count());
    }

    public function test_seed_is_deterministic_in_record_counts_across_runs(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $first = app(DemoSeederService::class)->seed($this->organization->fresh(), $admin);
        $second = app(DemoSeederService::class)->reset($this->organization->fresh(), $admin);

        $this->assertSame($first['customers'], $second['customers']);
        $this->assertSame($first['projects'], $second['projects']);
        $this->assertSame($first['background_diary_entries'], $second['background_diary_entries']);
    }
}
