<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationOutboxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Integration;

use App\Contracts\Integration\IntegrationOutboxDispatcher;
use App\Enums\Integration\IntegrationOutboxStatus;
use App\Jobs\Integration\IntegrationOutboxDeliveryJob;
use App\Models\{IntegrationInboxItem, IntegrationOutboxEntry};
use App\Services\Integration\{IntegrationOutboxDispatcherResolver, IntegrationOutboxService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 055, MVP-114: generische Integrations-Outbox — Idempotenz über
 * (Organisation, Schlüssel), Statusübergänge, Wiederholung bei vorübergehendem
 * Fehler und Kompensation (Inbox-Fall statt Rollback) nach terminalem
 * Fehlschlag. Die `inventory_outbox` bleibt davon unberührt.
 */
final class IntegrationOutboxTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private IntegrationOutboxService $outbox;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->outbox = app(IntegrationOutboxService::class);
    }

    /** Dispatcher-Stub mit steuerbarem Verhalten registrieren. */
    private function registerDispatcher(callable $onDispatch): void {
        app(IntegrationOutboxDispatcherResolver::class)->register(new class($onDispatch) implements IntegrationOutboxDispatcher {
            /** @var callable */
            private $onDispatch;

            public function __construct(callable $onDispatch) {
                $this->onDispatch = $onDispatch;
            }

            public function pluginId(): string {
                return 'fake-plugin';
            }

            public function dispatch(IntegrationOutboxEntry $entry): bool {
                return ($this->onDispatch)($entry);
            }
        });
    }

    private function enqueueEntry(string $key = 'op:1'): IntegrationOutboxEntry {
        return $this->outbox->enqueue($this->organization->id, 'fake-plugin', 'demo.op', ['x' => 1], $key);
    }

    public function test_enqueue_is_idempotent_per_key(): void {
        Queue::fake();

        $first = $this->enqueueEntry();
        $second = $this->enqueueEntry();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, IntegrationOutboxEntry::withoutGlobalScopes()->count());
        Queue::assertPushed(IntegrationOutboxDeliveryJob::class, 1);
    }

    public function test_successful_dispatch_confirms_entry(): void {
        Queue::fake();
        $entry = $this->enqueueEntry();
        $this->registerDispatcher(fn (): bool => true);

        (new IntegrationOutboxDeliveryJob($entry->id))->handle($this->outbox, app(IntegrationOutboxDispatcherResolver::class));

        $entry->refresh();
        $this->assertSame(IntegrationOutboxStatus::Confirmed, $entry->status);
        $this->assertSame(1, $entry->attempts);
        $this->assertNotNull($entry->confirmed_at);
    }

    public function test_missing_dispatcher_marks_failed_without_retry(): void {
        Queue::fake();
        $entry = $this->outbox->enqueue($this->organization->id, 'unbekannt', 'demo.op', [], 'op:x');

        (new IntegrationOutboxDeliveryJob($entry->id))->handle($this->outbox, app(IntegrationOutboxDispatcherResolver::class));

        $entry->refresh();
        $this->assertSame(IntegrationOutboxStatus::Failed, $entry->status);
        $this->assertStringContainsString('kein Dispatcher', (string) $entry->last_error);
    }

    public function test_temporary_failure_rethrows_for_queue_retry(): void {
        Queue::fake();
        $entry = $this->enqueueEntry();
        $this->registerDispatcher(function (): bool {
            throw new RuntimeException('vorübergehend nicht erreichbar');
        });

        try {
            (new IntegrationOutboxDeliveryJob($entry->id))->handle($this->outbox, app(IntegrationOutboxDispatcherResolver::class));
            $this->fail('Erwartete Weitergabe der Ausnahme für die Queue-Wiederholung.');
        } catch (RuntimeException) {
            // erwartet — Queue wiederholt mit Backoff
        }

        $entry->refresh();
        $this->assertSame(IntegrationOutboxStatus::Failed, $entry->status);
        $this->assertFalse($entry->status->isTerminal());
        $this->assertSame(1, $entry->attempts);
    }

    public function test_terminal_failure_compensates_with_inbox_item(): void {
        Queue::fake();
        $entry = $this->enqueueEntry('op:terminal');

        (new IntegrationOutboxDeliveryJob($entry->id))->failed(new RuntimeException('endgültig fehlgeschlagen'));

        $entry->refresh();
        $this->assertSame(IntegrationOutboxStatus::CompensationRequired, $entry->status);
        $this->assertTrue($entry->status->isTerminal());

        $item = IntegrationInboxItem::withoutGlobalScopes()
            ->where('dedupe_key', 'outbox-failed:op:terminal')
            ->firstOrFail();
        $this->assertSame(IntegrationInboxItem::CASE_CONFLICT, $item->case_type);
        $this->assertSame('fake-plugin', $item->plugin_id);
        $this->assertTrue($item->isOpen());

        // Wiederholtes failed() erzeugt keinen zweiten Fall.
        (new IntegrationOutboxDeliveryJob($entry->id))->failed(new RuntimeException('noch einmal'));
        $this->assertSame(1, IntegrationInboxItem::withoutGlobalScopes()->where('dedupe_key', 'outbox-failed:op:terminal')->count());
    }
}
