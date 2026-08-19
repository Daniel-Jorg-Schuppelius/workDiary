<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\OrgaMax;

use App\Models\{Customer, ExternalReference, IntegrationInboxItem, OrgaMaxConnection, OrgaMaxInvoice, User};
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\OrgaMax\Services\{OrgaMaxInvoiceProjector, OrgaMaxSyncService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-307/311/313: Inbox-First-Zuordnung (keine Schattenstammdaten),
 * Rechnungsprojektion und budgetierter Sweep mit Offset-Checkpoint.
 */
class OrgaMaxSyncTest extends TestCase {
    use OrgaMaxApiResponses;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private OrgaMaxConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);

        $this->connection = OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'api_key' => 'k',
            'api_secret' => 's',
            'ownership_id' => 'own-1',
            'bearer_token' => 'token',
            'token_expires_at' => Carbon::now()->addHour(),
            'status' => OrgaMaxConnection::STATUS_ACTIVE,
            'capabilities' => [
                'customers' => ['enabled' => true, 'leader' => 'orgamax'],
                'billing' => ['enabled' => true, 'leader' => 'orgamax'],
            ],
        ]);
    }

    public function test_customer_sync_links_exact_match_and_inboxes_unknown(): void {
        $known = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
            'email' => 'billing@acme.test',
            'vat_id' => 'DE123456789',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);

        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/customer*' => self::listResponse([
                ['id' => 'om-1', 'name' => 'ACME GmbH', 'vatNumber' => 'DE123456789', 'email' => 'billing@acme.test'],
                ['id' => 'om-2', 'name' => 'Unbekannte Firma XY', 'email' => 'nobody@xy.test'],
            ]),
            'https://api.orgamax.de/openapi/invoice*' => self::listResponse([]),
        ]);

        app(OrgaMaxSyncService::class)->run($this->connection);

        // Sicherer Treffer (USt-IdNr.) → ExternalReference, KEIN neuer Kunde.
        $reference = ExternalReference::query()
            ->where('plugin_id', OrgaMaxPlugin::ID)
            ->where('external_type', 'customer')
            ->where('external_id', 'om-1')
            ->firstOrFail();
        $this->assertSame($known->id, $reference->referenceable_id);

        // Unbekannter Datensatz → Inbox statt Schattenstammdaten.
        $this->assertSame(1, IntegrationInboxItem::query()
            ->where('plugin_id', OrgaMaxPlugin::ID)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->count());
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_invoice_projection_is_stored_with_source_orgamax(): void {
        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/customer*' => self::listResponse([]),
            'https://api.orgamax.de/openapi/invoice*' => self::listResponse([
                [
                    'id' => 501,
                    'number' => 'RE-2026-001',
                    'state' => 'locked',
                    'totalNet' => 100.0,
                    'totalGross' => 119.0,
                    'outstandingAmount' => 119.0,
                    'customerId' => 7,
                    'customerData' => ['name' => 'ACME'],
                    'dueToDate' => '2026-08-15',
                ],
            ]),
        ]);

        app(OrgaMaxSyncService::class)->run($this->connection);

        $projection = ExternalReference::query()
            ->where('plugin_id', OrgaMaxPlugin::ID)
            ->where('external_type', OrgaMaxInvoiceProjector::EXT_TYPE_INVOICE)
            ->where('external_id', '501')
            ->firstOrFail();
        $this->assertSame('orgamax', $projection->payload['source'] ?? null);
        $this->assertSame('RE-2026-001', $projection->payload['number'] ?? null);
        $this->assertSame('locked', $projection->payload['status'] ?? null);
        $this->assertSame('ACME', $projection->payload['customer'] ?? null);
        $this->assertEqualsWithDelta(119.0, (float) ($projection->payload['outstanding_amount'] ?? 0), 0.001);
        $this->assertNotNull($projection->synced_at);
    }

    public function test_multiple_invoices_are_projected_into_the_local_mirror(): void {
        // Regression (Bestandsfehler Feature 077): alle Rechnungen hingen an
        // derselben Verbindung, wodurch `extref_unique` ab dem zweiten Beleg
        // zuschlug. Jede Rechnung hat jetzt ein eigenes lokales Objekt.
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);
        ExternalReference::link($this->organization->id, OrgaMaxPlugin::ID, 'customer', $customer, '7');

        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/customer*' => self::listResponse([]),
            'https://api.orgamax.de/openapi/invoice*' => self::listResponse([
                ['id' => 501, 'number' => 'RE-2026-001', 'state' => 'locked', 'totalGross' => 119.0, 'outstandingAmount' => 119.0, 'customerId' => 7],
                ['id' => 502, 'number' => 'RE-2026-002', 'state' => 'paid', 'totalGross' => 238.0, 'outstandingAmount' => 0.0, 'customerId' => 7],
            ]),
        ]);

        app(OrgaMaxSyncService::class)->run($this->connection);

        $this->assertSame(2, OrgaMaxInvoice::query()->count());
        $this->assertSame(2, ExternalReference::query()
            ->where('external_type', OrgaMaxInvoiceProjector::EXT_TYPE_INVOICE)
            ->count());

        $mirror = OrgaMaxInvoice::query()->where('external_id', '502')->firstOrFail();
        $this->assertSame('RE-2026-002', $mirror->invoice_number);
        $this->assertSame('paid', $mirror->invoice_status);
        $this->assertSame($customer->id, $mirror->customer_id, 'Der bestätigte Kunde wird verknüpft.');
        $this->assertSame(1, OrgaMaxInvoice::query()->open()->count(), 'Nur der offene Beleg zählt als offen.');

        // Referenz zeigt auf den Spiegel, nicht mehr auf die Verbindung.
        $reference = ExternalReference::query()
            ->where('external_type', OrgaMaxInvoiceProjector::EXT_TYPE_INVOICE)
            ->where('external_id', '502')
            ->firstOrFail();
        $this->assertSame($mirror->getMorphClass(), $reference->referenceable_type);
        $this->assertSame($mirror->id, $reference->referenceable_id);

        // Zweiter Lauf aktualisiert, statt zu duplizieren.
        app(OrgaMaxSyncService::class)->run($this->connection->refresh());
        $this->assertSame(2, OrgaMaxInvoice::query()->count());
    }

    public function test_budgeted_sweep_advances_offset_checkpoint_and_resets_after_full_pass(): void {
        config()->set('plugins.orgamax.page_size', 2);
        config()->set('plugins.orgamax.sync_page_budget', 1);

        // Erste Seite ist voll (2 Datensätze) → Budget erschöpft, Offset bleibt stehen.
        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/customer*' => self::listResponse([
                ['id' => 'om-1', 'name' => 'A'],
                ['id' => 'om-2', 'name' => 'B'],
            ]),
            'https://api.orgamax.de/openapi/invoice*' => self::listResponse([]),
        ]);
        app(OrgaMaxSyncService::class)->run($this->connection);

        $this->connection->refresh();
        $this->assertSame(2, (int) ($this->connection->checkpoints['customers_offset'] ?? -1));

        // Nächster Lauf: kurze Seite → Vollabgleich abgeschlossen, Offset zurück auf 0.
        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/customer*' => self::listResponse([
                ['id' => 'om-3', 'name' => 'C'],
            ]),
            'https://api.orgamax.de/openapi/invoice*' => self::listResponse([]),
        ]);
        app(OrgaMaxSyncService::class)->run($this->connection);

        $this->connection->refresh();
        $this->assertSame(0, (int) ($this->connection->checkpoints['customers_offset'] ?? -1));
        $this->assertNotNull($this->connection->checkpoints['customers'] ?? null);
        $this->assertNotNull($this->connection->last_sync_at);
    }
}
