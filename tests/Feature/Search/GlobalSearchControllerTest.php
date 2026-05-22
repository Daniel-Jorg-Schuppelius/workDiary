<?php
/*
 * Created on   : Sun Nov 23 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlobalSearchControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Search;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class GlobalSearchControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_short_query_returns_empty_groups(): void {
        $this->actingAs($this->user)
            ->getJson(route('api.internal.search', ['q' => 'a']))
            ->assertOk()
            ->assertJson(['groups' => []]);
    }

    public function test_finds_customers_and_projects_in_org(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme Industries GmbH',
        ]);
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Acme Webportal',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.internal.search', ['q' => 'acme']))
            ->assertOk();

        $data = $response->json();
        $keys = collect($data['groups'])->pluck('key')->all();
        $this->assertContains('customers', $keys);
        $this->assertContains('projects', $keys);

        $customerGroup = collect($data['groups'])->firstWhere('key', 'customers');
        $this->assertSame('Acme Industries GmbH', $customerGroup['items'][0]['title']);

        $projectGroup = collect($data['groups'])->firstWhere('key', 'projects');
        $this->assertSame('Acme Webportal', $projectGroup['items'][0]['title']);
        $this->assertSame('Acme Industries GmbH', $projectGroup['items'][0]['subtitle']);
    }

    public function test_does_not_leak_across_organizations(): void {
        $otherOrg = \App\Models\Organization::factory()->create();
        Customer::factory()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Foreign Kunde XYZ',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.internal.search', ['q' => 'foreign']))
            ->assertOk();

        $this->assertSame([], $response->json('groups'));
    }

    public function test_requires_authentication(): void {
        $this->getJson(route('api.internal.search', ['q' => 'acme']))
            ->assertUnauthorized();
    }
}
