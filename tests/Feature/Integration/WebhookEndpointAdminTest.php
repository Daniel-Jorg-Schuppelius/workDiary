<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookEndpointAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Integration;

use App\Enums\Integration\WebhookEvent;
use App\Models\Integration\WebhookEndpoint;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class WebhookEndpointAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
    }

    public function test_regular_user_cannot_access_index(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('admin.webhooks.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_index(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->get(route('admin.webhooks.index'))
            ->assertOk();
    }

    public function test_admin_can_create_endpoint_and_secret_shown_once(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($admin)
            ->post(route('admin.webhooks.store'), [
                'label' => 'ERP',
                'url' => 'https://example.test/hooks',
                'events' => [WebhookEvent::OpenIssueAssigned->value, WebhookEvent::SlaBreached->value],
                'active' => '1',
            ])
            ->assertRedirect(route('admin.webhooks.index'));

        // Der Klartext-Schlüssel wird genau EINMAL über die Session geflasht.
        $secret = $response->getSession()->get('webhook_secret');
        $this->assertIsString($secret);
        $this->assertSame(64, strlen($secret));

        $endpoint = WebhookEndpoint::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame('ERP', $endpoint->label);
        $this->assertEqualsCanonicalizing(
            [WebhookEvent::OpenIssueAssigned->value, WebhookEvent::SlaBreached->value],
            $endpoint->events
        );
        $this->assertSame($admin->id, $endpoint->created_by_user_id);
        // Secret entschlüsselt sich zum geflashten Klartext.
        $this->assertSame($secret, $endpoint->secret);
    }

    public function test_secret_is_encrypted_at_rest_and_never_in_response(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        // Per Factory anlegen (kein Klartext-Flash), damit die Index-Seite
        // den Schlüssel nicht über die einmalige Anzeige zeigt.
        $endpoint = WebhookEndpoint::factory()->create(['organization_id' => $this->organization->id]);
        $plaintext = $endpoint->secret;

        // DB-Rohwert ist NICHT der Klartext (encrypted at-rest).
        $raw = DB::table('webhook_endpoints')->where('id', $endpoint->id)->value('secret');
        $this->assertNotSame($plaintext, $raw);

        // JSON/Array-Serialisierung blendet das Secret aus ($hidden).
        $this->assertArrayNotHasKey('secret', $endpoint->toArray());

        // Die Index-Seite zeigt den Klartext-Schlüssel nicht.
        $body = $this->actingAs($admin)->get(route('admin.webhooks.index'))->getContent();
        $this->assertStringNotContainsString($plaintext, (string) $body);
    }

    public function test_store_requires_at_least_one_event(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->post(route('admin.webhooks.store'), [
                'label' => 'No events',
                'url' => 'https://example.test/hooks',
                'events' => [],
                'active' => '1',
            ])
            ->assertSessionHasErrors('events');
    }

    public function test_rotate_secret_changes_key_and_shows_once(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $endpoint = WebhookEndpoint::factory()->create(['organization_id' => $this->organization->id]);
        $old = $endpoint->secret;

        $response = $this->actingAs($admin)
            ->post(route('admin.webhooks.rotate-secret', $endpoint))
            ->assertRedirect(route('admin.webhooks.index'));

        $new = $response->getSession()->get('webhook_secret');
        $this->assertIsString($new);
        $this->assertNotSame($old, $new);
        $this->assertSame($new, $endpoint->fresh()->secret);
    }

    public function test_cross_org_endpoint_is_not_accessible(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $otherOrgEndpoint = WebhookEndpoint::factory()->create(); // eigene Org via factory

        $this->actingAs($admin)
            ->get(route('admin.webhooks.edit', $otherOrgEndpoint))
            ->assertNotFound();
    }
}
