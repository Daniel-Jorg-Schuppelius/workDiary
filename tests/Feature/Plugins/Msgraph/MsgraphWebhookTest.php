<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphWebhookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Msgraph;

use App\Models\{EmailConnection, MsgraphConnection, MsgraphMailConnection, MsgraphTaskConnection, MsgraphTaskListLink, Project};
use App\Plugins\Msgraph\Jobs\{MsgraphCalendarWakeJob, MsgraphMailWakeJob, MsgraphTodoWakeJob};
use App\Plugins\Msgraph\Services\MsgraphSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Graph-Webhooks (Feature 102, Folgeausbau): Subscription-Verwaltung für
 * Zwei-Wege-Kalender, To-Do-Listen und Graph-Postfächer (msgraph:subscriptions
 * legt an/erneuert) plus generischer Empfänger `api/webhooks/msgraph` —
 * Zuordnung über subscriptionId + clientState (Konstantzeit), reines
 * Aufwecksignal (Queue-Jobs laufen den regulären Delta-/Sync-Lauf), Debounce
 * gegen Notification-Bursts.
 */
final class MsgraphWebhookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        config()->set('plugins.msgraph.enabled', true);
        config()->set('plugins.msgraph.client_id', 'test-client');
        config()->set('plugins.msgraph.client_secret', 'test-secret');
    }

    /** @param array<string, mixed> $attributes */
    private function calendarConnection(array $attributes = []): MsgraphConnection {
        return MsgraphConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-1',
            'status' => MsgraphConnection::STATUS_ACTIVE,
            'two_way' => true,
        ]);
    }

    /** @param array<string, mixed> $linkAttributes @return array{0: MsgraphTaskConnection, 1: MsgraphTaskListLink} */
    private function todoSetup(array $linkAttributes = []): array {
        $connection = MsgraphTaskConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-2',
            'status' => MsgraphTaskConnection::STATUS_ACTIVE,
        ]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $link = MsgraphTaskListLink::query()->create($linkAttributes + [
            'organization_id' => $this->organization->id,
            'todo_list_id' => 'list-1',
            'todo_list_name' => 'WorkDiary-Liste',
            'target_kind' => MsgraphTaskListLink::KIND_PROJECT,
            'project_id' => $project->id,
            'sync_mode' => MsgraphTaskListLink::MODE_BIDIRECTIONAL,
            'status' => MsgraphTaskListLink::STATUS_ACTIVE,
        ]);

        return [$connection, $link];
    }

    /** @param array<string, mixed> $attributes @return array{0: EmailConnection, 1: MsgraphMailConnection} */
    private function mailboxSetup(array $attributes = []): array {
        $mail = MsgraphMailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'mail-token',
            'status' => MsgraphMailConnection::STATUS_ACTIVE,
        ]);
        $mailbox = EmailConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'M365-Postfach',
            'transport' => EmailConnection::TRANSPORT_MSGRAPH,
            'folder' => 'INBOX',
            'active' => true,
        ]);

        return [$mailbox, $mail];
    }

    // ── Sender-Seite: msgraph:subscriptions ─────────────────────────────

    public function test_subscriptions_command_creates_subscriptions_for_all_holder_types(): void {
        $calendar = $this->calendarConnection();
        [, $link] = $this->todoSetup();
        [$mailbox] = $this->mailboxSetup();

        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/subscriptions' => FakePluginHttp::response([
                'id' => 'sub-neu',
                'expirationDateTime' => Carbon::now()->addDays(2)->format('Y-m-d\TH:i:s\Z'),
            ], 201),
        ]);

        $this->artisan('msgraph:subscriptions')->assertExitCode(0);

        foreach ([$calendar->fresh(), $link->fresh(), $mailbox->fresh()] as $holder) {
            $this->assertSame('sub-neu', $holder?->subscription_id);
            $this->assertNotSame('', (string) $holder?->webhook_secret);
            $this->assertNotNull($holder?->subscription_expires_at);
        }

        $sentResources = [];
        foreach ($fake->recorded() as $entry) {
            if ($entry['request']->getMethod() !== 'POST' || ! str_ends_with((string) $entry['request']->getUri(), '/subscriptions')) {
                continue;
            }
            /** @var array{resource?: string, changeType?: string, notificationUrl?: string} $payload */
            $payload = (array) json_decode((string) $entry['request']->getBody(), true);
            $sentResources[(string) ($payload['resource'] ?? '')] = [
                'changeType' => (string) ($payload['changeType'] ?? ''),
                'url' => (string) ($payload['notificationUrl'] ?? ''),
            ];
        }

        $this->assertSame('created,updated,deleted', $sentResources['/me/events']['changeType'] ?? null);
        $this->assertSame('created,updated,deleted', $sentResources['/me/todo/lists/list-1/tasks']['changeType'] ?? null);
        $this->assertSame('created', $sentResources["/me/mailFolders('inbox')/messages"]['changeType'] ?? null);
        foreach ($sentResources as $meta) {
            $this->assertStringContainsString('/api/webhooks/msgraph', $meta['url']);
        }
    }

    public function test_subscription_renewed_only_when_close_to_expiry(): void {
        // Subscription-Felder sind bewusst nicht fillable — wie im Service via forceFill.
        $calendar = $this->calendarConnection();
        $calendar->forceFill([
            'subscription_id' => 'sub-alt',
            'subscription_expires_at' => Carbon::now()->addDays(5),
            'webhook_secret' => 'geheim',
        ])->save();

        // Weit vor Ablauf: kein API-Kontakt.
        $idle = FakePluginHttp::fake();
        app(MsgraphSubscriptionService::class)->ensureCalendar($calendar);
        $idle->assertNothingSent();

        // Kurz vor Ablauf: PATCH-Verlängerung, Ablauf fortgeschrieben.
        $calendar->forceFill(['subscription_expires_at' => Carbon::now()->addHours(12)])->save();
        $renew = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/subscriptions/sub-alt' => FakePluginHttp::response(['id' => 'sub-alt']),
        ]);
        app(MsgraphSubscriptionService::class)->ensureCalendar($calendar->fresh() ?? $calendar);

        $renew->assertSent(fn ($request): bool => $request->getMethod() === 'PATCH'
            && str_ends_with((string) $request->getUri(), '/subscriptions/sub-alt'));
        $this->assertTrue($calendar->fresh()?->subscription_expires_at?->greaterThan(Carbon::now()->addDays(4)));
        $this->assertSame('sub-alt', $calendar->fresh()?->subscription_id);
    }

    // ── Empfänger-Seite: api/webhooks/msgraph ───────────────────────────

    public function test_webhook_validation_token_is_echoed_as_plain_text(): void {
        $response = $this->post('/api/webhooks/msgraph?validationToken=tok-42');

        $response->assertStatus(200);
        $this->assertSame('tok-42', $response->getContent());
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
    }

    public function test_webhook_dispatches_wake_jobs_per_holder_with_valid_client_state(): void {
        Queue::fake();
        $this->calendarConnection()->forceFill(['subscription_id' => 'sub-cal', 'webhook_secret' => 'sec-cal'])->save();
        [, $link] = $this->todoSetup();
        $link->forceFill(['subscription_id' => 'sub-todo', 'webhook_secret' => 'sec-todo'])->save();
        [$mailbox] = $this->mailboxSetup();
        $mailbox->forceFill(['subscription_id' => 'sub-mail', 'webhook_secret' => 'sec-mail'])->save();

        $this->postJson('/api/webhooks/msgraph', ['value' => [
            ['subscriptionId' => 'sub-cal', 'clientState' => 'sec-cal'],
            ['subscriptionId' => 'sub-todo', 'clientState' => 'sec-todo'],
            ['subscriptionId' => 'sub-mail', 'clientState' => 'sec-mail'],
        ]])->assertStatus(202);

        Queue::assertPushed(MsgraphCalendarWakeJob::class, fn (MsgraphCalendarWakeJob $job): bool => $job->organizationId === $this->organization->id);
        Queue::assertPushed(MsgraphTodoWakeJob::class, fn (MsgraphTodoWakeJob $job): bool => $job->linkId === $link->id);
        Queue::assertPushed(MsgraphMailWakeJob::class, fn (MsgraphMailWakeJob $job): bool => $job->connectionId === $mailbox->id);

        // Burst-Debounce: identischer Impuls direkt danach löst keinen zweiten Lauf aus.
        $this->postJson('/api/webhooks/msgraph', ['value' => [
            ['subscriptionId' => 'sub-cal', 'clientState' => 'sec-cal'],
        ]])->assertStatus(202);
        Queue::assertPushed(MsgraphCalendarWakeJob::class, 1);
    }

    public function test_webhook_ignores_wrong_client_state_silently(): void {
        Queue::fake();
        $this->calendarConnection()->forceFill(['subscription_id' => 'sub-cal', 'webhook_secret' => 'sec-cal'])->save();

        $this->postJson('/api/webhooks/msgraph', ['value' => [
            ['subscriptionId' => 'sub-cal', 'clientState' => 'falsch'],
            ['subscriptionId' => 'sub-unbekannt', 'clientState' => 'egal'],
        ]])->assertStatus(202); // kein Oracle — immer 202

        Queue::assertNothingPushed();
    }

    // ── Wake-Jobs: regulärer Lauf, geschützt ────────────────────────────

    public function test_todo_wake_job_runs_link_sync(): void {
        [$connection, $link] = $this->todoSetup();

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks/delta*' => FakePluginHttp::response(['value' => []]),
        ]);

        (new MsgraphTodoWakeJob($this->organization->id, $link->id))
            ->handle(app(\App\Plugins\Msgraph\Services\MsgraphTodoSyncService::class));

        $this->assertNotNull($link->fresh()?->last_run_at);
        $this->assertNotNull($connection->fresh());
    }

    public function test_calendar_wake_job_runs_delta_import(): void {
        $calendar = $this->calendarConnection();

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/calendarView/delta*' => FakePluginHttp::response([
                'value' => [],
                '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/calendarView/delta?token=wake',
            ]),
        ]);

        (new MsgraphCalendarWakeJob($this->organization->id))
            ->handle(app(\App\Plugins\Msgraph\Services\MsgraphCalendarImportService::class));

        $this->assertStringContainsString('token=wake', (string) $calendar->fresh()?->calendar_delta_link);
    }

    public function test_mail_wake_job_polls_single_mailbox(): void {
        [$mailbox] = $this->mailboxSetup();

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages*' => FakePluginHttp::response(['value' => []]),
        ]);

        (new MsgraphMailWakeJob($this->organization->id, $mailbox->id))
            ->handle(app(\App\Services\Mail\MailboxGateway::class), app(\App\Services\Mail\MailIntakeService::class));

        $this->assertNotNull($mailbox->fresh()?->last_polled_at);
    }
}
