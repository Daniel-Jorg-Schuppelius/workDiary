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
use App\Models\{Customer, ExternalReference, User};
use App\Models\Domain\{DomainAccountingEntry, DomainProjection, DomainProviderConnection};
use App\Plugins\Support\Domain\DomainRateBudgetException;
use App\Services\Domain\{DomainAccountingService, DomainActionException, DomainAvailabilityService, DomainCommandService, DomainConnectionService, DomainCustomerMappingService, DomainDangerousActionService, DomainDnsService, DomainEventPollingService, DomainInvoiceService, DomainReportService, DomainSyncService};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'QueryContactList' => FakeDomainResellingTransport::properties([['contact' => 'P-ABC123', 'organization' => 'Kunde GmbH']]),
        ]);

        app(DomainSyncService::class)->syncAll($this->connection());

        $this->assertDatabaseHas('domain_reseller_accounts', ['external_user' => 'sub1', 'parent_user' => 'reseller1']);
        $domain = DomainProjection::query()->where('external_domain', 'kunde.de')->firstOrFail();
        $this->assertSame(DomainSyncStatus::Current, $domain->sync_status);
        $this->assertNotNull($domain->reseller_account_id);
        $this->assertDatabaseHas('domain_contact_projections', ['external_handle' => 'P-ABC123']);
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
        $this->assertDatabaseHas('external_references', ['external_type' => 'domain', 'external_id' => 'kunde.de', 'referenceable_id' => $customer->id]);
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
            'QueryEventList' => FakeDomainResellingTransport::properties([['eventid' => 'E-1', 'class' => 'DOMAIN', 'action' => 'EXPIRE', 'object' => 'x.de']]),
            'DeleteEvent' => "code=200\ndescription=ok\n", // kein EOF
        ]);
        $connection = $this->connection();

        $result = app(DomainEventPollingService::class)->poll($connection);
        $this->assertSame(1, $result['stored']);
        $this->assertSame(0, $result['acknowledged']);
        $this->assertDatabaseHas('domain_events', ['external_event_id' => 'E-1', 'status' => 'stored']);
    }

    public function test_accounting_is_read_only_and_invoices_blocked(): void {
        FakeDomainResellingTransport::fake([
            'QueryAccountingList' => FakeDomainResellingTransport::properties([['user' => 'reseller1', 'type' => 'RENEW', 'amount' => '9.90', 'currency' => 'EUR', 'date' => '2026-07-01']]),
        ]);
        $connection = $this->connection();

        app(DomainAccountingService::class)->sync($connection);
        $this->assertDatabaseCount('domain_accounting_entries', 1);
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

    public function test_own_holding_excludes_from_unmapped_and_yields_to_assignment(): void {
        $connection = \App\Models\Domain\DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
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
        $foreignOfOther = \App\Models\ForeignCustomer::factory()->create([
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
}
