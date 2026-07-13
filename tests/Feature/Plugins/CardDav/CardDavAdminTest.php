<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\CardDav;

use App\Models\{CardDavCard, CardDavConnection, Organization, User};
use App\Plugins\CardDav\Contracts\{CardDavGateway, CardDavGatewayFactory};
use App\Plugins\CardDav\Services\CardDavAddressbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeCardDavGateway;
use Tests\TestCase;

/**
 * Bauturbo A9 (MVP-329): Admin-Panel. SSRF-Leitplanke mit auditiertem
 * Private-Network-Opt-in, Discovery mit Adressbuch-Wahl (nur entdeckte
 * Adressbücher wählbar), Passwort-Erhalt beim Speichern, Quellenwechsel
 * verwirft Sync-Stand, Org-Isolation.
 */
final class CardDavAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function bindGateway(FakeCardDavGateway $gateway): void {
        $this->app->instance(CardDavGatewayFactory::class, new class($gateway) implements CardDavGatewayFactory {
            public function __construct(private CardDavGateway $gateway) {}

            public function for(CardDavConnection $connection): CardDavGateway {
                return $this->gateway;
            }
        });
    }

    private function connection(?Organization $organization = null): CardDavConnection {
        return CardDavConnection::query()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
            'name' => 'Nextcloud',
            'base_url' => 'https://cloud.example.com/remote.php/dav',
            'username' => 'svc',
            'app_password' => 'secret',
            'active' => true,
        ]);
    }

    public function test_non_admin_cannot_open_admin_page(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('admin.carddav.index'))->assertForbidden();
    }

    public function test_private_base_url_is_blocked_without_optin(): void {
        $response = $this->actingAs($this->admin)->post(route('admin.carddav.connection.store'), [
            'name' => 'Intern',
            'base_url' => 'https://192.168.10.20/dav',
            'username' => 'svc',
            'app_password' => 'secret',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('carddav_connections', ['organization_id' => $this->organization->id]);
    }

    public function test_private_base_url_is_allowed_with_audited_optin(): void {
        $response = $this->actingAs($this->admin)->post(route('admin.carddav.connection.store'), [
            'name' => 'Intern',
            'base_url' => 'https://192.168.10.20/dav',
            'username' => 'svc',
            'app_password' => 'secret',
            'allow_private_network' => '1',
            'active' => '1',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('carddav_connections', [
            'organization_id' => $this->organization->id,
            'allow_private_network' => true,
            'active' => true,
        ]);
    }

    public function test_empty_password_keeps_existing_secret(): void {
        $this->connection();

        $this->actingAs($this->admin)->post(route('admin.carddav.connection.store'), [
            'name' => 'Nextcloud neu',
            'base_url' => 'https://cloud.example.com/remote.php/dav',
            'username' => 'svc2',
            'app_password' => '',
            'active' => '1',
        ])->assertSessionHas('success');

        $connection = CardDavConnection::query()->firstOrFail();
        $this->assertSame('secret', $connection->app_password);
        $this->assertSame('svc2', $connection->username);
        // At-rest verschlüsselt (kein Klartext in der Spalte).
        $rawValue = (string) CardDavConnection::query()->toBase()->value('app_password');
        $this->assertNotSame('secret', $rawValue);
        $this->assertSame('secret', Crypt::decryptString($rawValue));
    }

    public function test_discovery_offers_addressbooks_and_choice_is_limited_to_them(): void {
        $this->connection();
        $this->bindGateway(new FakeCardDavGateway(addressbooks: [
            new CardDavAddressbook('https://cloud.example.com/dav/ab/contacts/', 'Kontakte'),
            new CardDavAddressbook('https://cloud.example.com/dav/ab/team/', 'Team'),
        ]));

        $response = $this->actingAs($this->admin)->post(route('admin.carddav.discover'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('carddav_addressbooks');

        // Nicht entdeckte URL kann nicht untergeschoben werden (SSRF-Leitplanke).
        $this->actingAs($this->admin)
            ->withSession(['carddav_addressbooks' => [['url' => 'https://cloud.example.com/dav/ab/contacts/', 'name' => 'Kontakte']]])
            ->post(route('admin.carddav.addressbook'), ['addressbook_url' => 'https://attacker.example/dav/'])
            ->assertSessionHas('error');
        $this->assertNull(CardDavConnection::query()->firstOrFail()->addressbook_url);

        // Entdeckte URL wird übernommen.
        $this->actingAs($this->admin)
            ->withSession(['carddav_addressbooks' => [['url' => 'https://cloud.example.com/dav/ab/contacts/', 'name' => 'Kontakte']]])
            ->post(route('admin.carddav.addressbook'), ['addressbook_url' => 'https://cloud.example.com/dav/ab/contacts/'])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('carddav_connections', [
            'addressbook_url' => 'https://cloud.example.com/dav/ab/contacts/',
            'addressbook_name' => 'Kontakte',
        ]);
    }

    public function test_changing_addressbook_resets_sync_state(): void {
        $connection = $this->connection();
        $connection->forceFill([
            'addressbook_url' => 'https://cloud.example.com/dav/ab/alt/',
            'sync_token' => 'tok-alt',
        ])->save();
        CardDavCard::query()->create([
            'organization_id' => $this->organization->id,
            'carddav_connection_id' => $connection->id,
            'href' => '/dav/ab/alt/a.vcf',
            'uid' => 'uid-a',
            'etag' => 'etag-a',
        ]);

        $this->actingAs($this->admin)
            ->withSession(['carddav_addressbooks' => [['url' => 'https://cloud.example.com/dav/ab/neu/', 'name' => 'Neu']]])
            ->post(route('admin.carddav.addressbook'), ['addressbook_url' => 'https://cloud.example.com/dav/ab/neu/'])
            ->assertSessionHas('success');

        $connection->refresh();
        $this->assertSame('https://cloud.example.com/dav/ab/neu/', $connection->addressbook_url);
        $this->assertNull($connection->sync_token);
        $this->assertDatabaseMissing('carddav_cards', ['carddav_connection_id' => $connection->id]);
    }

    public function test_admin_actions_are_org_isolated(): void {
        $connection = $this->connection();

        $otherOrg = Organization::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($otherOrg->id);
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        // Der fremde Admin trennt „seine" (nicht vorhandene) Anbindung — die
        // Anbindung der ersten Organisation bleibt unangetastet.
        $this->actingAs($otherAdmin)->post(route('admin.carddav.disconnect'));

        $this->assertTrue($connection->refresh()->active);
    }

    public function test_manual_sync_requires_syncable_connection(): void {
        $this->connection(); // ohne gewähltes Adressbuch → nicht sync-fähig

        $this->actingAs($this->admin)
            ->post(route('admin.carddav.sync'))
            ->assertSessionHas('error');
    }
}
