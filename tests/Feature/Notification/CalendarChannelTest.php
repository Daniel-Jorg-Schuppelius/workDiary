<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarChannelTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Notification;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Enums\OpenIssue\OpenIssueStatus;
use App\Jobs\Notification\CalendarEventPublishJob;
use App\Models\{ExternalReference, MsgraphConnection, OpenIssue, Organization, User};
use App\Models\Notification\NotificationRule;
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Plugins\Support\Calendar\RemoteCalendarPublishService;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Kalender-Kanal der Benachrichtigungen (MVP-331, Bauturbo A11): terminartige
 * Ereignisse (Payload mit due_at) werden über die A8-Publish-Infrastruktur
 * als Kalendereintrag publiziert — stabile UID (erneutes Feuern aktualisiert
 * statt dupliziert), ohne Verbindung stiller Skip, org-isoliert. Referenz-
 * Provider im Test: Microsoft Graph (FakePluginHttp, A8-Testmuster).
 */
class CalendarChannelTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $assignee;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->assignee = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        // Msgraph-Plugin installationsweit aktivieren (keine per-Org-Zeile nötig).
        config(['plugins.msgraph.enabled' => true]);
    }

    /** @param  list<string>  $channels */
    private function makeRule(NotificationEvent $event, array $channels): NotificationRule {
        return NotificationRule::factory()->forEvent($event)->create([
            'organization_id' => $this->organization->id,
            'channels' => $channels,
        ]);
    }

    private function makeConnection(int $organizationId): MsgraphConnection {
        return MsgraphConnection::query()->create([
            'organization_id' => $organizationId,
            'access_token' => 'secret-token-123',
            'status' => MsgraphConnection::STATUS_ACTIVE,
        ]);
    }

    private function makeOverdueIssue(): OpenIssue {
        return OpenIssue::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->assignee->id,
            'assignee_user_id' => $this->assignee->id,
            'status' => OpenIssueStatus::Open->value,
            'due_at' => now()->subDay(),
        ]);
    }

    public function test_calendar_channel_publishes_deadline_event_with_stable_uid(): void {
        $this->makeRule(NotificationEvent::OpenIssueOverdue, [NotificationChannel::InApp->value, NotificationChannel::Calendar->value]);
        $this->makeConnection($this->organization->id);
        $issue = $this->makeOverdueIssue();
        $uid = CalendarEventPublishJob::uidFor($issue->getMorphClass(), (int) $issue->getKey());

        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/events' => FakePluginHttp::response(['id' => 'AAMk-1'], 201),
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        // Create trägt die stabile UID als transactionId (Graph-Idempotenz, A8).
        $fake->assertSent(function (RequestInterface $request) use ($uid): bool {
            $body = (array) json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST'
                && str_ends_with((string) $request->getUri()->getPath(), '/me/events')
                && ($body['transactionId'] ?? null) === $uid;
        });

        $ref = ExternalReference::query()
            ->where('plugin_id', MsgraphPlugin::ID)
            ->where('external_type', RemoteCalendarPublishService::EXTERNAL_TYPE)
            ->where('referenceable_type', $issue->getMorphClass())
            ->where('referenceable_id', $issue->getKey())
            ->firstOrFail();
        $this->assertSame('AAMk-1', $ref->external_id);
        $this->assertSame($uid, $ref->payload['uid'] ?? null);
    }

    public function test_refire_updates_entry_instead_of_duplicating(): void {
        $this->makeRule(NotificationEvent::OpenIssueOverdue, [NotificationChannel::InApp->value, NotificationChannel::Calendar->value]);
        $this->makeConnection($this->organization->id);
        $issue = $this->makeOverdueIssue();

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/events' => FakePluginHttp::response(['id' => 'AAMk-1'], 201),
        ]);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        // Unveränderter Folgelauf: Hash gleich → kein einziger Request.
        $silent = FakePluginHttp::fake();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $silent->assertNothingSent();
        $this->assertSame(1, ExternalReference::query()->count());

        // Frist verschoben → PATCH auf die Remote-ID, kein zweiter Create.
        $issue->forceFill(['due_at' => now()->subDays(2)])->save();
        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/events/*' => FakePluginHttp::response(['id' => 'AAMk-1']),
        ]);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $fake->assertSent(fn(RequestInterface $r): bool => $r->getMethod() === 'PATCH'
            && str_contains((string) $r->getUri(), '/me/events/AAMk-1'));
        $fake->assertNotSent(fn(RequestInterface $r): bool => $r->getMethod() === 'POST');
        $this->assertSame(1, ExternalReference::query()->count());
    }

    public function test_without_calendar_connection_channel_is_silently_skipped(): void {
        $this->makeRule(NotificationEvent::OpenIssueOverdue, [NotificationChannel::InApp->value, NotificationChannel::Calendar->value]);
        $this->makeOverdueIssue();

        $fake = FakePluginHttp::fake();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        // Kein Fehler, kein Request, keine Referenz — In-App kommt trotzdem an.
        $fake->assertNothingSent();
        $this->assertSame(0, ExternalReference::query()->count());
        $this->assertSame(1, $this->assignee->notifications()->count());
    }

    public function test_rule_without_calendar_channel_does_not_publish(): void {
        $this->makeRule(NotificationEvent::OpenIssueOverdue, [NotificationChannel::InApp->value]);
        $this->makeConnection($this->organization->id);
        $this->makeOverdueIssue();

        $fake = FakePluginHttp::fake();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $fake->assertNothingSent();
        $this->assertSame(0, ExternalReference::query()->count());
    }

    public function test_event_without_due_date_does_not_publish(): void {
        $this->makeRule(NotificationEvent::OpenIssueAssigned, [NotificationChannel::InApp->value, NotificationChannel::Calendar->value]);
        $this->makeConnection($this->organization->id);
        $issue = $this->makeOverdueIssue();

        $fake = FakePluginHttp::fake();
        app(NotificationDispatcher::class)->notify(
            NotificationEvent::OpenIssueAssigned,
            $issue,
            $this->assignee,
            ['title' => 'Zuweisung', 'message' => 'ohne Datumsbezug', 'url' => null],
        );

        $fake->assertNothingSent();
        $this->assertSame(0, ExternalReference::query()->count());
    }

    public function test_publish_is_organization_isolated(): void {
        $this->makeRule(NotificationEvent::OpenIssueOverdue, [NotificationChannel::InApp->value, NotificationChannel::Calendar->value]);
        // Verbindung gehört einer FREMDEN Organisation → darf nie benutzt werden.
        $other = Organization::factory()->create();
        $this->makeConnection((int) $other->id);
        $this->makeOverdueIssue();

        $fake = FakePluginHttp::fake();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $fake->assertNothingSent();
        $this->assertSame(0, ExternalReference::query()->count());
    }
}
