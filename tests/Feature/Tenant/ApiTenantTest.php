<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiTenantTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Tenant;

use App\Models\{Customer, DiaryEntry, Organization, Project, Task, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Die REST-API (routes/api.php) wird über Sanctum-Tokens authentifiziert.
 * Diese Suite stellt sicher, dass auch über die API-Stack-Middleware die
 * Mandantengrenze gilt: ein Token aus Organisation A darf weder Listen
 * noch einzelne Records aus Organisation B sehen oder verändern.
 *
 * Referenz: ../WorkDiary-Architecture/security/tenant-audit-2026.md (Abschnitt „API").
 */
class ApiTenantTest extends TestCase {
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $adminA;

    private User $adminB;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $this->orgA = Organization::factory()->create(['slug' => 'api-a']);
        $this->orgB = Organization::factory()->create(['slug' => 'api-b']);

        $this->adminA = User::factory()->admin()->create(['organization_id' => $this->orgA->id]);
        $this->adminB = User::factory()->admin()->create(['organization_id' => $this->orgB->id]);
    }

    public function test_api_customers_index_does_not_leak_cross_org(): void {
        $customerB = $this->withOrg($this->orgB, fn() => Customer::factory()->create([
            'name' => 'APICUSTBORG',
        ]));

        Sanctum::actingAs($this->adminA);
        $response = $this->getJson('/api/customers');
        $response->assertOk();
        $ids = collect((array) ($response->json('data') ?? $response->json()))->pluck('id')->all();
        $this->assertNotContains((int) $customerB->id, array_map('intval', $ids));
        $this->assertStringNotContainsString('APICUSTBORG', (string) $response->getContent());
    }

    public function test_api_customer_show_cross_org_is_not_found(): void {
        $customerB = $this->withOrg($this->orgB, fn() => Customer::factory()->create());

        Sanctum::actingAs($this->adminA);
        $response = $this->getJson('/api/customers/' . $customerB->id);
        $this->assertContains($response->status(), [403, 404], 'Cross-Org-Show muss 403 oder 404 liefern, war: ' . $response->status());
    }

    public function test_api_projects_index_does_not_leak_cross_org(): void {
        $customerB = $this->withOrg($this->orgB, fn() => Customer::factory()->create());
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->for($customerB)->create(['name' => 'APIPROJBORG']));

        Sanctum::actingAs($this->adminA);
        $response = $this->getJson('/api/projects');
        $response->assertOk();
        $this->assertStringNotContainsString('APIPROJBORG', (string) $response->getContent());
        $this->assertStringNotContainsString('"id":' . (int) $projectB->id . ',', (string) $response->getContent());
    }

    public function test_api_tasks_show_cross_org_is_not_found(): void {
        $customerB = $this->withOrg($this->orgB, fn() => Customer::factory()->create());
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->for($customerB)->create());
        $taskB = $this->withOrg($this->orgB, fn() => Task::factory()->for($projectB)->create([
            'created_by' => $this->adminB->id,
        ]));

        Sanctum::actingAs($this->adminA);
        $response = $this->getJson('/api/tasks/' . $taskB->id);
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_api_diary_index_does_not_leak_cross_org(): void {
        $this->withOrg($this->orgB, fn() => DiaryEntry::factory()->create([
            'user_id' => $this->adminB->id,
            'content' => 'APIDIARYBORG',
        ]));

        Sanctum::actingAs($this->adminA);
        $response = $this->getJson('/api/diary');
        $response->assertOk();
        $this->assertStringNotContainsString('APIDIARYBORG', (string) $response->getContent());
    }

    public function test_api_diary_show_cross_org_is_not_found(): void {
        $diaryB = $this->withOrg($this->orgB, fn() => DiaryEntry::factory()->create([
            'user_id' => $this->adminB->id,
        ]));

        Sanctum::actingAs($this->adminA);
        $response = $this->getJson('/api/diary/' . $diaryB->id);
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_api_diary_update_cross_org_is_blocked(): void {
        $diaryB = $this->withOrg($this->orgB, fn() => DiaryEntry::factory()->create([
            'user_id' => $this->adminB->id,
            'content' => 'ORIGINAL-B',
        ]));

        Sanctum::actingAs($this->adminA);
        $response = $this->putJson('/api/diary/' . $diaryB->id, [
            'content' => 'HIJACKED-BY-A',
            'status' => 2,
        ]);
        $this->assertContains($response->status(), [403, 404, 422]);

        $this->withOrg($this->orgB, function () use ($diaryB): void {
            $fresh = DiaryEntry::find($diaryB->id);
            $this->assertNotNull($fresh);
            $this->assertSame('ORIGINAL-B', $fresh->content);
        });
    }

    /**
     * @template T
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function withOrg(Organization $org, \Closure $callback): mixed {
        $previous = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        app()->instance('currentOrganization', $org);
        try {
            return $callback();
        } finally {
            if ($previous instanceof Organization) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }
}
