<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Domain;

use App\Enums\Domain\{DomainConnectionStatus, DomainProviderCommandStatus, DomainSyncStatus};
use App\Models\Customer\Customer;
use App\Models\Domain\{DomainAccountingEntry, DomainContactProjection, DomainProjection, DomainProviderConnection, DomainResellerAccount};
use App\Models\Integration\ExternalReference;
use App\Models\Platform\User;
use App\Plugins\Support\Domain\DomainRateBudgetException;
use App\Services\Domain\{DomainAccountingService, DomainActionException, DomainAvailabilityService, DomainCommandService, DomainConnectionService, DomainCustomerMappingService, DomainDangerousActionService, DomainDnsService, DomainEventPollingService, DomainInvoiceService, DomainReportService, DomainSyncService};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeDomainResellingTransport;
use Tests\TestCase;

/**
 * Kern-Services des Domain-Moduls (Feature 083) gegen den Fake-Transport:
 * Projektions-Sync, Kundenmapping, Command-Outbox mit Vier-Augen, DNS,
 * Verfügbarkeitsbudget, Event-Durable-Store und Accounting/Rechnungen.
 */
class DomainServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $actor;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actor = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function connection(): DomainProviderConnection {
        return DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_sync_projects_resellers_domains_and_contacts(): void {
        FakeDomainResellingTransport::fake([
            'QueryUserList' => FakeDomainResellingTransport::properties([['user' => 'sub1', 'parentuser' => 'reseller1', 'depth' => '1', 'currency' => 'EUR']]),
            'QueryDomainList' => FakeDomainResellingTransport::properties([['domain' => 'kunde.de', 'user' => 'sub1', 'status' => 'ACTIVE', 'renewalmode' => 'AUTORENEW', 'expirationdate' => '2027-01-01']]),
            'QueryContactList' => FakeDomainResellingTransport::properties([['contact' => 'P-ABC123', 'organization' => 'Kunde GmbH'], ['CONTACT' => 'P-DEF456', 'CONTACT_ORGANIZATION' => 'Doku GmbH', 'CONTACT_COUNTRY' => 'DE']]),
        ]);

        app(DomainSyncService::class)->syncAll($this->connection());

        $this->assertDatabaseHas('domain_reseller_accounts', ['external_user' => 'sub1', 'parent_user' => 'reseller1']);
        $domain = DomainProjection::query()->where('external_domain', 'kunde.de')->firstOrFail();
        $this->assertSame(DomainSyncStatus::Current, $domain->sync_status);
        $this->assertNotNull($domain->reseller_account_id);
        $this->assertDatabaseHas('domain_contact_projections', ['external_handle' => 'P-ABC123']);
        $this->assertSame('Doku GmbH', DomainContactProjection::query()->where('external_handle', 'P-DEF456')->firstOrFail()->snapshot['organization'] ?? null, 'Doku-Schreibweise CONTACT_ORGANIZATION trifft ebenfalls');
    }

    public function test_customer_mapping_suggests_by_homepage_and_assigns_without_provider_move(): void {
        FakeDomainResellingTransport::fake([]);
        $connection = $this->connection();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'homepage' => 'https://kunde.de']);
        $domain = DomainProjection::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_domain' => 'kunde.de', 'domain_hash' => DomainProjection::hashFor('kunde.de')]);

        $mapping = app(DomainCustomerMappingService::class);
        $suggestions = $mapping->suggestFor($domain);
        $this->assertNotEmpty($suggestions);
        $this->assertSame($customer->id, $suggestions[0]['customer']->id);

        $mapping->assign($domain, $customer, $this->actor);
        $domain->refresh();
        $this->assertSame($customer->id, $domain->customer_id);
        $ref = ExternalReference::query()->withoutGlobalScopes()
            ->where('external_type', 'domain')->where('external_id', 'kunde.de')->firstOrFail();
        $this->assertSame($domain->getMorphClass(), $ref->referenceable_type);
        $this->assertSame($domain->id, $ref->referenceable_id);
        $this->assertSame($customer->id, $ref->payload['customer_id']);
    }

    public function test_dangerous_delete_requires_name_and_four_eyes(): void {
        FakeDomainResellingTransport::fake(['DeleteDomain' => "code=200\ndescription=deleted\nEOF\n"]);
        $connection = $this->connection();
        $domain = DomainProjection::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_domain' => 'weg.de', 'domain_hash' => DomainProjection::hashFor('weg.de')]);
        $dangerous = app(DomainDangerousActionService::class);

        // Falscher Bestätigungsname → Abbruch.
        $this->expectException(DomainActionException::class);
        $dangerous->requestDelete($domain, 'falsch.de', $this->actor);
    }

    public function test_dangerous_delete_four_eyes_flow(): void {
        FakeDomainResellingTransport::fake(['DeleteDomain' => "code=200\ndescription=deleted\nEOF\n"]);
        $connection = $this->connection();
        $domain = DomainProjection::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_domain' => 'weg.de', 'domain_hash' => DomainProjection::hashFor('weg.de')]);
        $commands = app(DomainCommandService::class);

        $command = app(DomainDangerousActionService::class)->requestDelete($domain, 'WEG.DE', $this->actor);
        $this->assertSame(DomainProviderCommandStatus::Draft, $command->status);

        // Freigabe durch denselben Nutzer verboten (Vier-Augen).
        try {
            $commands->approve($command, $this->actor);
            $this->fail('Vier-Augen-Verletzung nicht erkannt.');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $approver = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $commands->approve($command, $approver);
        $command->refresh();
        $commands->dispatch($command);

        $command->refresh();
        $this->assertSame(DomainProviderCommandStatus::Confirmed, $command->status);
    }

    public function test_command_without_eof_becomes_unknown_not_retried(): void {
        FakeDomainResellingTransport::fake(['ModifyDomain' => "code=200\ndescription=ok\n"]); // KEIN EOF
        $connection = $this->connection();
        $domain = DomainProjection::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id]);

        $command = app(DomainCommandService::class)->createAndDispatch(
            $connection,
            \App\Enums\Domain\DomainCapabilityArea::Domains,
            'ModifyDomain',
            $domain->external_domain,
            ['domain' => $domain->external_domain],
        );

        $command->refresh();
        $this->assertSame(DomainProviderCommandStatus::Unknown, $command->status);
    }

    public function test_dns_validation_and_replace_snapshot(): void {
        $zoneBody = FakeDomainResellingTransport::properties([['rr' => 'www 3600 IN A 1.2.3.4']]);
        FakeDomainResellingTransport::fake(['StatusDNSZone' => $zoneBody, 'ModifyDNSZone' => "code=200\nEOF\n"]);
        $connection = $this->connection();
        $dns = app(DomainDnsService::class);

        // Ungültiger Typ wirft.
        try {
            $dns->validateRecords([['type' => 'FOO', 'name' => 'www', 'content' => 'x']]);
            $this->fail('Ungültiger DNS-Typ nicht erkannt.');
        } catch (DomainActionException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('www 3600 IN A 1.2.3.4', $dns->serialize(['type' => 'A', 'name' => 'www', 'ttl' => 3600, 'content' => '1.2.3.4']));

        $result = $dns->replaceZone($connection, 'kunde.de', [['type' => 'A', 'name' => 'www', 'ttl' => 3600, 'content' => '1.2.3.4']], $this->actor);
        $this->assertFalse($result['conflict']);
        $this->assertNotNull($result['command']->preflight_snapshot);
    }

    public function test_availability_rate_budget_exhausts(): void {
        config()->set('plugins.domainreselling.check_budget_per_hour', 1);
        FakeDomainResellingTransport::fake(['CheckDomains' => FakeDomainResellingTransport::properties([['domain' => 'neu.de', 'status' => 'available']])]);
        $connection = $this->connection();
        $svc = app(DomainAvailabilityService::class);

        $svc->check($connection, ['neu.de']); // verbraucht Budget 1
        $this->expectException(DomainRateBudgetException::class);
        $svc->check($connection, ['zweite.de']);
    }

    public function test_event_polling_stores_before_acknowledge(): void {
        // DeleteEvent OHNE EOF → Acknowledge scheitert, Event bleibt gespeichert.
        FakeDomainResellingTransport::fake([
            // Namen laut Doku (EVENT, EVENTCLASS, EVENTSUBCLASS, EVENT_DATE, OBJECT_ID) — bis 2026-10-29 las der
            // Abruf `eventid`/`id` und speicherte nie ein Ereignis.
            'QueryEventList' => FakeDomainResellingTransport::properties([['event' => '4711', 'eventclass' => 'DOMAIN', 'eventsubclass' => 'TRANSFER_SUCCESSFUL', 'event_date' => '2026-10-28 10:00:00', 'object_id' => 'x.de']]),
            'DeleteEvent' => "code=200\ndescription=ok\n", // kein EOF
        ]);
        $connection = $this->connection();

        $result = app(DomainEventPollingService::class)->poll($connection);
        $this->assertSame(1, $result['stored']);
        $this->assertSame(0, $result['acknowledged']);
        $this->assertDatabaseHas('domain_events', ['external_event_id' => '4711', 'status' => 'stored', 'event_class' => 'DOMAIN', 'event_action' => 'TRANSFER_SUCCESSFUL', 'object' => 'x.de']);
        $this->assertSame(['event', 'event_date', 'eventclass', 'eventsubclass', 'object_id'], $connection->refresh()->observed_properties['queryeventlist'] ?? null, 'der Adapter merkt sich den gelieferten Vertrag');
    }

    public function test_availability_reads_the_documented_domaincheck_codes(): void {
        // Doku: DOMAINCHECK = „210 Available“ / „211 Not available“; bis 2026-10-29 verglich der Dienst nur `status`.
        FakeDomainResellingTransport::fake(['CheckDomains' => FakeDomainResellingTransport::properties([
            ['DOMAINCHECK' => '210 Available'],
            ['DOMAINCHECK' => '211 Not available'],
        ])]);
        $connection = $this->connection();
        $result = app(DomainAvailabilityService::class)->check($connection, ['frei.de', 'vergeben.de']);
        $this->assertSame([true, false], array_map(static fn(array $r): bool => $r['available'], $result));
    }

    public function test_contract_command_lists_observed_properties_and_missing_keys(): void {
        $connection = $this->connection();
        $connection->recordObservedProperties('QueryAccountingList', ['accountingdate', 'accountingtype', 'accountingdescription', 'accountingprice', 'total']);
        $connection->recordObservedProperties('QueryAccountingList', ['accountingdate', 'accountingtype', 'accountingdescription', 'accountingprice', 'total']);
        $this->assertSame(['queryaccountinglist'], array_keys($connection->refresh()->observed_properties ?? []));

        $this->assertSame(0, Artisan::call('domain:contract', ['--connection' => $connection->id]));
        $output = Artisan::output();
        $this->assertStringContainsString('queryaccountinglist', $output);
        $this->assertStringContainsString('Laufzeit, Subreseller', $output, $output);
    }

    public function test_accounting_is_read_only_and_invoices_blocked(): void {
        // Spalten laut CentralNic-Doku (Befund 2026-10-29: die alten Schlüssel lieferte die API nie);
        // Zeile 0 trägt zusätzlich die Summenfelder der Antwort.
        FakeDomainResellingTransport::fake([
            'QueryAccountingList' => FakeDomainResellingTransport::properties([
                // Live-Gateway schreibt die Namen ohne Unterstriche (RRPproxy-Stil), die Doku mit — beide müssen treffen.
                ['ACCOUNTINGDATE' => '2026-10-27 07:30:00', 'ACCOUNTINGTYPE' => 'RENEW', 'ACCOUNTINGDESCRIPTION' => 'antjebewegt.de', 'ACCOUNTINGAMOUNT' => '1', 'ACCOUNTINGPRICE' => '-5.36', 'ACCOUNTINGVAT' => '19', 'ACCOUNTINGVATPRICE' => '-1.02', 'ACCOUNTINGCURRENCY' => 'EUR', 'INVOICEPERIOD' => '1Y', 'SUBRESELLER' => 'lds-systems', 'TOTAL' => '2', 'COUNT' => '2', 'SUM' => '-5.54'],
                ['accounting_date' => '2026-10-28 03:03:00', 'accounting_type' => 'MONTHLY_FEE', 'accounting_description' => 'monthly fee (4 zones)', 'accounting_amount' => '4', 'accounting_price' => '-0.18', 'accounting_currency' => 'EUR'],
            ]),
        ]);
        $connection = $this->connection();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'LDS Systems GmbH']);
        $reseller = DomainResellerAccount::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_user' => 'lds-systems', 'customer_id' => $customer->id]);
        $domain = DomainProjection::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_domain' => 'antjebewegt.de', 'domain_hash' => DomainProjection::hashFor('antjebewegt.de')]);
        // Leere Zeile aus der Zeit der falschen Schlüssel.
        DomainAccountingEntry::query()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_user' => '', 'raw_hash' => str_repeat('0', 64)]);

        app(DomainAccountingService::class)->sync($connection);
        app(DomainAccountingService::class)->sync($connection);
        // Zwei Buchungen, keine Dubletten, leere Altzeile entfernt.
        $this->assertDatabaseCount('domain_accounting_entries', 2);
        $renew = DomainAccountingEntry::query()->where('type', 'RENEW')->firstOrFail();
        $this->assertSame('2026-10-27', $renew->entry_date?->format('Y-m-d'));
        $this->assertSame('antjebewegt.de', $renew->description);
        $this->assertSame('1Y', $renew->invoice_period);
        $this->assertSame('-5.36', $renew->net_amount);
        $this->assertSame('lds-systems', $renew->external_user);
        $this->assertSame($reseller->id, $renew->reseller_account_id);
        $this->assertSame($domain->id, $renew->domain_projection_id);
        $this->assertSame($customer->id, $renew->customer_id, 'Kunde des Subusers, weil die Domain keinen hat');
        $fee = DomainAccountingEntry::query()->where('type', 'MONTHLY_FEE')->firstOrFail();
        $this->assertNull($fee->domain_projection_id);
        $this->assertSame(0, DomainAccountingEntry::query()->whereNotNull('reference')->where('reference', 'INVOICE')->count());

        // Rechnungs-Capability ist per Default gesperrt → leer + Blocked-Reason.
        $invoices = app(DomainInvoiceService::class);
        $this->assertFalse($invoices->isAvailable($connection));
        $this->assertTrue($invoices->list($connection)->isEmpty());
    }

    public function test_connection_test_activates_and_sets_capabilities(): void {
        FakeDomainResellingTransport::fake(['StatusUser' => "code=200\ndescription=ok\nEOF\n"]);
        $connection = DomainProviderConnection::factory()->draft()->create(['organization_id' => $this->organization->id]);

        $this->assertTrue(app(DomainConnectionService::class)->test($connection));
        $connection->refresh();
        $this->assertTrue($connection->isRunnable());
        $this->assertIsArray($connection->capabilities);
        // Rechnungen bleiben gesperrt.
        $this->assertFalse($connection->capabilities['invoices']);
    }

    public function test_connection_test_records_auth_code_on_failure(): void {
        // Echte API: Auth-Fehler kommen ohne [RESPONSE]/EOF — der Code muss
        // trotzdem im last_error sichtbar sein (nicht als „incomplete").
        FakeDomainResellingTransport::fake(['StatusUser' => "code = 530\ndescription = Authentication failed"]);
        $connection = DomainProviderConnection::factory()->draft()->create(['organization_id' => $this->organization->id]);

        $this->assertFalse(app(DomainConnectionService::class)->test($connection));
        $connection->refresh();
        $this->assertSame(DomainConnectionStatus::Blocked, $connection->status);
        $this->assertSame('auth_code_530', $connection->last_error);
    }

    public function test_domain_list_sync_sends_wide_and_maps_real_property_names(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $reseller = DomainResellerAccount::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_user' => 'lds-systems',
        ]);

        FakeDomainResellingTransport::fake([
            'QueryDomainList' => function (array $params): string {
                // Ohne wide=1 liefert die echte API nur Domainnamen (kein USER
                // → keine Reseller-Verknüpfung); Live-Befund 2026-08-05.
                \PHPUnit\Framework\Assert::assertSame('1', $params['wide'] ?? null);

                return implode("\n", [
                    'code=200',
                    'property[DOMAIN][0]=lds-endkunde.de',
                    'property[USER][0]=lds-systems',
                    'property[DOMAINREGISTRAR][0]=DENIC',
                    'property[DOMAINSTATUS][0]=ACTIVE',
                    'property[DOMAINRENEWALMODE][0]=AUTORENEW',
                    'property[DOMAINREGISTRATIONEXPIRATIONDATE][0]=2027-03-01 00:00:00',
                    'EOF',
                ]) . "\n";
            },
        ]);

        app(DomainSyncService::class)->syncDomains($connection, 'ALL');

        $domain = DomainProjection::query()->where('domain_hash', DomainProjection::hashFor('lds-endkunde.de'))->firstOrFail();
        $this->assertSame($reseller->id, $domain->reseller_account_id);
        $this->assertSame('lds-systems', $domain->external_user);
        $this->assertSame('DENIC', $domain->registrar);
        $this->assertSame('ACTIVE', $domain->status);
        $this->assertSame('2027-03-01', $domain->expiration_at?->toDateString());
    }

    public function test_refresh_domain_relinks_reseller_from_status_user(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $reseller = DomainResellerAccount::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_user' => 'lds-systems',
        ]);
        // Altbestand aus einem Sync OHNE wide: keine Verknüpfung, kein Ablauf.
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'reseller_account_id' => null,
            'external_user' => '',
            'expiration_at' => null,
            'external_domain' => 'heilung.de',
            'domain_hash' => DomainProjection::hashFor('heilung.de'),
        ]);

        FakeDomainResellingTransport::fake([
            'StatusDomain' => FakeDomainResellingTransport::properties([[
                'registrar' => 'DENIC',
                'registrationexpirationdate' => '2027-03-01 00:00:00',
                'user' => 'lds-systems',
            ]]),
        ]);

        app(DomainSyncService::class)->refreshDomain($domain);

        $domain->refresh();
        $this->assertSame($reseller->id, $domain->reseller_account_id);
        $this->assertSame('lds-systems', $domain->external_user);
        $this->assertSame('2027-03-01', $domain->expiration_at?->toDateString());
    }

    public function test_own_holding_excludes_from_unmapped_and_yields_to_assignment(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'firmendomain.de',
            'domain_hash' => DomainProjection::hashFor('firmendomain.de'),
        ]);
        $mapping = app(DomainCustomerMappingService::class);
        $reports = app(DomainReportService::class);

        $this->assertTrue($reports->unmapped((int) $this->organization->id)->contains('id', $domain->id));

        $mapping->markOwnHolding($domain);
        $this->assertTrue($domain->refresh()->is_own_holding);
        $this->assertFalse($reports->unmapped((int) $this->organization->id)->contains('id', $domain->id));

        // Kundenzuordnung hebt den Eigenbestand auf; fremder Endkunde wird verworfen.
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreignOfOther = \App\Models\Customer\ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);
        $mapping->assign($domain, $customer, $this->actor, $foreignOfOther);
        $domain->refresh();
        $this->assertFalse($domain->is_own_holding);
        $this->assertSame($customer->id, $domain->customer_id);
        $this->assertNull($domain->foreign_customer_id);
    }

    public function test_domain_maps_to_single_customer_org_wide_without_duplicates(): void {
        $connA = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $connB = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $customerX = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $customerY = Customer::factory()->create(['organization_id' => $this->organization->id]);

        // Domain unter Verbindung A, zugeordnet zu Kunde X.
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connA->id,
            'external_domain' => 'shared.de',
            'domain_hash' => DomainProjection::hashFor('shared.de'),
            'customer_id' => $customerX->id,
        ]);
        app(DomainCustomerMappingService::class)->assign($domain, $customerX, $this->actor);

        // Dieselbe Domain über Verbindung B gemeldet → aktualisiert die EINE Zeile.
        FakeDomainResellingTransport::fake([
            'QueryDomainList' => FakeDomainResellingTransport::properties([['domain' => 'shared.de', 'status' => 'ACTIVE']]),
        ]);
        app(DomainSyncService::class)->syncDomains($connB, 'ALL');

        $hash = DomainProjection::hashFor('shared.de');
        $this->assertSame(1, DomainProjection::query()->where('domain_hash', $hash)->count());
        $moved = DomainProjection::query()->where('domain_hash', $hash)->firstOrFail();
        $this->assertSame($connB->id, $moved->connection_id);   // Verbindung „gewandert"
        $this->assertSame($customerX->id, $moved->customer_id); // Kundenzuordnung erhalten

        // Neuzuordnung ersetzt, dupliziert keine ExternalReference.
        app(DomainCustomerMappingService::class)->assign($moved, $customerY, $this->actor);
        $moved->refresh();
        $this->assertSame($customerY->id, $moved->customer_id);
        $this->assertSame(1, ExternalReference::query()
            ->where('external_type', 'domain')->where('external_id', 'shared.de')->count());

        // DB-Guard: manuelles Duplikat derselben Domain in der Organisation wird abgewiesen.
        $this->expectException(QueryException::class);
        DomainProjection::query()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connA->id,
            'external_domain' => 'shared.de',
            'domain_hash' => $hash,
        ]);
    }

    public function test_customer_may_hold_multiple_domains(): void {
        FakeDomainResellingTransport::fake([]);
        $connection = $this->connection();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $mapping = app(DomainCustomerMappingService::class);

        $first = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'erste.de',
            'domain_hash' => DomainProjection::hashFor('erste.de'),
        ]);
        $second = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'zweite.de',
            'domain_hash' => DomainProjection::hashFor('zweite.de'),
        ]);

        // Zwei Domains auf denselben Kunden dürfen extref_unique nicht verletzen.
        $mapping->assign($first, $customer, $this->actor);
        $mapping->assign($second, $customer, $this->actor);

        // Je Domain genau EINE Referenz auf die jeweilige Projektion, Kunde im
        // payload — kein Alias-Umweg.
        $this->assertSame(2, ExternalReference::query()->withoutGlobalScopes()
            ->where('external_type', 'domain')
            ->whereIn('external_id', ['erste.de', 'zweite.de'])
            ->count());
        $this->assertDatabaseCount('external_reference_aliases', 0);
        foreach ([$first, $second] as $projection) {
            $ref = ExternalReference::query()->withoutGlobalScopes()
                ->where('external_type', 'domain')
                ->where('external_id', $projection->external_domain)
                ->firstOrFail();
            $this->assertSame($projection->getMorphClass(), $ref->referenceable_type);
            $this->assertSame($projection->id, $ref->referenceable_id);
            $this->assertSame($customer->id, $ref->payload['customer_id']);
        }

        // Beide Domains schlagen denselben Kunden vor.
        $this->assertSame($customer->id, $mapping->suggestFor($first)[0]['customer']->id);
        $this->assertSame($customer->id, $mapping->suggestFor($second)[0]['customer']->id);

        // Aufheben einer Domain trifft nur diese, die andere bleibt zugeordnet.
        $mapping->clearAssignment($second);
        $this->assertDatabaseMissing('external_references', ['external_type' => 'domain', 'external_id' => 'zweite.de']);
        $this->assertSame($customer->id, $mapping->suggestFor($first)[0]['customer']->id);
        $this->assertEmpty(array_filter(
            $mapping->suggestFor($second),
            fn(array $s): bool => $s['reason'] === 'external_reference',
        ));
    }

    public function test_assign_reseller_domains_writes_customer_on_all_domains(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $reseller = DomainResellerAccount::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'customer_id' => $customer->id,
        ]);
        $a = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'reseller_account_id' => $reseller->id,
            'external_domain' => 'reseller-a.de',
            'domain_hash' => DomainProjection::hashFor('reseller-a.de'),
        ]);
        $b = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'reseller_account_id' => $reseller->id,
            'external_domain' => 'reseller-b.de',
            'domain_hash' => DomainProjection::hashFor('reseller-b.de'),
        ]);

        $count = app(DomainCustomerMappingService::class)->assignResellerDomains($reseller, $this->actor);

        $this->assertSame(2, $count);
        $this->assertSame($customer->id, $a->refresh()->customer_id);
        $this->assertSame($customer->id, $b->refresh()->customer_id);
        // Je Domain eine bestätigte Referenz (Ziel = Projektion, Kunde im payload).
        $this->assertSame(2, ExternalReference::query()->withoutGlobalScopes()
            ->where('external_type', 'domain')
            ->whereIn('external_id', ['reseller-a.de', 'reseller-b.de'])
            ->count());
    }

    public function test_assign_reseller_domains_without_customer_is_noop(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $reseller = DomainResellerAccount::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'customer_id' => null,
        ]);
        DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'reseller_account_id' => $reseller->id,
            'external_domain' => 'ohne-kunde.de',
            'domain_hash' => DomainProjection::hashFor('ohne-kunde.de'),
        ]);

        $this->assertSame(0, app(DomainCustomerMappingService::class)->assignResellerDomains($reseller, $this->actor));
    }

    public function test_portal_company_suggestion_from_registrant_matches_customer(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beispiel GmbH']);
        \App\Models\Domain\DomainContactProjection::query()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_handle' => 'P-OWNER1',
            'snapshot' => ['organization' => 'Beispiel GmbH', 'country' => 'DE', 'type' => 'ORG'],
        ]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'owner_handle' => 'P-OWNER1',
            'external_domain' => 'portal.de',
            'domain_hash' => DomainProjection::hashFor('portal.de'),
        ]);

        $portal = array_values(array_filter(
            app(DomainCustomerMappingService::class)->suggestFor($domain),
            fn(array $s): bool => $s['reason'] === 'portal_company',
        ));
        $this->assertCount(1, $portal);
        $this->assertSame($customer->id, $portal[0]['customer']->id);
    }

    public function test_portal_company_suggestion_absent_without_owner_handle(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beispiel GmbH']);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'owner_handle' => null,
            'external_domain' => 'kein-owner.de',
            'domain_hash' => DomainProjection::hashFor('kein-owner.de'),
        ]);

        $this->assertEmpty(array_filter(
            app(DomainCustomerMappingService::class)->suggestFor($domain),
            fn(array $s): bool => $s['reason'] === 'portal_company',
        ));
    }
}
