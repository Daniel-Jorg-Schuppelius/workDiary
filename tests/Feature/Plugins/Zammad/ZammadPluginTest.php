<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadPluginTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Zammad;

use App\Models\Audit\AuditLog;
use App\Models\ServiceTicket\ServiceQueue;
use App\Plugins\Contracts\{PluginCapability, TaskSyncer};
use App\Plugins\{PluginDiscovery, PluginHealth};
use App\Plugins\Zammad\Contracts\{ZammadGateway, ZammadGatewayFactory};
use App\Plugins\Zammad\Models\ZammadConnection;
use App\Plugins\Zammad\ZammadPlugin;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\SqidEncoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 060, MVP-129: Plugin-Verdrahtung. Auto-Discovery, angekündigte
 * TaskSync-Fähigkeit, einbahnige syncTasks-Aggregation und der per-Org-
 * Health-Check über die (gefälschte) Gateway-Factory.
 */
final class ZammadPluginTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * @param  list<array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null}>  $tickets
     */
    private function fakeFactory(array $tickets = [], bool $ping = true): void {
        $gateway = new class($tickets, $ping) implements ZammadGateway {
            /** @param list<array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null}> $tickets */
            public function __construct(private array $tickets, private bool $pingResult) {}

            public function listTickets(?int $groupId = null, int $page = 1, int $perPage = 100): array {
                return $this->tickets;
            }

            public function ping(): bool {
                return $this->pingResult;
            }

            public function updateTicketState(int $ticketId, ?string $state, ?string $note): bool {
                return true;
            }

            public function accountTime(int $ticketId, float $timeUnit): bool {
                return true;
            }
        };

        $this->app->instance(ZammadGatewayFactory::class, new class($gateway) implements ZammadGatewayFactory {
            public function __construct(private ZammadGateway $gateway) {}

            public function for(ZammadConnection $connection): ZammadGateway {
                return $this->gateway;
            }
        });
    }

    /** @return array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null} */
    private function ticket(int $id): array {
        return ['id' => $id, 'number' => (string) (22000 + $id), 'title' => "Ticket {$id}", 'group_id' => null, 'state' => 'open', 'customer_id' => null];
    }

    private function connection(bool $active = true): ZammadConnection {
        return ZammadConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Support',
            'base_url' => 'https://support.example.com',
            'api_token' => 'token-123',
            'active' => $active,
        ]);
    }

    /** Ein leeres Secret-Feld heißt „unverändert lassen“ (Platzhalter) — es löscht das gespeicherte nicht. */
    public function test_saving_without_webhook_secret_keeps_the_stored_one(): void {
        $connection = $this->connection();
        $connection->forceFill(['webhook_secret' => 'geheim'])->save();
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->post(route('admin.zammad.connection.store'), [
            'name' => 'Support',
            'base_url' => 'https://support.example.com',
            'active' => 1,
        ])->assertRedirect();

        $this->assertSame('geheim', $connection->refresh()->webhook_secret);
    }

    /** Die Seite nennt die Webhook-Adresse, die in Zammad einzutragen ist (Phase 137). */
    public function test_admin_page_shows_the_webhook_address(): void {
        $connection = $this->connection();

        $this->actingAs($this->orgAdmin())->get(route('admin.zammad.index'))
            ->assertOk()
            ->assertSee(route('api.webhooks.zammad', ['connection' => $connection->id]), false);
    }

    /** Gruppenfilter (E18), Zeitbuchung (E20) und Freigabe privater Adressen (E22) speichern; die Freigabe wird protokolliert. */
    public function test_saving_stores_group_filter_time_unit_and_private_opt_in(): void {
        $this->actingAs($this->orgAdmin())->post(route('admin.zammad.connection.store'), [
            'name' => 'Support',
            'base_url' => 'http://10.0.0.5',
            'api_token' => 'token-123',
            'active' => 1,
            'is_limited_to_mapped_groups' => 1,
            'time_unit' => 'hour',
            'allow_private_network' => 1,
        ])->assertRedirect()->assertSessionHas('success');

        $connection = ZammadConnection::query()->firstOrFail();
        $this->assertTrue($connection->is_limited_to_mapped_groups);
        $this->assertSame('hour', $connection->time_unit);
        $this->assertTrue($connection->pushesTime());
        $this->assertTrue($connection->allow_private_network);

        $audit = AuditLog::query()->withoutGlobalScopes()->where('event', 'zammad.connection_saved')->latest('id')->firstOrFail();
        $this->assertTrue($audit->changes['allow_private_network'] ?? null);
        $this->assertSame('hour', $audit->changes['time_unit'] ?? null);
    }

    /** Ohne Freigabe weist schon das Speichern eine interne Instanz-URL ab (E22). */
    public function test_private_instance_url_needs_the_opt_in(): void {
        $this->actingAs($this->orgAdmin())->post(route('admin.zammad.connection.store'), [
            'name' => 'Support',
            'base_url' => 'http://10.0.0.5',
            'api_token' => 'token-123',
            'active' => 1,
        ])->assertRedirect()->assertSessionHas('error', __('zammad::zammad.flash.private_url_blocked'));

        $this->assertSame(0, ZammadConnection::query()->count());
    }

    /** Die Seite bietet die neuen Schalter und das Ticketziel an (E18, E20). */
    public function test_admin_page_offers_the_switches_and_the_ticket_target(): void {
        $this->connection();

        $this->actingAs($this->orgAdmin())->get(route('admin.zammad.index'))
            ->assertOk()
            ->assertSee(__('zammad::zammad.queue.limited'))
            ->assertSee(__('zammad::zammad.field.time_unit'))
            ->assertSee(__('zammad::zammad.field.allow_private_network'))
            ->assertSee(route('admin.zammad.ticket-target'), false)
            ->assertSee('data-confirm-dialog', false);
    }

    /** Wechsel auf Service-Tickets braucht eine Queue, wird protokolliert und lässt sich zurücknehmen (E20). */
    public function test_switching_the_ticket_target(): void {
        $connection = $this->connection();
        $queue = ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Zammad-Support']);
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->post(route('admin.zammad.ticket-target'), ['ticket_target' => 'service_ticket'])
            ->assertSessionHasErrors('service_queue');
        $this->assertSame('task', $connection->refresh()->ticket_target);

        $this->actingAs($admin)->post(route('admin.zammad.ticket-target'), [
            'ticket_target' => 'service_ticket',
            'service_queue' => app(SqidEncoder::class)->encode(ServiceQueue::class, (int) $queue->id),
        ])->assertSessionHas('success');

        $connection->refresh();
        $this->assertSame('service_ticket', $connection->ticket_target);
        $this->assertSame((int) $queue->id, (int) $connection->service_queue_id);
        $this->assertSame('external', $queue->refresh()->data_ownership);
        $this->assertTrue(AuditLog::query()->withoutGlobalScopes()->where('event', 'zammad.ticket_target_switched')->exists());

        $this->actingAs($admin)->post(route('admin.zammad.ticket-target'), ['ticket_target' => 'task'])
            ->assertSessionHas('success');
        $this->assertSame('task', $connection->refresh()->ticket_target);
        $this->assertNull($connection->service_queue_id);
    }

    /** Ohne Helpdesk-Modul gibt es keine Service-Tickets als Ziel. */
    public function test_service_ticket_target_requires_the_helpdesk_module(): void {
        config(['license.feature_overrides' => ['module.helpdesk' => false]]);
        app(FeatureFlagResolver::class)->flush();
        $connection = $this->connection();
        $queue = ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Zammad-Support']);

        $this->actingAs($this->orgAdmin())->post(route('admin.zammad.ticket-target'), [
            'ticket_target' => 'service_ticket',
            'service_queue' => app(SqidEncoder::class)->encode(ServiceQueue::class, (int) $queue->id),
        ])->assertSessionHas('error', __('zammad::zammad.flash.helpdesk_required'));

        $this->assertSame('task', $connection->refresh()->ticket_target);
    }

    public function test_is_discovered_and_announces_task_sync(): void {
        $this->assertContains(ZammadPlugin::class, PluginDiscovery::classes());

        $plugin = new ZammadPlugin();
        $this->assertContains(PluginCapability::TaskSync, $plugin->capabilities());
        $this->assertTrue($plugin->isPerOrganization());
        $this->assertInstanceOf(TaskSyncer::class, $plugin);
    }

    public function test_sync_tasks_aggregates_created_then_unchanged(): void {
        $this->fakeFactory([$this->ticket(1), $this->ticket(2)]);
        $this->connection();

        $first = (new ZammadPlugin())->syncTasks($this->organization);
        $this->assertSame(2, $first['created']);
        $this->assertSame(0, $first['unchanged']);

        $second = (new ZammadPlugin())->syncTasks($this->organization);
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['unchanged']);
        $this->assertSame(0, $second['failed']);
    }

    public function test_sync_tasks_skips_inactive_connection(): void {
        $this->fakeFactory([$this->ticket(1)]);
        $this->connection(active: false);

        $result = (new ZammadPlugin())->syncTasks($this->organization);
        $this->assertSame(0, $result['created']);
    }

    public function test_health_ok_when_ping_succeeds(): void {
        $this->fakeFactory(ping: true);
        $this->connection();

        $this->assertTrue((new ZammadPlugin())->healthCheck()->isOk());
    }

    public function test_health_failing_when_ping_fails(): void {
        $this->fakeFactory(ping: false);
        $this->connection();

        $this->assertTrue((new ZammadPlugin())->healthCheck()->isFailing());
    }

    public function test_health_degraded_without_connection(): void {
        $this->fakeFactory();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, (new ZammadPlugin())->healthCheck()->status);
    }
}
