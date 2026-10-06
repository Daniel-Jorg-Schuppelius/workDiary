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

use App\Enums\Domain\{DomainConnectionStatus, DomainDnsRecordType};
use App\Enums\User\Permission;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Domain\{DomainDnsRecordProjection, DomainDnsZoneProjection, DomainProjection, DomainProviderConnection, DomainResellerAccount};
use App\Models\Platform\{Organization, User};
use App\Services\Domain\DomainDnsService;
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
        FakeDomainResellingTransport::fake(['StatusUser' => "code=200\ndescription=ok\nEOF\n"]);

        $this->actingAs($this->admin)->post(route('admin.domain-provider.store'), [
            'name' => 'DR Test',
            'environment' => 'ote',
            'login' => 'reseller1',
            'password' => 'secret-pw',
        ])->assertRedirect(route('admin.domain-provider.index'));

        $connection = DomainProviderConnection::query()->where('login', 'reseller1')->firstOrFail();
        $this->assertSame(DomainConnectionStatus::Active, $connection->status);
    }

    /** UI-Fuzz 2026-09-21: dasselbe Konto ein zweites Mal verbinden endete in 1062 dpc_org_endpoint_login_uq (500). */
    public function test_connecting_the_same_login_twice_is_a_validation_error(): void {
        FakeDomainResellingTransport::fake(['StatusUser' => "code=200\ndescription=ok\nEOF\n"]);
        $payload = ['name' => 'DR Test', 'environment' => 'ote', 'login' => 'reseller1', 'password' => 'secret-pw'];

        $this->actingAs($this->admin)->post(route('admin.domain-provider.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.domain-provider.store'), $payload)->assertSessionHasErrors('login');

        $this->assertSame(1, DomainProviderConnection::query()->where('login', 'reseller1')->count());
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

    /** MVP-798 (C1-06): DNS-Einträge hinzufügen und löschen hatten Route und Dienst, aber keinen Einstieg. */
    public function test_dns_entries_can_be_added_and_deleted_from_the_detail_page(): void {
        $this->admin->givePermissionTo(Permission::DomainDnsManage->value);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'zone.de',
            'domain_hash' => DomainProjection::hashFor('zone.de'),
        ]);
        $zone = DomainDnsZoneProjection::query()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'domain_projection_id' => $domain->id,
            'zone' => 'zone.de',
            'zone_hash' => DomainDnsZoneProjection::hashFor('zone.de'),
        ]);
        DomainDnsRecordProjection::query()->create([
            'organization_id' => $this->organization->id,
            'zone_id' => $zone->id,
            'type' => DomainDnsRecordType::A,
            'name' => 'www.zone.de',
            'ttl' => 3600,
            'content' => '192.0.2.10',
            'position' => 0,
        ]);

        $this->actingAs($this->admin)->get(route('domains.show', $domain))->assertOk()
            ->assertSee('data-open-dialog="domain-dns-add"', false)
            ->assertSee('name="add[0][content]"', false)
            ->assertSee('name="delete[0][content]" value="192.0.2.10"', false);

        $this->mock(DomainDnsService::class)
            ->shouldReceive('modifyRecords')
            ->once()
            ->withArgs(fn ($connectionArg, string $zoneName, array $add, array $delete): bool => $zoneName === 'zone.de'
                && $add === [['type' => 'TXT', 'name' => 'zone.de', 'ttl' => null, 'priority' => null, 'content' => 'v=spf1 -all']]
                && $delete === [['type' => 'A', 'name' => 'www.zone.de', 'ttl' => 3600, 'priority' => null, 'content' => '192.0.2.10']])
            ->andReturn([]);

        $this->actingAs($this->admin)->from(route('domains.show', $domain))->post(route('domains.dns.modify', $domain), [
            'add' => [['type' => 'TXT', 'name' => 'zone.de', 'ttl' => '', 'priority' => '', 'content' => 'v=spf1 -all']],
            'delete' => [['type' => 'A', 'name' => 'www.zone.de', 'ttl' => '3600', 'priority' => '', 'content' => '192.0.2.10']],
        ])->assertRedirect(route('domains.show', $domain))->assertSessionHas('success');
    }

    /** Verfügbarkeit prüfen und registrieren hatten Route und Dienst, aber keinen Einstieg im Portfolio. */
    public function test_availability_check_and_registration_start_from_the_portfolio(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        // Ohne domain.register bleiben beide Einstiege weg.
        $this->actingAs($this->admin)->get(route('domains.index'))->assertOk()
            ->assertDontSee(route('domains.availability'), false)
            ->assertDontSee(route('domains.register'), false);

        $this->admin->givePermissionTo(Permission::DomainRegister->value);
        $this->actingAs($this->admin)->get(route('domains.index'))->assertOk()
            ->assertSee('data-open-dialog="domain-availability"', false)
            ->assertSee('data-open-dialog="domain-register"', false)
            ->assertSee(route('domains.availability'), false)
            ->assertSee(route('domains.register'), false);

        FakeDomainResellingTransport::fake([
            'CheckDomains' => FakeDomainResellingTransport::properties([
                ['domain' => 'belegt.de', 'status' => 'taken'],
                ['domain' => 'neu.de', 'status' => 'available', 'price' => '9.90', 'currency' => 'EUR'],
            ]),
            'AddDomain' => "code=200\ndescription=ok\nEOF\n",
        ]);

        $this->actingAs($this->admin)->from(route('domains.index'))->post(route('domains.availability'), [
            'connection' => $connection->sqid,
            'domains' => "belegt.de,\nneu.de",
        ])->assertRedirect(route('domains.index'))->assertSessionHas('availability');

        // Das Ergebnis steht auf der Seite, der freie Name ist im Registrier-Dialog vorbelegt.
        $this->actingAs($this->admin)->get(route('domains.index'))->assertOk()
            ->assertSee(__('domain.availability.results'))
            ->assertSee('belegt.de')
            ->assertSee('9,90 EUR')
            ->assertSee('id="domain-register-domain"', false)
            ->assertSee('value="neu.de"', false);

        $this->actingAs($this->admin)->from(route('domains.index'))->post(route('domains.register'), [
            'connection' => $connection->sqid,
            'domain' => 'neu.de',
            'customer' => $customer->sqid,
            'period' => '2',
            'renewal_mode' => 'AUTORENEW',
            'owner_contact' => 'P-OWNER1',
            'nameservers' => "ns1.example.net\nns2.example.net",
            'price_confirmed' => '1',
        ])->assertRedirect(route('domains.index'))->assertSessionHas('success', __('domain.flash.registered'));

        $domain = DomainProjection::query()->where('external_domain', 'neu.de')->sole();
        $this->assertSame($customer->id, $domain->customer_id);
        $this->assertDatabaseHas('domain_provider_commands', ['command' => 'AddDomain', 'target' => 'neu.de', 'customer_id' => $customer->id]);
    }

    /** Ohne betriebsbereite Verbindung gibt es nichts zu prüfen oder zu registrieren. */
    public function test_registration_entries_need_a_runnable_connection(): void {
        $this->admin->givePermissionTo(Permission::DomainRegister->value);
        DomainProviderConnection::factory()->draft()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->get(route('domains.index'))->assertOk()
            ->assertDontSee('data-open-dialog="domain-register"', false)
            ->assertDontSee(route('domains.register'), false);
    }

    public function test_domain_can_be_renewed_from_the_detail_page(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'laufzeit.de',
            'domain_hash' => DomainProjection::hashFor('laufzeit.de'),
        ]);

        $this->actingAs($this->admin)->get(route('domains.show', $domain))->assertOk()
            ->assertDontSee(route('domains.renew', $domain), false);

        $this->admin->givePermissionTo(Permission::DomainRenewalManage->value);
        $this->actingAs($this->admin)->get(route('domains.show', $domain))->assertOk()
            ->assertSee(route('domains.renew', $domain), false)
            ->assertSee('id="domain-renew-period"', false);

        $sent = [];
        FakeDomainResellingTransport::fake(['RenewDomain' => function (array $params) use (&$sent): string {
            $sent = $params;

            return "code=200\ndescription=ok\nEOF\n";
        }]);

        $this->actingAs($this->admin)->from(route('domains.show', $domain))
            ->post(route('domains.renew', $domain), ['period' => '2'])
            ->assertRedirect(route('domains.show', $domain))
            ->assertSessionHas('success', __('domain.flash.renew_requested'));

        $this->assertSame('laufzeit.de', $sent['domain'] ?? null);
        $this->assertSame('2', $sent['period'] ?? null);
        $this->assertDatabaseHas('domain_provider_commands', ['command' => 'RenewDomain', 'target' => 'laufzeit.de', 'status' => 'confirmed']);
    }

    /**
     * Zone ersetzen geht nur vom gelesenen Stand aus: Der Dialog ist mit den
     * Einträgen der Zone vorbelegt, ohne gelesene Zone gibt es ihn nicht.
     */
    /** Eine gelesene Zone hängt an ihrer Domain — sonst zeigt die Detailseite sie nicht (Fehler bis 2026-10-05). */
    public function test_read_zone_appears_on_the_domain_page(): void {
        $this->admin->givePermissionTo(Permission::DomainDnsManage->value);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'zone.de',
            'domain_hash' => DomainProjection::hashFor('zone.de'),
        ]);
        FakeDomainResellingTransport::fake([
            'StatusDNSZone' => fn (): string => FakeDomainResellingTransport::properties([['rr' => 'www.zone.de 3600 IN A 192.0.2.10']]),
        ]);

        $this->actingAs($this->admin)->get(route('domains.show', $domain))->assertOk()->assertDontSee('192.0.2.10');

        $this->actingAs($this->admin)->post(route('domains.dns.read', $domain))->assertSessionHas('success');

        $this->assertSame($domain->id, $domain->dnsZones()->sole()->domain_projection_id);
        $this->actingAs($this->admin)->get(route('domains.show', $domain))->assertOk()
            ->assertSee('192.0.2.10');
    }

    /**
     * Zone ersetzen (Entscheidung 2026-10-05): vorbelegt mit dem gelesenen Stand; Einträge, deren
     * Typ die App nicht kennt, gehen unverändert mit — sonst löschte der Vollersatz sie beim Anbieter.
     */
    public function test_zone_is_replaced_from_its_read_state_and_keeps_records_of_unknown_type(): void {
        $this->admin->givePermissionTo(Permission::DomainDnsManage->value);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'zone.de',
            'domain_hash' => DomainProjection::hashFor('zone.de'),
        ]);

        $unknown = 'zone.de 3600 IN X-UNBEKANNT 0 issue "ca.example"';
        $zone = ['www.zone.de 3600 IN A 192.0.2.10', $unknown];
        FakeDomainResellingTransport::fake([
            'StatusDNSZone' => function () use (&$zone): string {
                return FakeDomainResellingTransport::properties(array_map(static fn (string $rr): array => ['rr' => $rr], $zone));
            },
            'ModifyDNSZone' => function (array $params) use (&$zone): string {
                $zone = array_values(array_filter($params, static fn (string $key): bool => str_starts_with($key, 'rr'), ARRAY_FILTER_USE_KEY));

                return "code=200\ndescription=ok\nEOF\n";
            },
        ]);

        // Ohne gelesene Zone kein Einstieg.
        $this->actingAs($this->admin)->get(route('domains.show', $domain))->assertOk()
            ->assertDontSee('domain-dns-replace', false);

        $this->actingAs($this->admin)->post(route('domains.dns.read', $domain))->assertSessionHas('success');
        $this->assertSame([$unknown], $domain->dnsZones()->sole()->unparsed_records);

        $this->actingAs($this->admin)->get(route('domains.show', $domain))->assertOk()
            ->assertSee('data-open-dialog="domain-dns-replace"', false)
            ->assertSee(route('domains.dns.replace', $domain), false)
            ->assertSee('name="records[0][content]" value="192.0.2.10"', false)
            ->assertSee('X-UNBEKANNT');

        // Ein leeres Formular würde die Zone löschen: abgewiesen, nichts gesendet.
        $this->actingAs($this->admin)->from(route('domains.show', $domain))->post(route('domains.dns.replace', $domain), [
            'records' => [['type' => 'A', 'name' => '', 'ttl' => '', 'priority' => '', 'content' => '']],
        ])->assertSessionHas('error', __('domain.errors.dns_replace_empty'));
        $this->assertSame(['www.zone.de 3600 IN A 192.0.2.10', $unknown], $zone);

        $this->actingAs($this->admin)->from(route('domains.show', $domain))->post(route('domains.dns.replace', $domain), [
            'records' => [
                ['type' => 'A', 'name' => 'www.zone.de', 'ttl' => '3600', 'priority' => '', 'content' => '192.0.2.20'],
                ['type' => 'MX', 'name' => 'zone.de', 'ttl' => '', 'priority' => '10', 'content' => 'mail.zone.de'],
                // Leerzeile des Formulars: ohne Namen kein Eintrag.
                ['type' => 'A', 'name' => '', 'ttl' => '', 'priority' => '', 'content' => ''],
            ],
        ])->assertRedirect(route('domains.show', $domain))->assertSessionHas('success', __('domain.flash.dns_replaced'));

        $this->assertSame(['www.zone.de 3600 IN A 192.0.2.20', 'zone.de 3600 IN MX 10 mail.zone.de', $unknown], $zone);
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

    /** Vollaudit 2026-07 (M34): Kundenakte zeigt zugeordnete Domains (Feature 083, MVP-394). */
    public function test_customer_file_shows_assigned_domains(): void {
        $customer = \App\Models\Customer\Customer::factory()->create(['organization_id' => $this->organization->id]);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'customer_id' => $customer->id,
            'external_domain' => 'kundendomain.de',
            'domain_hash' => DomainProjection::hashFor('kundendomain.de'),
        ]);
        DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'andere.de',
            'domain_hash' => DomainProjection::hashFor('andere.de'),
        ]);

        $this->actingAs($this->admin)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('kundendomain.de')
            ->assertDontSee('andere.de');

        // Ohne domain.viewAny bleibt der Reiter unsichtbar.
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($stranger)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertDontSee('kundendomain.de');
    }

    public function test_customer_and_foreign_customer_assignment_via_http(): void {
        $this->admin->givePermissionTo(Permission::DomainCustomerAssign->value);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'zuweisung.de',
            'domain_hash' => DomainProjection::hashFor('zuweisung.de'),
        ]);

        // Endkunde eines ANDEREN Kunden wird abgelehnt.
        $this->actingAs($this->admin)->post(route('domains.customer', $domain), [
            'customer' => $other->sqid,
            'foreign_customer' => $foreign->sqid,
        ])->assertSessionHas('error');
        $this->assertNull($domain->refresh()->customer_id);

        // Passender Endkunde wird mit dem Kunden gespeichert.
        $this->actingAs($this->admin)->post(route('domains.customer', $domain), [
            'customer' => $customer->sqid,
            'foreign_customer' => $foreign->sqid,
        ])->assertSessionHas('success');
        $domain->refresh();
        $this->assertSame($customer->id, $domain->customer_id);
        $this->assertSame($foreign->id, $domain->foreign_customer_id);
    }

    public function test_domain_show_provides_foreign_customers_for_dependent_dropdown(): void {
        $this->admin->givePermissionTo(Permission::DomainCustomerAssign->value);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'name' => 'Endkunde Müller']);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        // Domain OHNE Kundenzuordnung — die Endkunden müssen für das abhängige
        // Dropdown trotzdem im Payload stehen (Ein-Schritt-Zuordnung).
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'dropdown.de',
            'domain_hash' => DomainProjection::hashFor('dropdown.de'),
        ]);

        $this->actingAs($this->admin)->get(route('domains.show', $domain))->assertOk()->assertSee('Endkunde Müller');
    }

    public function test_reseller_bulk_assigns_customer_on_all_domains_via_http(): void {
        $this->admin->givePermissionTo(Permission::DomainCustomerAssign->value);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $reseller = DomainResellerAccount::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'customer_id' => $customer->id,
        ]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'reseller_account_id' => $reseller->id,
            'external_domain' => 'bulk.de',
            'domain_hash' => DomainProjection::hashFor('bulk.de'),
        ]);

        $this->actingAs($this->admin)->post(route('domain-reseller.assign-domains', $reseller))->assertSessionHas('success');
        $this->assertSame($customer->id, $domain->refresh()->customer_id);
    }

    public function test_reseller_bulk_requires_assigned_customer(): void {
        $this->admin->givePermissionTo(Permission::DomainCustomerAssign->value);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $reseller = DomainResellerAccount::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'customer_id' => null,
        ]);

        $this->actingAs($this->admin)->post(route('domain-reseller.assign-domains', $reseller))->assertSessionHas('error');
    }

    public function test_own_holding_toggle_clears_customer_mapping(): void {
        $this->admin->givePermissionTo(Permission::DomainCustomerAssign->value);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'customer_id' => $customer->id,
            'external_domain' => 'eigene.de',
            'domain_hash' => DomainProjection::hashFor('eigene.de'),
        ]);

        $this->actingAs($this->admin)->post(route('domains.customer', $domain), ['own' => '1'])->assertSessionHas('success');
        $domain->refresh();
        $this->assertTrue($domain->is_own_holding);
        $this->assertNull($domain->customer_id);

        $this->actingAs($this->admin)->post(route('domains.customer', $domain), ['own' => '0']);
        $this->assertFalse($domain->refresh()->is_own_holding);
    }

    public function test_reseller_assignment_groups_domains_in_customer_file(): void {
        $this->admin->givePermissionTo(Permission::DomainCustomerAssign->value);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $reseller = DomainResellerAccount::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_user' => 'lds-systems',
        ]);
        DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'reseller_account_id' => $reseller->id,
            'external_domain' => 'lds-kunde.de',
            'domain_hash' => DomainProjection::hashFor('lds-kunde.de'),
        ]);

        $this->actingAs($this->admin)
            ->post(route('domain-reseller.customer', $reseller), ['customer' => $customer->sqid])
            ->assertSessionHas('success');
        $this->assertSame($customer->id, $reseller->refresh()->customer_id);

        // Kundenakte gruppiert die Subuser-Domain ohne Einzelzuordnung.
        $this->actingAs($this->admin)->get(route('customers.show', $customer))->assertOk()->assertSee('lds-kunde.de');
    }

    public function test_show_renders_customer_suggestions(): void {
        $this->admin->givePermissionTo(Permission::DomainCustomerAssign->value);
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Vorschlag GmbH',
            'email' => 'info@vorschlag.de',
        ]);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'vorschlag.de',
            'domain_hash' => DomainProjection::hashFor('vorschlag.de'),
        ]);

        $this->actingAs($this->admin)->get(route('domains.show', $domain))->assertOk()->assertSee('Vorschlag GmbH');
    }

    public function test_free_plan_blocks_domain_module(): void {
        $freeOrg = Organization::factory()->free()->create();
        app()->instance('currentOrganization', $freeOrg);
        $user = User::factory()->user()->create(['organization_id' => $freeOrg->id]);
        $user->givePermissionTo(Permission::DomainViewAny->value);

        $this->actingAs($user)->get(route('domains.index'))->assertStatus(423);
    }
}
