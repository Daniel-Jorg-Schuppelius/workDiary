<?php
/*
 * Created on   : Tue Jul 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeWritebackDispatchersTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{ExternalReference, IntegrationInboxItem, IntegrationOutboxEntry, Project, TimeEntry, User};
use App\Plugins\Clockify\Services\ClockifyOutboxDispatcher;
use App\Plugins\Kimai\Services\KimaiOutboxDispatcher;
use App\Plugins\OpenProject\Services\OpenProjectOutboxDispatcher;
use App\Plugins\Support\{RemoteTimeFingerprint, TimeWritebackDispatcher};
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Rückrichtung der übrigen Zeit-Plugins (Kimai, Clockify, OpenProject) auf dem
 * gemeinsamen {@see TimeWritebackDispatcher}: schreibt nur, wenn der Fremdstand
 * unverändert ist — sonst Konflikt statt Überschreiben.
 */
class TimeWritebackDispatchersTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const START = '2026-07-01 09:00';

    private const END = '2026-07-01 10:00';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function linkedEntry(string $pluginId, string $externalId, array $payload): TimeEntry {
        $user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);

        $entry = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'date' => CarbonImmutable::parse(self::START)->toDateString(),
            'started_at' => CarbonImmutable::parse(self::START),
            'ended_at' => CarbonImmutable::parse(self::END),
            'minutes' => 60,
            'description' => 'Lokal korrigiert',
            'billable' => true,
            'exported' => false,
        ]);

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => $pluginId,
            'external_type' => 'entry',
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->getKey(),
            'external_id' => $externalId,
            'payload' => $payload,
            'synced_at' => now(),
        ]);

        return $entry->fresh();
    }

    private function outbox(string $pluginId, TimeEntry $entry): IntegrationOutboxEntry {
        return IntegrationOutboxEntry::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => $pluginId,
            'operation' => TimeWritebackDispatcher::updateOperation($pluginId),
            'payload' => ['time_entry_id' => $entry->getKey()],
            'idempotency_key' => $pluginId . '-test:' . $entry->getKey(),
        ]);
    }

    public function test_kimai_patches_when_the_remote_is_unchanged(): void {
        config([
            'plugins.kimai.enabled' => true,
            'plugins.kimai.writeback' => true,
            'plugins.kimai.base_url' => 'https://kimai.example.com',
            'plugins.kimai.api_token' => 'token',
        ]);

        $fingerprint = RemoteTimeFingerprint::fromParts(
            CarbonImmutable::parse(self::START),
            CarbonImmutable::parse(self::END),
            'Unverändert',
            null,
            true,
        );
        $entry = $this->linkedEntry('kimai', 'api:77', ['fingerprint' => $fingerprint]);

        $fake = FakePluginHttp::fake([
            'https://kimai.example.com/api/timesheets/77' => FakePluginHttp::response([
                'begin' => CarbonImmutable::parse(self::START)->toIso8601String(),
                'end' => CarbonImmutable::parse(self::END)->toIso8601String(),
                'description' => 'Unverändert',
                'billable' => true,
            ]),
        ]);

        $this->assertTrue((new KimaiOutboxDispatcher)->dispatch($this->outbox('kimai', $entry)));

        $fake->assertSent(fn (RequestInterface $r): bool => $r->getMethod() === 'PATCH'
            && str_contains((string) $r->getUri(), '/api/timesheets/77'));
        $this->assertDatabaseCount('integration_inbox_items', 0);
    }

    public function test_kimai_raises_a_conflict_when_the_remote_moved_on(): void {
        config([
            'plugins.kimai.enabled' => true,
            'plugins.kimai.writeback' => true,
            'plugins.kimai.base_url' => 'https://kimai.example.com',
            'plugins.kimai.api_token' => 'token',
        ]);

        $entry = $this->linkedEntry('kimai', 'api:77', ['fingerprint' => 'stand-von-frueher']);

        $fake = FakePluginHttp::fake([
            'https://kimai.example.com/api/timesheets/77' => FakePluginHttp::response([
                'begin' => CarbonImmutable::parse(self::START)->toIso8601String(),
                'end' => CarbonImmutable::parse(self::END)->addHour()->toIso8601String(),
                'description' => 'In Kimai nachgetragen',
                'billable' => true,
            ]),
        ]);

        $this->assertTrue((new KimaiOutboxDispatcher)->dispatch($this->outbox('kimai', $entry)), 'Konflikt ist kein Fehlschlag');

        $fake->assertNotSent(fn (RequestInterface $r): bool => $r->getMethod() === 'PATCH');
        $this->assertDatabaseHas('integration_inbox_items', ['dedupe_key' => 'kimai-entry-conflict:api:77']);
    }

    public function test_clockify_keeps_project_and_tags_when_writing_back(): void {
        config([
            'plugins.clockify.enabled' => true,
            'plugins.clockify.writeback' => true,
            'plugins.clockify.api_key' => 'key',
            'plugins.clockify.workspace_id' => 'ws1',
        ]);

        $fingerprint = RemoteTimeFingerprint::fromParts(
            CarbonImmutable::parse(self::START),
            CarbonImmutable::parse(self::END),
            'Unverändert',
            null,
            true,
        );
        $entry = $this->linkedEntry('clockify', 'api:abc123', ['fingerprint' => $fingerprint]);

        $body = [
            'timeInterval' => [
                'start' => CarbonImmutable::parse(self::START)->toIso8601String(),
                'end' => CarbonImmutable::parse(self::END)->toIso8601String(),
            ],
            'description' => 'Unverändert',
            'billable' => true,
            'projectId' => 'proj-1',
            'tagIds' => ['tag-1'],
        ];

        $fake = FakePluginHttp::fake([
            'https://api.clockify.me/api/v1/workspaces/ws1/time-entries/abc123' => FakePluginHttp::response($body),
        ]);

        $this->assertTrue((new ClockifyOutboxDispatcher)->dispatch($this->outbox('clockify', $entry)));

        // Clockifys PUT ersetzt den Eintrag — Projekt und Tags müssen mitgehen.
        $fake->assertSent(function (RequestInterface $r): bool {
            if ($r->getMethod() !== 'PUT') {
                return false;
            }
            $sent = json_decode((string) $r->getBody(), true);

            return ($sent['projectId'] ?? null) === 'proj-1' && ($sent['tagIds'] ?? []) === ['tag-1'];
        });
    }

    public function test_openproject_writes_date_and_duration(): void {
        config([
            'plugins.openproject.enabled' => true,
            'plugins.openproject.writeback' => true,
            'plugins.openproject.base_url' => 'https://op.example.com',
            'plugins.openproject.api_token' => 'token',
        ]);

        $entry = $this->linkedEntry('openproject', 'openproject:te:42', [
            'fingerprint' => RemoteTimeFingerprint::fromDuration(CarbonImmutable::parse(self::START), 60),
        ]);

        $fake = FakePluginHttp::fake([
            'https://op.example.com/api/v3/time_entries/42' => function (RequestInterface $request): Psr7Response {
                if ($request->getMethod() === 'GET') {
                    return FakePluginHttp::response(['spentOn' => '2026-07-01', 'hours' => 'PT1H']);
                }

                return FakePluginHttp::response(['id' => 42]);
            },
        ]);

        $this->assertTrue((new OpenProjectOutboxDispatcher)->dispatch($this->outbox('openproject', $entry)));

        $fake->assertSent(function (RequestInterface $r): bool {
            if ($r->getMethod() !== 'PATCH') {
                return false;
            }
            $sent = json_decode((string) $r->getBody(), true);

            return ($sent['spentOn'] ?? null) === '2026-07-01' && ($sent['hours'] ?? null) === 'PT1H';
        });
    }

    public function test_openproject_raises_a_conflict_when_the_duration_changed_remotely(): void {
        config([
            'plugins.openproject.enabled' => true,
            'plugins.openproject.writeback' => true,
            'plugins.openproject.base_url' => 'https://op.example.com',
            'plugins.openproject.api_token' => 'token',
        ]);

        $entry = $this->linkedEntry('openproject', 'openproject:te:42', [
            'fingerprint' => RemoteTimeFingerprint::fromDuration(CarbonImmutable::parse(self::START), 60),
        ]);

        $fake = FakePluginHttp::fake([
            // Drüben auf 2,5 Stunden verlängert.
            'https://op.example.com/api/v3/time_entries/42' => FakePluginHttp::response(['spentOn' => '2026-07-01', 'hours' => 'PT2H30M']),
        ]);

        $this->assertTrue((new OpenProjectOutboxDispatcher)->dispatch($this->outbox('openproject', $entry)));

        $fake->assertNotSent(fn (RequestInterface $r): bool => $r->getMethod() === 'PATCH');
        $this->assertNotNull(IntegrationInboxItem::query()
            ->where('dedupe_key', 'openproject-entry-conflict:openproject:te:42')
            ->first());
    }

    public function test_disabled_writeback_stops_the_observer(): void {
        config(['plugins.kimai.enabled' => true, 'plugins.kimai.writeback' => false]);

        $entry = $this->linkedEntry('kimai', 'api:77', ['fingerprint' => 'egal']);
        $entry->update(['description' => 'Lokal geändert']);

        $this->assertDatabaseMissing('integration_outbox', ['plugin_id' => 'kimai']);
    }
}
