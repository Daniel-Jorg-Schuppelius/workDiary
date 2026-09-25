<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleWiringTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Enums\AssetCompliance\AssetComplianceStatus;
use App\Enums\Import\ImportEntity;
use App\Enums\Notification\NotificationEvent;
use App\Enums\Search\SearchSourceType;
use App\Events\Contracts\NotifiesUsers;
use App\Listeners\Notification\NotificationBridge;
use App\Models\Asset\Asset;
use App\Models\Platform\{Organization, User};
use App\Modules\{ModuleRegistry, ModuleUnavailableException};
use App\Services\Ai\Contracts\NullAiInvoker;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Asset\Contracts\NullAssetComplianceStatusProvider;
use App\Services\Import\EntitySpecRegistry;
use App\Services\Invoicing\Contracts\NullPaymentStatusProvider;
use App\Services\Notification\DeadlineScans\{DeadlineScan, DeadlineScanRegistry};
use App\Services\Notification\NotificationDispatcher;
use App\Services\Routing\Contracts\NullTravelLogRecorder;
use App\Services\Search\Indexing\SearchSourceRegistry;
use App\Services\Sync\SyncCommandService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Verdrahtung der Modulgrenzen (MVP-863): Contracts sind gebunden, Null-Bindungen
 * verhalten sich wie dokumentiert, Registries lesen aus den Manifesten und die
 * Listener der Manifeste hängen an ihren Events.
 */
class ModuleWiringTest extends TestCase {
    use RefreshDatabase;

    public function test_every_contract_resolves_to_its_bound_implementation(): void {
        $registry = $this->app->make(ModuleRegistry::class);
        $bindings = $registry->bindings();
        $this->assertNotEmpty($bindings);
        foreach ($registry->contracts() as $contract => $null) {
            $expected = $bindings[$contract] ?? $null;
            $this->assertInstanceOf($expected, $this->app->make($contract), $contract);
        }
    }

    public function test_null_bindings_answer_neutrally_or_name_the_missing_module(): void {
        $this->assertSame(0.0, (new NullPaymentStatusProvider)->allocatedSum(new \App\Models\Invoicing\Invoice));
        $this->assertSame(AssetComplianceStatus::NotApplicable, (new NullAssetComplianceStatusProvider)->statusFor(new Asset));
        $this->assertFalse((new NullTravelLogRecorder)->available());

        $this->expectException(ModuleUnavailableException::class);
        (new NullTravelLogRecorder)->create([]);
    }

    public function test_null_ai_invoker_reports_the_module_as_inactive(): void {
        $this->expectException(AiUnavailableException::class);
        /** @var \App\Services\Ai\Contracts\AiRequestInterface $request */
        $request = Mockery::mock(\App\Services\Ai\Contracts\AiRequestInterface::class);
        (new NullAiInvoker)->invoke(new Organization, 'x', $request);
    }

    public function test_platform_registries_are_fed_by_the_manifests(): void {
        $registry = $this->app->make(ModuleRegistry::class);

        $scans = $this->app->make(DeadlineScanRegistry::class)->scans();
        $this->assertCount(count($registry->extensions(DeadlineScan::class)), $scans);
        $this->assertGreaterThan(20, count($scans));

        $specs = $this->app->make(EntitySpecRegistry::class);
        foreach (ImportEntity::cases() as $entity) {
            $this->assertSame($entity, $specs->for($entity)->entity());
        }

        $sources = $this->app->make(SearchSourceRegistry::class);
        foreach (SearchSourceType::cases() as $type) {
            $this->assertSame($type, $sources->get($type)->type());
        }

        $types = $this->app->make(SyncCommandService::class)->types();
        sort($types);
        $this->assertSame(['attendance.clock-in', 'attendance.clock-out', 'attendance.correct', 'comment.diary', 'form.submission', 'inventory.count', 'learning.unit-complete'], $types);
    }

    public function test_manifest_listeners_are_registered_exactly_once_for_their_events(): void {
        $dispatcher = $this->app->make(\Illuminate\Contracts\Events\Dispatcher::class);
        $raw = $dispatcher->getRawListeners();
        $expected = $this->app->make(ModuleRegistry::class)->listeners();
        $expected[NotifiesUsers::class] = [NotificationBridge::class];
        foreach ($expected as $event => $listeners) {
            foreach ($listeners as $listener) {
                $bound = $raw[$event] ?? [];
                $registered = array_values(array_filter(is_array($bound) ? $bound : [], static fn ($l): bool => is_string($l) && str_starts_with($l, $listener)));
                $this->assertCount(1, $registered, "{$listener} muss genau einmal an {$event} hängen (Event-Discovery über handle()), gefunden: " . implode(', ', $registered));
            }
        }
    }

    public function test_notification_bridge_forwards_notifying_events_to_the_dispatcher(): void {
        $subject = new User;
        $event = new class($subject) implements NotifiesUsers {
            public function __construct(private readonly Model $subject) {}

            public function notificationEvent(): NotificationEvent {
                return NotificationEvent::SecurityThreat;
            }

            public function notificationSubject(): Model {
                return $this->subject;
            }

            public function notificationAffected(): ?User {
                return null;
            }

            public function notificationPayload(): array {
                return ['title' => 'Test'];
            }
        };
        $dispatcher = Mockery::mock(NotificationDispatcher::class);
        $dispatcher->shouldReceive('notify')->once()->with(NotificationEvent::SecurityThreat, $subject, null, ['title' => 'Test'])->andReturn(1);
        $this->app->instance(NotificationDispatcher::class, $dispatcher);

        event($event);
    }

    public function test_mail_intake_handlers_run_attachments_before_ticket_threading(): void {
        $handlers = array_map(static fn (string $class): \App\Services\Mail\Contracts\MailIntakeHandler => app($class), app(ModuleRegistry::class)->extensions(\App\Services\Mail\Contracts\MailIntakeHandler::class));
        usort($handlers, static fn ($a, $b): int => $a->priority() <=> $b->priority());

        $this->assertSame([
            \App\Services\B2bCatalog\Mail\B2bOrderMailIntakeHandler::class,
            \App\Services\Invoicing\Mail\EInvoiceMailIntakeHandler::class,
            \App\Plugins\Fritzbox\FritzboxCallReportMailHandler::class,
            \App\Services\ServiceTicket\Mail\TicketThreadMailIntakeHandler::class,
        ], array_map(static fn (object $handler): string => $handler::class, $handlers));
    }

    public function test_welle4_null_bindings_answer_neutrally_or_refuse(): void {
        $this->assertFalse((new \App\Services\Stammdaten\Contracts\NullContactPushTarget)->pushAllowed());
        $this->assertInstanceOf(\App\Services\Finance\CashBookService::class, app(\App\Services\Passenger\Contracts\CashBookPosting::class));
        $this->assertInstanceOf(\App\Services\Finance\Accounting\ContactPushService::class, app(\App\Services\Stammdaten\Contracts\ContactPushTarget::class));
        $this->assertInstanceOf(\App\Services\Knowledge\Helpdesk\KnownErrorArticlePublisher::class, app(\App\Services\ServiceTicket\Contracts\KnownErrorPublisher::class));

        $this->expectException(ModuleUnavailableException::class);
        (new \App\Services\Passenger\Contracts\NullCashBookPosting)->record(new \App\Models\Finance\CashRegister, ['booked_on' => '2026-01-01', 'direction' => 'in', 'amount' => '1', 'purpose' => 'x']);
    }

    public function test_navigation_conditions_and_offboarding_steps_come_from_the_manifests(): void {
        $registry = app(ModuleRegistry::class);
        $keys = array_map(static fn (string $class): string => app($class)->key(), $registry->extensions(\App\Services\Navigation\Contracts\NavigationCondition::class));
        sort($keys);
        $this->assertSame(['accounting.local_ledger', 'knowledge.hub', 'passenger.profile', 'print.profile'], $keys);
        $this->assertCount(2, $registry->extensions(\App\Services\Org\Contracts\OffboardingStep::class));
    }
}
