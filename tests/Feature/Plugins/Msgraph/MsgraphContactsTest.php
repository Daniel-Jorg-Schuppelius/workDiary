<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphContactsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Msgraph;

use App\Models\{Customer, ExternalReference, MsgraphContactConnection, User};
use App\Plugins\Contracts\{ContactSyncer, PluginCapability};
use App\Plugins\Msgraph\MsgraphPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Kontakt-Push (Feature 102, Schnitt D): fünfter OAuth-Grant
 * (`Contacts.ReadWrite`), idempotenter Push Kunde → Outlook-Kontakt
 * (Create mit Immutable-ID, Update statt Dublette, 404 ⇒ Neuanlage),
 * ExternalReference-Nachweis, Kundenakten-Button über den View-Slot.
 */
final class MsgraphContactsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        config()->set('plugins.msgraph.enabled', true);
        config()->set('plugins.msgraph.client_id', 'test-client');
        config()->set('plugins.msgraph.client_secret', 'test-secret');
    }

    /** @param array<string, mixed> $attributes */
    private function connection(array $attributes = []): MsgraphContactConnection {
        return MsgraphContactConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-1',
            'status' => MsgraphContactConnection::STATUS_ACTIVE,
        ]);
    }

    private function customer(): Customer {
        return Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme GmbH',
            'contact_name' => 'Erika Beispiel',
            'email' => 'erika@acme.test',
            'phone' => '+49 30 123456',
        ]);
    }

    public function test_plugin_advertises_contact_sync_capability(): void {
        $plugin = new MsgraphPlugin();

        $this->assertContains(PluginCapability::ContactSync, $plugin->capabilities());
        $this->assertInstanceOf(ContactSyncer::class, $plugin);
    }

    public function test_oauth_start_requests_contacts_scope(): void {
        $response = $this->actingAs($this->admin)->post(route('admin.msgraph.contacts.oauth.start'));
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString('Contacts.ReadWrite', urldecode($location));
        $this->assertStringContainsString('offline_access', urldecode($location));
    }

    public function test_push_creates_contact_with_reference_and_immutable_id_preference(): void {
        $this->connection();
        $customer = $this->customer();

        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/contacts' => FakePluginHttp::response(['id' => 'kontakt-1'], 201),
        ]);

        $externalId = (new MsgraphPlugin())->pushContact($customer);

        $this->assertSame('kontakt-1', $externalId);
        $fake->assertSent(function ($request): bool {
            /** @var array{displayName?: string, companyName?: string, emailAddresses?: list<array{address?: string}>, businessPhones?: list<string>} $payload */
            $payload = (array) json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST'
                && str_contains($request->getHeaderLine('Prefer'), 'ImmutableId')
                && ($payload['displayName'] ?? null) === 'Erika Beispiel'
                && ($payload['fileAs'] ?? null) === 'Acme GmbH'
                && (($payload['emailAddresses'][0]['address'] ?? null) === 'erika@acme.test')
                && (($payload['businessPhones'][0] ?? null) === '+49 30 123456');
        });

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => MsgraphPlugin::ID,
            'external_type' => MsgraphPlugin::EXT_TYPE_CONTACT,
            'external_id' => 'kontakt-1',
            'referenceable_id' => $customer->id,
        ]);
        $this->assertNotNull(MsgraphContactConnection::query()->firstOrFail()->last_pushed_at);
    }

    public function test_second_push_updates_instead_of_duplicating(): void {
        $this->connection();
        $customer = $this->customer();
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => MsgraphPlugin::ID,
            'external_type' => MsgraphPlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => 'kontakt-1',
        ]);

        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/contacts/kontakt-1' => FakePluginHttp::response(['id' => 'kontakt-1']),
        ]);

        $externalId = (new MsgraphPlugin())->pushContact($customer);

        $this->assertSame('kontakt-1', $externalId);
        $fake->assertNotSent(fn ($request): bool => $request->getMethod() === 'POST'
            && str_ends_with((string) $request->getUri(), '/me/contacts'));
    }

    public function test_remote_deleted_contact_is_recreated_on_push(): void {
        $this->connection();
        $customer = $this->customer();
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => MsgraphPlugin::ID,
            'external_type' => MsgraphPlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => 'kontakt-alt',
        ]);

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/contacts/kontakt-alt' => FakePluginHttp::response(['error' => ['code' => 'ErrorItemNotFound']], 404),
            'https://graph.microsoft.com/v1.0/me/contacts' => FakePluginHttp::response(['id' => 'kontakt-neu'], 201),
        ]);

        $externalId = (new MsgraphPlugin())->pushContact($customer);

        $this->assertSame('kontakt-neu', $externalId);
        $this->assertDatabaseHas('external_references', ['external_id' => 'kontakt-neu', 'referenceable_id' => $customer->id]);
        $this->assertSame(1, ExternalReference::query()->where('external_type', MsgraphPlugin::EXT_TYPE_CONTACT)->count());
    }

    public function test_customer_page_shows_push_button_and_route_pushes(): void {
        $this->connection();
        $customer = $this->customer();

        // Slot-Button erscheint nur mit aktiver Verbindung.
        $this->actingAs($this->admin)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee(route('customers.msgraph.contact.push', $customer), false);

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/contacts' => FakePluginHttp::response(['id' => 'kontakt-7'], 201),
        ]);

        $this->actingAs($this->admin)
            ->post(route('customers.msgraph.contact.push', $customer))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_push_without_connection_fails_cleanly(): void {
        $customer = $this->customer();

        $this->actingAs($this->admin)
            ->post(route('customers.msgraph.contact.push', $customer))
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
