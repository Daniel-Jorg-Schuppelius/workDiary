<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScimProvisioningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Scim;

use App\Models\{ExternalReference, Organization, ScimToken, User};
use App\Services\Scim\ScimUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 057, MVP-121: SCIM-2.0-Provisioning. Prüft Token-Auth, Anlegen/
 * Aktualisieren, Deprovisionierung (Deaktivierung + Session-/Token-Widerruf),
 * die harte Zusage „SCIM vergibt keine Rollen" (keine Admin-Eskalation), das
 * Enterprise-Gating und die Mandantentrennung.
 */
final class ScimProvisioningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private string $plain;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);
        [, $this->plain] = ScimToken::issue($this->organization->id, 'Test-IdP');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function scim(string $method, string $uri, array $body = [], ?string $token = null): TestResponse {
        $token ??= $this->plain;

        return $this->call($method, $uri, [], [], [], [
            'HTTP_Authorization' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/scim+json',
            'HTTP_ACCEPT' => 'application/scim+json',
        ], $body !== [] ? (string) json_encode($body) : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(string $email = 'jane@example.com'): array {
        return [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
            'userName' => $email,
            'name' => ['givenName' => 'Jane', 'familyName' => 'Doe'],
            'active' => true,
            'externalId' => 'idp-123',
        ];
    }

    public function test_requires_valid_bearer_token(): void {
        $this->getJson('/scim/v2/Users')->assertStatus(401);
        $this->scim('GET', '/scim/v2/Users', token: 'wrong')->assertStatus(401);
    }

    public function test_creates_user_with_external_reference(): void {
        $response = $this->scim('POST', '/scim/v2/Users', $this->userPayload());

        $response->assertStatus(201)
            ->assertJsonPath('active', true)
            ->assertJsonPath('userName', 'jane@example.com')
            ->assertJsonPath('externalId', 'idp-123');

        $user = User::query()->where('email', 'jane@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame($this->organization->id, $user->organization_id);
        $this->assertTrue($user->is_new_system);
        $this->assertNull($user->deactivated_at);
        $this->assertSame(1, ExternalReference::query()
            ->where('plugin_id', ScimUserService::PLUGIN_ID)
            ->where('external_type', ScimUserService::EXT_TYPE)
            ->where('external_id', 'idp-123')
            ->count());
    }

    public function test_scim_never_assigns_any_role(): void {
        $this->scim('POST', '/scim/v2/Users', $this->userPayload());
        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

        // SCIM ist NICHT führend für Rollen — insbesondere nie Plattform-Admin.
        $this->assertSame(0, DB::table('model_has_roles')->where('model_id', $user->id)->count());
    }

    public function test_duplicate_username_conflicts(): void {
        $this->scim('POST', '/scim/v2/Users', $this->userPayload())->assertStatus(201);
        $this->scim('POST', '/scim/v2/Users', $this->userPayload())
            ->assertStatus(409)
            ->assertJsonPath('scimType', 'uniqueness');
    }

    public function test_filter_by_username_returns_list(): void {
        $this->scim('POST', '/scim/v2/Users', $this->userPayload())->assertStatus(201);

        $this->scim('GET', '/scim/v2/Users?filter=' . rawurlencode('userName eq "jane@example.com"'))
            ->assertStatus(200)
            ->assertJsonPath('totalResults', 1)
            ->assertJsonPath('Resources.0.userName', 'jane@example.com');
    }

    public function test_patch_active_false_deactivates_and_revokes_access(): void {
        $create = $this->scim('POST', '/scim/v2/Users', $this->userPayload());
        $id = (string) $create->json('id');
        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

        // Bestehende Session + API-Token, die beim Deprovisionieren erlöschen müssen.
        $user->createToken('cli');
        DB::table('sessions')->insert(['id' => 'sess-jane', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => 1_700_000_000]);

        $this->scim('PATCH', '/scim/v2/Users/' . $id, [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
            'Operations' => [['op' => 'replace', 'path' => 'active', 'value' => false]],
        ])->assertStatus(200)->assertJsonPath('active', false);

        $user->refresh();
        $this->assertNotNull($user->deactivated_at);
        $this->assertFalse($user->canLogin());
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_delete_deprovisions_but_retains_data(): void {
        $create = $this->scim('POST', '/scim/v2/Users', $this->userPayload());
        $id = (string) $create->json('id');

        $this->scim('DELETE', '/scim/v2/Users/' . $id)->assertStatus(204);

        $user = User::query()->where('email', 'jane@example.com')->first();
        $this->assertNotNull($user); // Daten bleiben (kein Löschen)
        $this->assertNotNull($user->deactivated_at);
    }

    public function test_patch_active_true_reactivates(): void {
        $create = $this->scim('POST', '/scim/v2/Users', array_merge($this->userPayload(), ['active' => false]));
        $id = (string) $create->json('id');
        $this->assertNotNull(User::query()->where('email', 'jane@example.com')->firstOrFail()->deactivated_at);

        $this->scim('PATCH', '/scim/v2/Users/' . $id, [
            'Operations' => [['op' => 'replace', 'path' => 'active', 'value' => true]],
        ])->assertStatus(200)->assertJsonPath('active', true);

        $this->assertNull(User::query()->where('email', 'jane@example.com')->firstOrFail()->deactivated_at);
    }

    public function test_enterprise_gating_blocks_non_enterprise_plan(): void {
        $this->organization->forceFill(['plan' => Organization::PLAN_FREE])->save();

        $this->scim('GET', '/scim/v2/Users')->assertStatus(403);
    }

    public function test_tenant_isolation_hides_foreign_users(): void {
        $otherOrg = Organization::factory()->create(['plan' => Organization::PLAN_ENTERPRISE]);
        $foreign = User::query()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Foreign',
            'email' => 'foreign@example.com',
            'password' => 'x',
        ]);

        $this->scim('GET', '/scim/v2/Users/' . $foreign->sqid)->assertStatus(404);
    }

    public function test_bulk_processes_mixed_operations_with_bulkid_reference(): void {
        $response = $this->scim('POST', '/scim/v2/Bulk', [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:BulkRequest'],
            'Operations' => [
                ['method' => 'POST', 'path' => '/Users', 'bulkId' => 'u1', 'data' => $this->userPayload('bulk@example.com')],
                ['method' => 'PATCH', 'path' => '/Users/bulkId:u1', 'data' => [
                    'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
                    'Operations' => [['op' => 'replace', 'path' => 'active', 'value' => false]],
                ]],
            ],
        ]);

        $response->assertOk();
        $ops = $response->json('Operations');
        $this->assertSame('201', $ops[0]['status']);
        $this->assertSame('200', $ops[1]['status']);

        $user = User::query()->where('email', 'bulk@example.com')->firstOrFail();
        $this->assertNotNull($user->deactivated_at); // PATCH via bulkId:u1 deaktiviert
    }

    public function test_bulk_reference_to_failed_post_is_rejected(): void {
        $response = $this->scim('POST', '/scim/v2/Bulk', [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:BulkRequest'],
            'Operations' => [
                // userName fehlt → POST scheitert (400) → bulkId u1 bleibt unaufgelöst.
                ['method' => 'POST', 'path' => '/Users', 'bulkId' => 'u1', 'data' => ['schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User']]],
                ['method' => 'PATCH', 'path' => '/Users/bulkId:u1', 'data' => [
                    'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
                    'Operations' => [['op' => 'replace', 'path' => 'active', 'value' => false]],
                ]],
            ],
        ]);

        $response->assertOk();
        $ops = $response->json('Operations');
        $this->assertSame('400', $ops[0]['status']);
        $this->assertSame('400', $ops[1]['status']);
        $this->assertSame('invalidValue', $ops[1]['response']['scimType']);
    }

    public function test_bulk_rejects_too_many_operations(): void {
        $operations = array_fill(0, 101, ['method' => 'POST', 'path' => '/Users', 'data' => []]);

        $this->scim('POST', '/scim/v2/Bulk', [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:BulkRequest'],
            'Operations' => $operations,
        ])->assertStatus(413);
    }

    public function test_service_provider_config_advertises_bulk(): void {
        $this->scim('GET', '/scim/v2/ServiceProviderConfig')
            ->assertOk()
            ->assertJsonPath('bulk.supported', true)
            ->assertJsonPath('bulk.maxOperations', 100);
    }
}
