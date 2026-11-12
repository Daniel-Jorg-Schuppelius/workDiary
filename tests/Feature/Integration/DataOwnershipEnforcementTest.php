<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataOwnershipEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Enums\Integration\{DataDomain, IntegrationInboxStatus};
use App\Exceptions\ExternallyLedQueueException;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Audit\AuditLog;
use App\Models\Calendar\{AppointmentRequest, Event};
use App\Models\Integration\{ExternalArticleMapping, ExternalReference, IntegrationInboxItem};
use App\Models\Inventory\{StockMovement, Warehouse};
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Models\ServiceTicket\{ServiceQueue, ServiceTicket};
use App\Plugins\Calendly\Services\CalendlyIngestService;
use App\Plugins\JtlWawi\Enums\JtlConnectionStatus;
use App\Plugins\JtlWawi\Models\{JtlConnection, JtlWarehouseMapping};
use App\Plugins\JtlWawi\Services\JtlTakeoverService;
use App\Plugins\Support\Calendar\CalendarImportStager;
use App\Plugins\Zammad\Contracts\ZammadGateway;
use App\Plugins\Zammad\Models\ZammadConnection;
use App\Plugins\Zammad\Services\ZammadTicketImporter;
use App\Services\Integration\{DataOwnershipGuard, DataOwnershipResolver, InboxActionService};
use App\Services\ServiceTicket\ServiceTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-1592: Datenführerschaft für Tickets, Bestände und Kalender. Führt die
 * Quelle bzw. WorkDiary, wird geschrieben; führt ein anderes System, entsteht
 * statt des Schreibens ein Führerschaftskonflikt, „übernehmen“ schreibt genau
 * einmal. Dazu die Matrix mit schreibfähigen Plugins und die abgeleitete
 * Bestandsführung.
 */
final class DataOwnershipEnforcementTest extends TestCase {
    use RefreshDatabase;

    private const string JTL_BASE = 'https://wawi.example.test:5883/api/eazybusiness';

    private Organization $org;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
    }

    // ── Tickets ──────────────────────────────────────────────────────────

    public function test_tickets_leading_source_writes_service_tickets(): void {
        $connection = $this->zammadConnection();
        app(DataOwnershipResolver::class)->setOwner($this->org, DataDomain::Tickets, 'zammad');

        $result = app(ZammadTicketImporter::class)->import($connection, $this->zammadGateway([$this->zammadTicket(501)]));

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, ServiceTicket::query()->count());
        $this->assertSame(0, $this->ownershipCases());
    }

    public function test_tickets_foreign_lead_holds_back_and_accept_writes_once(): void {
        $connection = $this->zammadConnection();
        app(DataOwnershipResolver::class)->setOwner($this->org->refresh(), DataDomain::Tickets, 'freescout');
        $gateway = $this->zammadGateway([$this->zammadTicket(502)]);

        $result = app(ZammadTicketImporter::class)->import($connection, $gateway);
        app(ZammadTicketImporter::class)->import($connection, $gateway);

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, ServiceTicket::query()->count(), 'Fremd geführt: kein Service-Ticket.');
        $item = $this->onlyOwnershipCase();
        $marker = $item->ownershipMarker();
        $this->assertNotNull($marker);
        $this->assertSame('tickets', $marker['domain']);
        $this->assertSame('freescout', $marker['owner']);
        $this->assertSame(1, AuditLog::query()->where('event', 'integration.ownership_blocked')->count());

        $written = app(DataOwnershipGuard::class)->accept($item);

        $this->assertInstanceOf(ServiceTicket::class, $written);
        $this->assertSame(1, ServiceTicket::query()->count());
        $this->assertSame(IntegrationInboxStatus::ResolvedRemote, $item->refresh()->status);
        $this->assertSame(1, AuditLog::query()->where('event', 'integration.ownership_overridden')->count());

        app(ZammadTicketImporter::class)->import($connection, $gateway);
        $this->assertSame(1, ServiceTicket::query()->count(), 'Kein zweites Ticket nach dem Übernehmen.');
        $this->expectException(RuntimeException::class);
        app(DataOwnershipGuard::class)->accept($item->refresh());
    }

    public function test_tickets_close_from_foreign_lead_waits_for_accept(): void {
        $connection = $this->zammadConnection();
        app(ZammadTicketImporter::class)->import($connection, $this->zammadGateway([$this->zammadTicket(503)]));
        $ticket = ServiceTicket::query()->sole();

        app(DataOwnershipResolver::class)->setOwner($this->org->refresh(), DataDomain::Tickets, 'freescout');
        app(ZammadTicketImporter::class)->import($connection, $this->zammadGateway([$this->zammadTicket(503, 'closed')]));

        $this->assertFalse($ticket->refresh()->status->isTerminal(), 'Der Abschluss wartet auf die Übernahme.');
        app(DataOwnershipGuard::class)->accept($this->onlyOwnershipCase());
        $this->assertTrue($ticket->refresh()->status->isTerminal());
    }

    public function test_local_ticket_in_externally_led_queue_is_refused(): void {
        $queue = $this->queue(ServiceQueue::OWNERSHIP_EXTERNAL);

        try {
            app(ServiceTicketService::class)->create($this->org, null, ['title' => 'Lokal', 'queue_id' => $queue->id]);
            $this->fail('Lokale Anlage in extern geführter Queue muss scheitern.');
        } catch (ExternallyLedQueueException) {
        }

        // Ohne ausdrückliche Queue weicht die Standardauflösung auf eine selbst geführte aus.
        $queue->forceFill(['is_default' => true])->save();
        $native = $this->queue(ServiceQueue::OWNERSHIP_NATIVE, 'Intern');
        $ticket = app(ServiceTicketService::class)->create($this->org, null, ['title' => 'Lokal']);
        $this->assertSame($native->id, $ticket->queue_id);
    }

    // ── Kalender ─────────────────────────────────────────────────────────

    public function test_calendar_leading_source_stages_normal_proposal(): void {
        app(DataOwnershipResolver::class)->setOwner($this->org, DataDomain::Calendar, 'caldav');

        $this->assertTrue($this->stageProposal('evt-1'));
        $this->assertSame(1, IntegrationInboxItem::query()->where('dedupe_key', 'calendar-proposal:evt-1')->count());
        $this->assertSame(0, $this->ownershipCases());
    }

    public function test_calendar_foreign_lead_holds_back_and_accept_creates_event_once(): void {
        app(DataOwnershipResolver::class)->setOwner($this->org, DataDomain::Calendar, 'msgraph');

        $this->assertFalse($this->stageProposal('evt-2'));
        $this->assertFalse($this->stageProposal('evt-2'));
        $this->assertSame(0, IntegrationInboxItem::query()->where('dedupe_key', 'calendar-proposal:evt-2')->count());
        $item = $this->onlyOwnershipCase();

        $event = app(DataOwnershipGuard::class)->accept($item);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame(1, Event::query()->count());
        $this->assertTrue(ExternalReference::query()->where('plugin_id', 'caldav')->where('referenceable_id', $event->id)->exists());
        $this->assertFalse($this->stageProposal('evt-2'));
        $this->assertSame(1, Event::query()->count(), 'Übernommen heißt einmal.');
    }

    public function test_calendar_series_from_foreign_lead_is_one_case(): void {
        app(DataOwnershipResolver::class)->setOwner($this->org, DataDomain::Calendar, 'msgraph');
        $occurrences = [$this->occurrence('ser-1', '2026-12-01 09:00:00'), $this->occurrence('ser-1', '2026-12-08 09:00:00')];

        $this->assertSame(0, app(CalendarImportStager::class)->stageSeries($this->org->id, 'caldav', 'ser-1', 'Kalender', $occurrences));
        $item = $this->onlyOwnershipCase();

        app(DataOwnershipGuard::class)->accept($item);
        $this->assertSame(2, Event::query()->count());
    }

    public function test_calendly_respects_calendar_lead(): void {
        app(DataOwnershipResolver::class)->setOwner($this->org, DataDomain::Calendar, 'msgraph');
        $payload = ['event' => 'invitee.created', 'payload' => [
            'uri' => 'https://api.calendly.com/invitees/inv-9',
            'email' => 'gast@example.com',
            'name' => 'Erika Muster',
            'scheduled_event' => ['uri' => 'https://api.calendly.com/scheduled_events/e9', 'name' => 'Erstgespräch', 'start_time' => '2026-12-01T10:00:00.000000Z', 'end_time' => '2026-12-01T10:30:00.000000Z'],
        ]];

        $this->assertNull(app(CalendlyIngestService::class)->handlePayload($this->org, $payload));
        $this->assertSame(0, AppointmentRequest::query()->count());

        $request = app(DataOwnershipGuard::class)->accept($this->onlyOwnershipCase());
        $this->assertInstanceOf(AppointmentRequest::class, $request);
        $this->assertSame(1, AppointmentRequest::query()->count());

        app(DataOwnershipResolver::class)->setOwner($this->org->refresh(), DataDomain::Calendar, 'calendly');
        $payload['payload']['uri'] = 'https://api.calendly.com/invitees/inv-10';
        $this->assertInstanceOf(AppointmentRequest::class, app(CalendlyIngestService::class)->handlePayload($this->org, $payload));
    }

    public function test_dismissed_conflict_stays_dismissed_and_new_lead_closes_open_ones(): void {
        app(DataOwnershipResolver::class)->setOwner($this->org, DataDomain::Calendar, 'msgraph');
        $this->stageProposal('evt-3');
        $this->stageProposal('evt-4');
        $dismissed = IntegrationInboxItem::query()->where('display_title', 'Termin evt-3')->sole();
        app(InboxActionService::class)->dismiss($dismissed);

        $this->assertFalse($this->stageProposal('evt-3'));
        $this->assertSame(IntegrationInboxStatus::Dismissed, $dismissed->refresh()->status, 'Verworfen bleibt verworfen.');

        app(DataOwnershipResolver::class)->setOwner($this->org->refresh(), DataDomain::Calendar, 'native');
        $this->assertTrue($this->stageProposal('evt-4'));
        $this->assertSame(0, app(DataOwnershipGuard::class)->openConflicts($this->org, DataDomain::Calendar), 'Der erlaubte Schreibweg schließt den offenen Konflikt.');
    }

    // ── Bestände ─────────────────────────────────────────────────────────

    public function test_inventory_owner_follows_stock_management(): void {
        $resolver = app(DataOwnershipResolver::class);
        $this->assertSame('native', $resolver->ownerFor($this->org, DataDomain::Inventory));

        $this->org->forceFill(['settings' => ['inventory_mode' => 'read_only', 'inventory_plugin_id' => 'jtl_wawi', 'data_ownership' => ['inventory' => 'billbee']]])->save();
        $this->assertSame('jtl_wawi', $resolver->ownerFor($this->org->refresh(), DataDomain::Inventory), 'Der alte Matrix-Eintrag zählt nicht.');

        $this->expectException(RuntimeException::class);
        $resolver->setOwner($this->org, DataDomain::Inventory, 'billbee');
    }

    public function test_inventory_takeover_books_only_with_lead_and_accept_books_once(): void {
        [$variant, $warehouse] = $this->jtlPair();
        $this->org->forceFill(['settings' => ['inventory_mode' => 'external', 'inventory_plugin_id' => 'billbee']])->save();
        FakePluginHttp::fake([self::JTL_BASE . '/v2/stocks*' => FakePluginHttp::response([
            'items' => [['itemId' => 'ITEM-O1', 'warehouseId' => 'WH-O1', 'quantityTotal' => 5]],
        ])]);

        $result = app(JtlTakeoverService::class)->importOpeningStock($this->org->refresh());
        $this->assertSame(1, $result['blocked']);
        $this->assertSame(0, StockMovement::query()->count(), 'Billbee führt: JTL bucht nicht.');

        $movement = app(DataOwnershipGuard::class)->accept($this->onlyOwnershipCase());
        $this->assertInstanceOf(StockMovement::class, $movement);
        $this->assertSame(1, StockMovement::query()->count());

        $this->org->forceFill(['settings' => ['inventory_mode' => 'local']])->save();
        $this->assertSame(0, app(JtlTakeoverService::class)->importOpeningStock($this->org->refresh())['blocked']);
        $this->assertSame(1, StockMovement::query()->count(), 'Gleicher Stichtag, gleicher Schlüssel: keine Doppelbuchung.');
    }

    // ── Matrix ───────────────────────────────────────────────────────────

    public function test_matrix_offers_only_writers_and_explains_open_conflicts(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        foreach (['zammad', 'toggl'] as $pluginId) {
            PluginSetting::create(['organization_id' => $this->org->id, 'plugin_id' => $pluginId, 'enabled' => true, 'settings' => []]);
        }
        app(DataOwnershipResolver::class)->setOwner($this->org, DataDomain::Calendar, 'msgraph');
        $this->stageProposal('evt-5');

        $this->actingAs($admin)->get(route('admin.data-ownership.index'))
            ->assertOk()
            ->assertSee('data-domain="inventory"', false)
            ->assertSee('<option value="zammad"', false)
            ->assertDontSee('<option value="toggl"', false)
            ->assertSee(__('integration.ownership.matrix.unsupported', ['name' => app(\App\Plugins\PluginManager::class)->find('msgraph')?->name()]))
            ->assertSee(__('integration.ownership.matrix.open_conflicts', ['count' => 1]));

        $this->actingAs($admin)->post(route('admin.data-ownership.update'), ['domain' => 'tickets', 'owner' => 'todoist'])
            ->assertSessionHas('error', __('integration.ownership.matrix.invalid_owner'));
        $this->actingAs($admin)->post(route('admin.data-ownership.update'), ['domain' => 'inventory', 'owner' => 'native'])
            ->assertSessionHas('error', __('integration.ownership.inventory_locked'));

        $this->actingAs($admin)->post(route('admin.data-ownership.update'), ['domain' => 'calendar', 'owner' => 'native'])
            ->assertSessionHas('success', __('integration.ownership.matrix.changed_conflicts', ['count' => 1]));
        $audit = AuditLog::query()->where('event', 'integration.data_ownership_changed')->latest('id')->firstOrFail();
        $this->assertSame('msgraph', ($audit->changes ?? [])['from'] ?? null);
        $this->assertSame(1, ($audit->changes ?? [])['open_conflicts'] ?? null);
    }

    public function test_inbox_shows_and_accepts_ownership_conflicts(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        app(DataOwnershipResolver::class)->setOwner($this->org, DataDomain::Calendar, 'msgraph');
        $this->stageProposal('evt-6');
        $item = $this->onlyOwnershipCase();

        $this->actingAs($admin)->get(route('admin.integration.inbox', ['case' => 'ownership']))
            ->assertOk()
            ->assertSee(__('integration.ownership.badge'))
            ->assertSee(route('admin.integration.inbox.accept-ownership', $item), false);

        $this->actingAs($admin)->post(route('admin.integration.inbox.accept-ownership', $item))
            ->assertSessionHas('success', __('integration.ownership.accepted'));
        $this->assertSame(1, Event::query()->count());
    }

    // ── Hilfen ───────────────────────────────────────────────────────────

    private function ownershipCases(): int {
        return IntegrationInboxItem::query()->whereLikeEscaped('dedupe_key', DataOwnershipGuard::DEDUPE_PREFIX, 'prefix')->count();
    }

    private function onlyOwnershipCase(): IntegrationInboxItem {
        return IntegrationInboxItem::query()->whereLikeEscaped('dedupe_key', DataOwnershipGuard::DEDUPE_PREFIX, 'prefix')->where('status', IntegrationInboxStatus::Open)->sole();
    }

    private function stageProposal(string $uid): bool {
        return app(CalendarImportStager::class)->stageCase(
            $this->org->id,
            'caldav',
            'Kalender',
            'calendar-proposal:' . $uid,
            IntegrationInboxItem::CASE_UNMATCHED,
            ['remote_id' => $uid, 'subject' => 'Termin ' . $uid],
            'Termin ' . $uid,
            null,
            $this->eventAttributes('Termin ' . $uid, '2026-12-01 09:00:00'),
        );
    }

    /** @return array{key: string, title: string, snapshot: array<string, mixed>, mapped: array<string, mixed>} */
    private function occurrence(string $uid, string $start): array {
        return [
            'key' => CalendarImportStager::key($uid, \Carbon\CarbonImmutable::parse($start)),
            'title' => 'Serie',
            'snapshot' => ['remote_id' => $uid, 'subject' => 'Serie', 'series_uid' => $uid, 'series_title' => 'Serie'],
            'mapped' => $this->eventAttributes('Serie', $start),
        ];
    }

    /** @return array<string, mixed> */
    private function eventAttributes(string $title, string $start): array {
        return [
            'title' => $title,
            'event_type' => 'meeting',
            'started_at' => $start,
            'ended_at' => \Carbon\CarbonImmutable::parse($start)->addHour()->format('Y-m-d H:i:s'),
            'is_all_day' => false,
            'timezone' => 'Europe/Berlin',
        ];
    }

    private function zammadConnection(): ZammadConnection {
        return ZammadConnection::query()->create([
            'organization_id' => $this->org->id,
            'name' => 'Support',
            'base_url' => 'https://zammad.example.com',
            'api_token' => 'token-123',
            'active' => true,
            'ticket_target' => 'service_ticket',
            'service_queue_id' => $this->queue(ServiceQueue::OWNERSHIP_EXTERNAL)->id,
        ]);
    }

    private function queue(string $ownership, string $name = 'Zammad-Support'): ServiceQueue {
        return ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => $name, 'data_ownership' => $ownership]);
    }

    /** @return array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null, customer: string, organization: string} */
    private function zammadTicket(int $id, string $state = 'open'): array {
        return ['id' => $id, 'number' => (string) (22000 + $id), 'title' => 'Ticket ' . $id, 'group_id' => 5, 'state' => $state, 'customer_id' => 3, 'customer' => '', 'organization' => ''];
    }

    /** @param list<array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null, customer: string, organization: string}> $tickets */
    private function zammadGateway(array $tickets): ZammadGateway {
        return new class($tickets) implements ZammadGateway {
            /** @param list<array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null, customer: string, organization: string}> $tickets */
            public function __construct(private array $tickets) {}

            public function listTickets(?int $groupId = null, int $page = 1, int $perPage = 100): array {
                return $this->tickets;
            }

            public function probe(): void {}

            public function updateTicketState(int $ticketId, ?string $state, ?string $note): bool {
                return true;
            }

            public function accountTime(int $ticketId, float $timeUnit): bool {
                return true;
            }
        };
    }

    /** @return array{0: ArticleVariant, 1: Warehouse} */
    private function jtlPair(): array {
        JtlConnection::query()->create([
            'organization_id' => $this->org->id,
            'mode' => JtlConnection::MODE_ON_PREMISE,
            'base_url' => self::JTL_BASE,
            'api_version' => '2.0',
            'allow_private_network' => true,
            'api_key' => 'KEY-TEST',
            'status' => JtlConnectionStatus::Active,
        ]);
        $article = Article::factory()->create(['organization_id' => $this->org->id]);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->org->id, 'article_id' => $article->id, 'sku' => 'SKU-O1']);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id]);
        ExternalArticleMapping::query()->create([
            'organization_id' => $this->org->id,
            'plugin_id' => 'jtl_wawi',
            'external_id' => 'ITEM-O1',
            'article_id' => $article->id,
            'article_variant_id' => $variant->id,
        ]);
        JtlWarehouseMapping::query()->create([
            'organization_id' => $this->org->id,
            'jtl_warehouse_id' => 'WH-O1',
            'name' => 'Hauptlager',
            'warehouse_id' => $warehouse->id,
        ]);

        return [$variant, $warehouse];
    }
}
