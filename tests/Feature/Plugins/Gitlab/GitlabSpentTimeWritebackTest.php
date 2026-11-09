<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabSpentTimeWritebackTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Gitlab;

use App\Models\Integration\{ExternalReference, IntegrationOutboxEntry};
use App\Models\Platform\PluginSetting;
use App\Models\Project\Task;
use App\Models\Time\TimeEntry;
use App\Plugins\Gitlab\GitlabPlugin;
use App\Plugins\Gitlab\Services\GitlabIssueWritebackDispatcher;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Zeit-Rückkanal nach GitLab (MVP-1513): gebuchte Zeit an einer
 * Issue-verknüpften Aufgabe geht genau einmal per `add_spent_time` an das
 * Issue; Änderungen danach werden bewusst nicht nachgezogen.
 */
class GitlabSpentTimeWritebackTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string ENDPOINT = 'https://gitlab.com/api/v4/projects/123/issues/7/add_spent_time';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Queue::fake();
    }

    /** @param array<string, mixed> $settings */
    private function configure(array $settings = []): void {
        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => GitlabPlugin::ID,
            'enabled' => true,
            'settings' => $settings + ['api_token' => 'glpat-token', 'project_id' => '123', 'time_writeback' => true],
        ]);
    }

    private function linkedTask(): Task {
        $task = Task::factory()->create(['organization_id' => $this->organization->id]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => GitlabPlugin::ID,
            'external_type' => GitlabPlugin::EXT_TYPE_ISSUE,
            'referenceable_type' => $task->getMorphClass(),
            'referenceable_id' => $task->getKey(),
            'external_id' => '123#7',
            'synced_at' => now(),
        ]);

        return $task;
    }

    /** @param array<string, mixed> $attributes */
    private function entry(array $attributes): TimeEntry {
        return TimeEntry::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'user_id' => $this->orgUser()->id,
            'description' => 'Fehleranalyse Anmeldemaske',
        ]);
    }

    private function outbox(): IntegrationOutboxEntry {
        return IntegrationOutboxEntry::query()->where('operation', GitlabIssueWritebackDispatcher::OP_SPENT_TIME)->sole();
    }

    public function test_time_on_linked_task_is_booked_once_in_documented_duration_format(): void {
        $this->configure();
        $task = $this->linkedTask();
        $entry = $this->entry(['task_id' => $task->id, 'minutes' => 90]);

        $fake = FakePluginHttp::fake([
            self::ENDPOINT => FakePluginHttp::response(['total_time_spent' => 5400, 'human_total_time_spent' => '1h 30m'], 201),
        ]);

        $outbox = $this->outbox();
        $this->assertTrue(app(GitlabIssueWritebackDispatcher::class)->dispatch($outbox));
        // Zweite Zustellung (Queue-Retry): keine zweite Buchung.
        $this->assertTrue(app(GitlabIssueWritebackDispatcher::class)->dispatch($outbox));

        $fake->assertSentCount(1);
        $fake->assertSent(function (RequestInterface $request): bool {
            $body = (array) json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST'
                && (string) $request->getUri() === self::ENDPOINT
                && $request->getHeaderLine('PRIVATE-TOKEN') === 'glpat-token'
                && ($body['duration'] ?? null) === '1h30m'
                && ($body['summary'] ?? null) === 'Fehleranalyse Anmeldemaske';
        });

        $reference = ExternalReference::query()
            ->forPlugin($this->organization->id, GitlabPlugin::ID, GitlabIssueWritebackDispatcher::EXT_TYPE_SPENT_TIME)
            ->forReferenceable($entry)
            ->sole();
        $this->assertSame(90, $reference->payload['minutes']);
        $this->assertSame(5400, $reference->payload['total_time_spent']);
    }

    public function test_changes_after_booking_are_not_enqueued_again(): void {
        $this->configure();
        $task = $this->linkedTask();
        $entry = $this->entry(['task_id' => $task->id, 'minutes' => 45]);

        FakePluginHttp::fake([self::ENDPOINT => FakePluginHttp::response(['total_time_spent' => 2700], 201)]);
        app(GitlabIssueWritebackDispatcher::class)->dispatch($this->outbox());

        $entry->update(['minutes' => 60]);
        $entry->delete();

        $this->assertSame(1, IntegrationOutboxEntry::query()->where('operation', GitlabIssueWritebackDispatcher::OP_SPENT_TIME)->count());
    }

    public function test_stopped_timer_is_booked_with_its_final_duration(): void {
        $this->configure();
        $task = $this->linkedTask();
        $entry = $this->entry(['task_id' => $task->id, 'minutes' => 0]);
        $this->assertDatabaseMissing('integration_outbox', ['operation' => GitlabIssueWritebackDispatcher::OP_SPENT_TIME]);

        $entry->update(['minutes' => 30]);

        $fake = FakePluginHttp::fake([self::ENDPOINT => FakePluginHttp::response(['total_time_spent' => 1800], 201)]);
        app(GitlabIssueWritebackDispatcher::class)->dispatch($this->outbox());

        $fake->assertSent(fn (RequestInterface $request): bool => ((array) json_decode((string) $request->getBody(), true))['duration'] === '30m');
    }

    public function test_without_opt_in_or_issue_link_nothing_is_enqueued(): void {
        $this->configure(['time_writeback' => false]);
        $this->entry(['task_id' => $this->linkedTask()->id, 'minutes' => 30]);
        $this->assertDatabaseMissing('integration_outbox', ['operation' => GitlabIssueWritebackDispatcher::OP_SPENT_TIME]);

        PluginSetting::query()->where('plugin_id', GitlabPlugin::ID)->delete();
        $this->configure();
        $unlinked = Task::factory()->create(['organization_id' => $this->organization->id]);
        $this->entry(['task_id' => $unlinked->id, 'minutes' => 30]);
        $this->assertDatabaseMissing('integration_outbox', ['operation' => GitlabIssueWritebackDispatcher::OP_SPENT_TIME]);
    }

    public function test_entry_moved_off_the_issue_before_delivery_is_not_booked(): void {
        $this->configure();
        $entry = $this->entry(['task_id' => $this->linkedTask()->id, 'minutes' => 30]);
        $outbox = $this->outbox();

        $entry->update(['task_id' => Task::factory()->create(['organization_id' => $this->organization->id])->id]);

        $fake = FakePluginHttp::fake();
        $this->assertTrue(app(GitlabIssueWritebackDispatcher::class)->dispatch($outbox));
        $fake->assertNothingSent();
    }

    public function test_transport_failure_is_not_retried_to_avoid_double_booking(): void {
        $this->configure();
        $entry = $this->entry(['task_id' => $this->linkedTask()->id, 'minutes' => 30]);

        FakePluginHttp::fake([
            self::ENDPOINT => static fn (RequestInterface $request) => throw new ConnectException('Zeitüberschreitung', new Psr7Request('POST', self::ENDPOINT)),
        ]);

        $this->assertTrue(app(GitlabIssueWritebackDispatcher::class)->dispatch($this->outbox()));
        $this->assertFalse(GitlabIssueWritebackDispatcher::alreadyBooked($this->organization->id, $entry));
    }
}
