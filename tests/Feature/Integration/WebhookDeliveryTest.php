<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookDeliveryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Integration;

use App\Enums\Integration\{WebhookDeliveryStatus, WebhookEvent};
use App\Enums\Notification\NotificationEvent;
use App\Jobs\Integration\WebhookDeliveryJob;
use App\Models\Integration\{WebhookDelivery, WebhookEndpoint};
use App\Services\Integration\WebhookDispatchService;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Queue};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_subscribed_event_via_dispatcher_creates_delivery_and_queues_job(): void {
        Queue::fake();

        $endpoint = WebhookEndpoint::factory()
            ->subscribedTo([WebhookEvent::OpenIssueAssigned])
            ->create(['organization_id' => $this->organization->id]);

        // Subjekt mit organization_id (Endpoint selbst dient als Model-Subjekt).
        app(NotificationDispatcher::class)->notify(
            NotificationEvent::OpenIssueAssigned,
            $endpoint,
            null,
            ['title' => 'Issue X'],
        );

        $this->assertSame(1, WebhookDelivery::query()->withoutGlobalScopes()->count());
        $delivery = WebhookDelivery::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(WebhookEvent::OpenIssueAssigned->value, $delivery->event);
        $this->assertSame(WebhookDeliveryStatus::Pending, $delivery->status);

        Queue::assertPushed(WebhookDeliveryJob::class, 1);
    }

    public function test_only_subscribed_events_trigger(): void {
        Queue::fake();

        $endpoint = WebhookEndpoint::factory()
            ->subscribedTo([WebhookEvent::SlaBreached])
            ->create(['organization_id' => $this->organization->id]);

        // Gefeuertes Ereignis ist NICHT abonniert → keine Delivery, kein Job.
        app(NotificationDispatcher::class)->notify(
            NotificationEvent::OpenIssueAssigned,
            $endpoint,
            null,
            ['title' => 'Issue X'],
        );

        $this->assertSame(0, WebhookDelivery::query()->withoutGlobalScopes()->count());
        Queue::assertNotPushed(WebhookDeliveryJob::class);
    }

    public function test_non_webhook_event_does_not_publish(): void {
        Queue::fake();

        $endpoint = WebhookEndpoint::factory()
            ->subscribedTo([WebhookEvent::OpenIssueAssigned])
            ->create(['organization_id' => $this->organization->id]);

        // TimeCorrectionDecided hat KEINE WebhookEvent-Entsprechung.
        app(NotificationDispatcher::class)->notify(
            NotificationEvent::TimeCorrectionDecided,
            $endpoint,
            null,
            ['title' => 'decided'],
        );

        $this->assertSame(0, WebhookDelivery::query()->withoutGlobalScopes()->count());
        Queue::assertNotPushed(WebhookDeliveryJob::class);
    }

    public function test_successful_delivery_sends_correct_hmac_signature(): void {
        Http::fake(['*' => Http::response('ok', 200)]);

        $endpoint = WebhookEndpoint::factory()
            ->subscribedTo([WebhookEvent::OpenIssueAssigned])
            ->create(['organization_id' => $this->organization->id]);
        $secret = $endpoint->secret;

        // sync-Queue: publish() führt den Job direkt aus.
        app(WebhookDispatchService::class)->publish(
            WebhookEvent::OpenIssueAssigned,
            (int) $this->organization->id,
            ['subject_type' => 'OpenIssue', 'subject_id' => 7, 'title' => 'X'],
        );

        $delivery = WebhookDelivery::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(WebhookDeliveryStatus::Success, $delivery->status);
        $this->assertSame(200, $delivery->http_status);

        Http::assertSent(function ($request) use ($secret): bool {
            $timestamp = $request->header(WebhookDeliveryJob::TIMESTAMP_HEADER)[0] ?? '';
            $signature = $request->header(WebhookDeliveryJob::SIGNATURE_HEADER)[0] ?? '';
            $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $request->body(), $secret);

            return hash_equals($expected, $signature) && $signature !== 'sha256=';
        });

        // Erfolg setzt den Fehlerzähler zurück.
        $this->assertSame(0, (int) $endpoint->fresh()->consecutive_failures);
    }

    public function test_failed_delivery_is_logged_and_counts_failure(): void {
        Http::fake(['*' => Http::response('boom', 500)]);

        $endpoint = WebhookEndpoint::factory()
            ->subscribedTo([WebhookEvent::OpenIssueAssigned])
            ->create(['organization_id' => $this->organization->id, 'consecutive_failures' => 0]);

        try {
            app(WebhookDispatchService::class)->publish(
                WebhookEvent::OpenIssueAssigned,
                (int) $this->organization->id,
                ['title' => 'X'],
            );
        } catch (\Throwable) {
            // sync-Queue propagiert den Retry-Auslöser; im Servicepfad gefangen.
        }

        $delivery = WebhookDelivery::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->status);
        $this->assertSame(500, $delivery->http_status);

        $this->assertSame(1, (int) $endpoint->fresh()->consecutive_failures);
    }

    public function test_endpoint_auto_disables_after_threshold_failures(): void {
        Http::fake(['*' => Http::response('boom', 500)]);

        $endpoint = WebhookEndpoint::factory()
            ->subscribedTo([WebhookEvent::OpenIssueAssigned])
            ->create([
                'organization_id' => $this->organization->id,
                // Einen Fehler unterhalb der Schwelle → der nächste deaktiviert.
                'consecutive_failures' => WebhookEndpoint::MAX_CONSECUTIVE_FAILURES - 1,
            ]);

        try {
            app(WebhookDispatchService::class)->publish(
                WebhookEvent::OpenIssueAssigned,
                (int) $this->organization->id,
                ['title' => 'X'],
            );
        } catch (\Throwable) {
        }

        $fresh = $endpoint->fresh();
        $this->assertSame(WebhookEndpoint::MAX_CONSECUTIVE_FAILURES, (int) $fresh->consecutive_failures);
        $this->assertNotNull($fresh->disabled_at);
        $this->assertFalse($fresh->active);
        $this->assertFalse($fresh->isDeliverable());
    }

    public function test_gone_410_auto_unsubscribes_without_retry(): void {
        Http::fake(['*' => Http::response('gone', 410)]);

        $endpoint = WebhookEndpoint::factory()
            ->subscribedTo([WebhookEvent::OpenIssueAssigned])
            ->create(['organization_id' => $this->organization->id, 'consecutive_failures' => 0]);

        // 410 short-circuited VOR markFailure → publish() wirft nicht (kein Retry).
        app(WebhookDispatchService::class)->publish(
            WebhookEvent::OpenIssueAssigned,
            (int) $this->organization->id,
            ['title' => 'X'],
        );

        $delivery = WebhookDelivery::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->status);
        $this->assertSame(410, $delivery->http_status);

        // Sofort abbestellt: Soft-Delete + deaktiviert; 410 zählt NICHT als Fehlversuch.
        $fresh = WebhookEndpoint::query()->withoutGlobalScopes()->find($endpoint->id);
        $this->assertNotNull($fresh->deleted_at);
        $this->assertFalse((bool) $fresh->active);
        $this->assertSame(0, (int) $fresh->consecutive_failures);
        Http::assertSentCount(1);
    }

    public function test_disabled_endpoint_does_not_receive_publish(): void {
        Queue::fake();

        WebhookEndpoint::factory()
            ->subscribedTo([WebhookEvent::OpenIssueAssigned])
            ->disabled()
            ->create(['organization_id' => $this->organization->id]);

        $count = app(WebhookDispatchService::class)->publish(
            WebhookEvent::OpenIssueAssigned,
            (int) $this->organization->id,
            ['title' => 'X'],
        );

        $this->assertSame(0, $count);
        Queue::assertNotPushed(WebhookDeliveryJob::class);
    }
}
