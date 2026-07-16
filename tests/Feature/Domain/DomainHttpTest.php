<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainHttpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Domain;

use App\Enums\Domain\DomainConnectionStatus;
use App\Enums\User\Permission;
use App\Models\Domain\{DomainProjection, DomainProviderConnection};
use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeDomainResellingTransport;
use Tests\TestCase;

/**
 * HTTP-Schicht des Domain-Moduls (Feature 083): Rechte, Verbindungs-Anlage,
 * Portfolio-Ansicht, Organisations-Scoping und Plan-Modul-Gate (free → 423).
 */
class DomainHttpTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(); // Factory-Default: enterprise (module.domain enthalten)
        $this->admin = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->admin->givePermissionTo([
            Permission::DomainProviderView->value,
            Permission::DomainProviderManage->value,
            Permission::DomainViewAny->value,
            Permission::DomainView->value,
        ]);
    }

    public function test_connection_index_requires_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('admin.domain-provider.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.domain-provider.index'))->assertOk();
    }

    public function test_store_creates_and_activates_connection(): void {
        FakeDomainResellingTransport::fake(['CheckAuthentication' => "code=200\ndescription=ok\nEOF\n"]);

        $this->actingAs($this->admin)->post(route('admin.domain-provider.store'), [
            'name' => 'DR Test',
            'environment' => 'ote',
            'login' => 'reseller1',
            'password' => 'secret-pw',
        ])->assertRedirect(route('admin.domain-provider.index'));

        $connection = DomainProviderConnection::query()->where('login', 'reseller1')->firstOrFail();
        $this->assertSame(DomainConnectionStatus::Active, $connection->status);
    }

    public function test_portfolio_index_and_detail_render(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'meine.de',
            'domain_hash' => DomainProjection::hashFor('meine.de'),
        ]);

        $this->actingAs($this->admin)->get(route('domains.index'))->assertOk()->assertSee('meine.de');
        $this->actingAs($this->admin)->get(route('domains.show', $domain))->assertOk()
            ->assertSee('meine.de')
            // Rechnungs-Reiter erklärt die API-Grenze (Blocked-State).
            ->assertSee('keine', false);
    }

    public function test_other_org_domain_is_not_visible(): void {
        $otherOrg = Organization::factory()->create();
        $otherConnection = DomainProviderConnection::factory()->create(['organization_id' => $otherOrg->id]);
        $foreign = DomainProjection::factory()->create([
            'organization_id' => $otherOrg->id,
            'connection_id' => $otherConnection->id,
            'external_domain' => 'fremd.de',
            'domain_hash' => DomainProjection::hashFor('fremd.de'),
        ]);

        $this->actingAs($this->admin)->get(route('domains.show', $foreign))->assertNotFound();
    }

    public function test_free_plan_blocks_domain_module(): void {
        $freeOrg = Organization::factory()->free()->create();
        app()->instance('currentOrganization', $freeOrg);
        $user = User::factory()->user()->create(['organization_id' => $freeOrg->id]);
        $user->givePermissionTo(Permission::DomainViewAny->value);

        $this->actingAs($user)->get(route('domains.index'))->assertStatus(423);
    }
}
