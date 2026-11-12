<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataOwnershipDocumentsCustomersTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Enums\CloudIntake\CloudIntakeRouteTarget;
use App\Enums\Integration\{DataDomain, IntegrationInboxStatus};
use App\Exceptions\ExternallyLedQueueException;
use App\Models\Audit\AuditLog;
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem, CloudDocumentRoute};
use App\Models\Customer\Customer;
use App\Models\Document\{Document, DocumentVersion};
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\{PluginSetting, User};
use App\Models\ServiceTicket\ServiceQueue;
use App\Plugins\Contracts\DocumentIntakeSource;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\SevDesk\Services\SevDeskContactImportService;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Plugins\Support\Intake\{IntakeChangePage, IntakeItem};
use App\Plugins\Toggl\Services\TogglExportImporter;
use App\Services\CloudIntake\CloudIntakeRunner;
use App\Services\Finance\Accounting\ContactPushService;
use App\Services\Integration\{DataOwnershipGuard, DataOwnershipResolver, InboxActionService};
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakeIntakeAdapter, FakePluginHttp};
use Tests\TestCase;

/**
 * MVP-1592, Teil 2: Datenführerschaft für Dokumente (Cloud-Dokumenteingang)
 * und Kunden/Lieferanten (Stammdatenhoheit, Kontakt-Rückweg der Buchhaltung).
 * Führt die Quelle bzw. WorkDiary, wird geschrieben; sonst Konflikt, und
 * „Einmalig übernehmen“ schreibt genau einmal. Ein übernommener Fall öffnet
 * sich bei neuem Stand wieder, ein verworfener bleibt verworfen.
 */
final class DataOwnershipDocumentsCustomersTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string SEVDESK_CONTACTS = 'https://my.sevdesk.de/api/v1/Contact?*';

    private const string SEVDESK_ADDRESSES = 'https://my.sevdesk.de/api/v1/ContactAddress*';

    private const string SEVDESK_WAYS = 'https://my.sevdesk.de/api/v1/CommunicationWay*';

    private FakeIntakeAdapter $adapter;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app()->instance('currentOrganization', $this->organization);
        $this->adapter = new FakeIntakeAdapter();
    }

    // ── Dokumente ────────────────────────────────────────────────────────

    public function test_documents_leading_source_imports(): void {
        $connection = $this->cloudConnection();
        app(DataOwnershipResolver::class)->setOwner($this->organization, DataDomain::Documents, 'dropbox');
        $this->offer('f-1', 'r1', 'inhalt-r1');

        $result = $this->runner()->run($connection, $this->adapter);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, Document::query()->count());
        $this->assertSame(0, $this->ownershipCases());
    }

    public function test_documents_foreign_lead_holds_back_and_accept_imports_once(): void {
        $connection = $this->cloudConnection();
        app(DataOwnershipResolver::class)->setOwner($this->organization, DataDomain::Documents, 'paperless');
        $this->offer('f-1', 'r1', 'inhalt-r1');

        $result = $this->runner()->run($connection, $this->adapter);

        $this->assertSame(1, $result['inbox']);
        $this->assertSame(0, Document::query()->count(), 'Paperless führt: Dropbox legt kein Dokument an.');
        $this->assertSame(0, CloudDocumentItem::query()->count(), 'Kein Nachweis, damit das Übernehmen die Datei neu lädt.');
        $item = $this->onlyOwnershipCase();
        $this->assertSame(['domain' => 'documents', 'owner' => 'paperless', 'kind' => CloudIntakeRunner::OWNERSHIP_KIND], $item->ownershipMarker());

        $written = app(DataOwnershipGuard::class)->accept($item);

        $this->assertInstanceOf(Document::class, $written);
        $this->assertSame(1, Document::query()->count());
        $this->assertSame(IntegrationInboxStatus::ResolvedRemote, $item->refresh()->status);
        $this->runner()->run($connection, $this->adapter);
        $this->assertSame(1, Document::query()->count(), 'Gleiche Revision: kein zweites Dokument.');
        $this->assertSame(IntegrationInboxStatus::ResolvedRemote, $item->refresh()->status, 'Gleicher Stand: nichts neu vorzulegen.');
    }

    public function test_accepted_case_reopens_on_new_state_and_dismissed_stays_dismissed(): void {
        $connection = $this->cloudConnection(['auto_version' => true]);
        app(DataOwnershipResolver::class)->setOwner($this->organization, DataDomain::Documents, 'paperless');
        $this->offer('f-1', 'r1', 'inhalt-r1');
        $this->runner()->run($connection, $this->adapter);
        $item = $this->onlyOwnershipCase();
        app(DataOwnershipGuard::class)->accept($item);

        $this->offer('f-1', 'r2', 'inhalt-r2');
        $this->runner()->run($connection, $this->adapter);

        $this->assertSame(IntegrationInboxStatus::Open, $item->refresh()->status, 'Übernommen, dann neuer Stand: der Fall öffnet sich wieder.');
        $this->assertSame(1, DocumentVersion::query()->count(), 'Die neue Revision wartet auf das Übernehmen.');
        $this->assertSame(2, AuditLog::query()->where('event', 'integration.ownership_blocked')->count());

        app(InboxActionService::class)->dismiss($item);
        $this->offer('f-1', 'r3', 'inhalt-r3');
        $this->runner()->run($connection, $this->adapter);

        $this->assertSame(IntegrationInboxStatus::Dismissed, $item->refresh()->status, 'Verworfen bleibt verworfen.');
        $this->assertSame(1, DocumentVersion::query()->count());
    }

    // ── Kunden und Lieferanten ───────────────────────────────────────────

    public function test_customers_owner_follows_master_data_authority(): void {
        $this->enable(SevDeskPlugin::ID, EasybillPlugin::ID);
        $resolver = app(DataOwnershipResolver::class);
        $this->assertSame(DataOwnershipResolver::NATIVE, $resolver->ownerFor($this->organization, DataDomain::Customers));

        Setting::set(ContactPushService::AUTHORITY_KEY, ContactPushService::AUTHORITY_ACCOUNTING, SettingScope::Organization, $this->organization);
        $this->assertSame(DataOwnershipResolver::ACCOUNTING, $resolver->ownerFor($this->organization->refresh(), DataDomain::Customers));
        $this->assertTrue($resolver->mayWrite($this->organization, DataDomain::Customers, SevDeskPlugin::ID), 'Ohne gewähltes Programm schreibt jedes, das Kunden schreiben kann.');
        $this->assertFalse($resolver->mayWrite($this->organization, DataDomain::Customers, 'toggl'));

        $resolver->setOwner($this->organization, DataDomain::Customers, EasybillPlugin::ID);
        $this->assertSame(EasybillPlugin::ID, $resolver->ownerFor($this->organization->refresh(), DataDomain::Customers));
        $this->assertFalse($resolver->mayWrite($this->organization, DataDomain::Customers, SevDeskPlugin::ID));
        $this->assertFalse(app(ContactPushService::class)->pushAllowed(), 'Ein Programm führt: kein Kontakt-Push.');

        $resolver->setOwner($this->organization, DataDomain::Customers, DataOwnershipResolver::NATIVE);
        $this->assertSame(DataOwnershipResolver::NATIVE, $resolver->ownerFor($this->organization->refresh(), DataDomain::Customers));
        $this->assertSame(ContactPushService::AUTHORITY_WORKDIARY, data_get($this->organization->settings, ContactPushService::AUTHORITY_KEY));
        $this->assertTrue(app(ContactPushService::class)->pushAllowed());
    }

    public function test_customer_and_supplier_forms_warn_while_accounting_leads(): void {
        $this->enable(EasybillPlugin::ID);
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $pages = [route('customers.create'), route('customers.edit', $customer), route('suppliers.create')];
        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertOk()->assertDontSee('data-accounting-led', false);
        }

        Setting::set(ContactPushService::AUTHORITY_KEY, ContactPushService::AUTHORITY_ACCOUNTING, SettingScope::Organization, $this->organization);
        // Die Anfragen davor haben die Organisation am Nutzer gebunden; der neue Stand gilt mit frischem Nutzer.
        $admin = $admin->fresh();
        $this->actingAs($admin)->get(route('customers.create'))
            ->assertSee('data-accounting-led', false)
            ->assertSee(__('integration.ownership.led_notice', ['program' => __('integration.ownership.led_by_accounting')]));

        app(DataOwnershipResolver::class)->setOwner($this->organization->refresh(), DataDomain::Customers, EasybillPlugin::ID);
        $admin = $admin->fresh();
        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertSee('data-accounting-led', false)->assertSee('easybill');
        }
    }

    public function test_customers_foreign_program_holds_back_and_accept_writes_once(): void {
        $this->enable(SevDeskPlugin::ID, EasybillPlugin::ID);
        app(DataOwnershipResolver::class)->setOwner($this->organization, DataDomain::Customers, EasybillPlugin::ID);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Seeblick Pension', 'vat_id' => null]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id, 'plugin_id' => SevDeskPlugin::ID, 'external_type' => ExternalReference::TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->id, 'external_id' => '42',
        ]);
        $this->serveSevDesk('Seeblick Pension & Café');

        $counters = app(SevDeskContactImportService::class)->run($this->organization->refresh());

        $this->assertSame(1, $counters['inboxed']);
        $this->assertSame('Seeblick Pension', $customer->refresh()->name, 'easybill führt: sevDesk schreibt nichts.');
        $item = $this->onlyOwnershipCase();
        $this->assertSame(['domain' => 'customers', 'owner' => EasybillPlugin::ID, 'kind' => SevDeskPlugin::ID . '.contact'], $item->ownershipMarker());

        $written = app(DataOwnershipGuard::class)->accept($item);

        $this->assertTrue($customer->is($written));
        $this->assertSame('Seeblick Pension & Café', $customer->refresh()->name);
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(IntegrationInboxStatus::ResolvedRemote, $item->refresh()->status);
    }

    public function test_customers_leading_program_writes(): void {
        $this->enable(SevDeskPlugin::ID);
        app(DataOwnershipResolver::class)->setOwner($this->organization, DataDomain::Customers, SevDeskPlugin::ID);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Seeblick Pension', 'vat_id' => null]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id, 'plugin_id' => SevDeskPlugin::ID, 'external_type' => ExternalReference::TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->id, 'external_id' => '42',
        ]);
        $this->serveSevDesk('Seeblick Pension & Café');

        app(SevDeskContactImportService::class)->run($this->organization->refresh());

        $this->assertSame('Seeblick Pension & Café', $customer->refresh()->name);
        $this->assertSame(0, $this->ownershipCases());
    }

    public function test_toggl_migration_does_not_create_customers_under_foreign_lead(): void {
        $this->enable(SevDeskPlugin::ID);
        app(DataOwnershipResolver::class)->setOwner($this->organization, DataDomain::Customers, SevDeskPlugin::ID);
        $method = new \ReflectionMethod(TogglExportImporter::class, 'findOrCreateCustomer');
        $stats = [];

        try {
            $method->invokeArgs(new TogglExportImporter(), [$this->organization->refresh(), 'Neukunde Musterbau', false, &$stats]);
            $this->fail('Die Migration darf unter fremder Kundenführung keinen Kunden anlegen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Neukunde Musterbau', $e->getMessage());
        }
        $this->assertSame(0, Customer::query()->where('name', 'Neukunde Musterbau')->count());
    }

    // ── Matrix ───────────────────────────────────────────────────────────

    public function test_matrix_offers_document_and_customer_writers_and_sets_master_data_authority(): void {
        $this->enable('dropbox', 'paperless', 'lexoffice', 'toggl');
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $page = $this->actingAs($admin)->get(route('admin.data-ownership.index'))->assertOk();
        $this->assertMatchesRegularExpression('/data-domain="documents".*?<option value="dropbox".*?<option value="paperless"/s', (string) $page->getContent());
        $this->assertMatchesRegularExpression('/data-domain="customers".*?<option value="accounting".*?<option value="lexoffice"/s', (string) $page->getContent());
        $page->assertDontSee('<option value="toggl"', false)
            ->assertSee(__('integration.ownership.matrix.customers_hint'));

        $this->actingAs($admin)->post(route('admin.data-ownership.update'), ['domain' => 'customers', 'owner' => 'lexoffice'])
            ->assertSessionHas('success');
        $this->assertSame(ContactPushService::AUTHORITY_ACCOUNTING, data_get($this->organization->refresh()->settings, ContactPushService::AUTHORITY_KEY));
        $this->assertSame('lexoffice', app(DataOwnershipResolver::class)->ownerFor($this->organization, DataDomain::Customers));

        $this->actingAs($admin)->post(route('admin.data-ownership.update'), ['domain' => 'documents', 'owner' => 'accounting'])
            ->assertSessionHas('error', __('integration.ownership.matrix.invalid_owner'));
    }

    // ── Rest aus Teil 1: extern geführte Queue ───────────────────────────

    public function test_externally_led_queue_refusal_renders_a_message_instead_of_an_error(): void {
        $queue = ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Extern', 'data_ownership' => ServiceQueue::OWNERSHIP_EXTERNAL]);
        $refusal = ExternallyLedQueueException::for($queue);

        $api = $refusal->render(Request::create('/api/v1/tickets', 'POST'));
        $this->assertSame(422, $api->getStatusCode());
        $this->assertStringContainsString('queue_externally_led', (string) $api->getContent());

        $web = $refusal->render(Request::create('/servicedesk/bestellen', 'POST'));
        $this->assertTrue($web->isRedirect());
        $this->assertSame($refusal->getMessage(), session('error'));
    }

    // ── Hilfen ───────────────────────────────────────────────────────────

    private function enable(string ...$pluginIds): void {
        foreach ($pluginIds as $pluginId) {
            PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => $pluginId, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        }
    }

    /** @param array<string, mixed> $route */
    private function cloudConnection(array $route = []): CloudDocumentConnection {
        $creator = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $connection = CloudDocumentConnection::factory()->active()->create(['organization_id' => $this->organization->id, 'created_by_user_id' => $creator->id]);
        CloudDocumentRoute::factory()->create($route + [
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'path_pattern' => 'Dokumente/**',
            'target' => CloudIntakeRouteTarget::Document,
        ]);

        return $connection;
    }

    private function offer(string $itemId, string $revision, string $content): void {
        $this->adapter->pages = [new IntakeChangePage([new IntakeItem(itemId: $itemId, path: 'Dokumente/bericht.pdf', name: 'bericht.pdf', revision: $revision, size: 100)], [], 'cp-' . $revision, false)];
        $this->adapter->contents = [$itemId => $content];
    }

    /** Runner mit dem Test-Adapter — auch für das Übernehmen aus dem Eingang. */
    private function runner(): CloudIntakeRunner {
        $adapter = $this->adapter;
        $runner = new class(app(\App\Services\CloudIntake\RoutePatternValidator::class), app(\App\Services\CloudIntake\CloudIntakeRouter::class), $adapter) extends CloudIntakeRunner {
            public function __construct(\App\Services\CloudIntake\RoutePatternValidator $patterns, \App\Services\CloudIntake\CloudIntakeRouter $router, private readonly DocumentIntakeSource $fake) {
                parent::__construct($patterns, $router);
            }

            protected function resolveAdapter(CloudDocumentConnection $connection): DocumentIntakeSource {
                return $this->fake;
            }
        };
        app()->instance(CloudIntakeRunner::class, $runner);

        return $runner;
    }

    private function serveSevDesk(string $name): void {
        FakePluginHttp::fake([
            self::SEVDESK_ADDRESSES => FakePluginHttp::response(['total' => 0, 'objects' => []]),
            self::SEVDESK_WAYS => FakePluginHttp::response(['total' => 0, 'objects' => []]),
            self::SEVDESK_CONTACTS => FakePluginHttp::response(['total' => 1, 'objects' => [
                ['id' => '42', 'objectName' => 'Contact', 'name' => $name, 'customerNumber' => '1042', 'category' => ['id' => '3', 'objectName' => 'Category']],
            ]]),
        ]);
    }

    private function ownershipCases(): int {
        return IntegrationInboxItem::query()->whereLikeEscaped('dedupe_key', DataOwnershipGuard::DEDUPE_PREFIX, 'prefix')->count();
    }

    private function onlyOwnershipCase(): IntegrationInboxItem {
        return IntegrationInboxItem::query()->whereLikeEscaped('dedupe_key', DataOwnershipGuard::DEDUPE_PREFIX, 'prefix')->where('status', IntegrationInboxStatus::Open)->sole();
    }
}
