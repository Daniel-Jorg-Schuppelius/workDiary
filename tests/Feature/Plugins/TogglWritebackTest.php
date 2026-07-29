<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglWritebackTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{ExternalReference, IntegrationInboxItem, IntegrationOutboxEntry, Project, TimeEntry, User};
use App\Plugins\Support\{ImportedTimeEntry, MatchingTimeImportService, RemoteTimeFingerprint, TimeWritebackDispatcher, TimeWritebackObserver};
use App\Plugins\Toggl\Services\TogglOutboxDispatcher;
use App\Plugins\Toggl\{TogglImportService, TogglPlugin};
use App\Services\Integration\InboxActionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Rückrichtung nach Toggl (MVP-437): lokale Korrekturen werden zurückgeschrieben,
 * aber niemals über eine zwischenzeitliche Änderung im Fremdsystem hinweg.
 */
class TogglWritebackTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const WORKSPACE = 4711;

    /** So sieht der Idempotenz-Schlüssel wirklich aus — die nackte ID leitet die Rückrichtung daraus ab. */
    private const ENTRY_KEY = 'toggl:123456';

    private const EXTERNAL_ID = '123456';

    private const BASE = 'https://api.track.toggl.com/api/v9';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        // enqueue() stößt die Verarbeitung sofort an — im Test rufen wir den
        // Dispatcher gezielt selbst auf.
        Queue::fake();

        config([
            'plugins.toggl.enabled' => true,
            'plugins.toggl.api_token' => 'test-token',
            'plugins.toggl.workspace_id' => self::WORKSPACE,
            'plugins.toggl.writeback' => true,
        ]);
    }

    private function linkedEntry(CarbonImmutable $start, CarbonImmutable $end, string $description): TimeEntry {
        $user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);

        $entry = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'started_at' => $start,
            'ended_at' => $end,
            'description' => $description,
            'billable' => true,
            'exported' => false,
        ]);

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => MatchingTimeImportService::EXT_TYPE_ENTRY,
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->getKey(),
            'external_id' => self::ENTRY_KEY,
            'payload' => [
                'workspace_id' => self::WORKSPACE,
                'fingerprint' => RemoteTimeFingerprint::fromParts($start, $end, $description, null, true),
            ],
            'synced_at' => now(),
        ]);

        return $entry->fresh();
    }

    public function test_local_change_enqueues_an_outbox_entry(): void {
        $entry = $this->linkedEntry(CarbonImmutable::parse('2026-07-01 09:00'), CarbonImmutable::parse('2026-07-01 10:00'), 'Alt');

        $entry->update(['description' => 'Korrigiert']);

        $this->assertDatabaseHas('integration_outbox', [
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'operation' => TimeWritebackDispatcher::updateOperation(TogglPlugin::ID),
        ]);
    }

    public function test_import_does_not_trigger_a_writeback(): void {
        $entry = $this->linkedEntry(CarbonImmutable::parse('2026-07-01 09:00'), CarbonImmutable::parse('2026-07-01 10:00'), 'Alt');

        TimeWritebackObserver::suppressed(function () use ($entry): void {
            $entry->update(['description' => 'Vom Import gesetzt']);
        });

        $this->assertDatabaseMissing('integration_outbox', [
            'plugin_id' => TogglPlugin::ID,
            'operation' => TimeWritebackDispatcher::updateOperation(TogglPlugin::ID),
        ]);
    }

    public function test_exported_entry_is_never_written_back(): void {
        $entry = $this->linkedEntry(CarbonImmutable::parse('2026-07-01 09:00'), CarbonImmutable::parse('2026-07-01 10:00'), 'Alt');
        $entry->forceFill(['exported' => true])->saveQuietly();

        $entry->fresh()->update(['description' => 'Nachträglich geändert']);

        $this->assertDatabaseMissing('integration_outbox', [
            'plugin_id' => TogglPlugin::ID,
            'operation' => TimeWritebackDispatcher::updateOperation(TogglPlugin::ID),
        ]);
    }

    public function test_unchanged_remote_is_updated(): void {
        $start = CarbonImmutable::parse('2026-07-01 09:00');
        $end = CarbonImmutable::parse('2026-07-01 10:00');
        $entry = $this->linkedEntry($start, $end, 'Alt');

        FakePluginHttp::fake([
            // Fremdstand unverändert → Fingerabdruck stimmt → PUT darf laufen
            self::BASE . '/workspaces/' . self::WORKSPACE . '/time_entries/' . self::EXTERNAL_ID => FakePluginHttp::response([
                'start' => $start->toIso8601String(),
                'stop' => $end->toIso8601String(),
                'description' => 'Alt',
                'billable' => true,
            ]),
        ]);

        $entry->update(['description' => 'Korrigiert']);
        $outbox = IntegrationOutboxEntry::query()->where('operation', TimeWritebackDispatcher::updateOperation(TogglPlugin::ID))->firstOrFail();

        $this->assertTrue(app(TogglOutboxDispatcher::class)->dispatch($outbox));
        $this->assertDatabaseCount('integration_inbox_items', 0);
    }

    public function test_changed_remote_raises_a_conflict_instead_of_overwriting(): void {
        $start = CarbonImmutable::parse('2026-07-01 09:00');
        $end = CarbonImmutable::parse('2026-07-01 10:00');
        $entry = $this->linkedEntry($start, $end, 'Alt');

        FakePluginHttp::fake([
            // Drüben wurde die Zeit inzwischen verlängert → anderer Fingerabdruck
            self::BASE . '/workspaces/' . self::WORKSPACE . '/time_entries/' . self::EXTERNAL_ID => FakePluginHttp::response([
                'start' => $start->toIso8601String(),
                'stop' => $end->addHour()->toIso8601String(),
                'description' => 'In Toggl nachgetragen',
                'billable' => true,
            ]),
        ]);

        $entry->update(['description' => 'Hier korrigiert']);
        $outbox = IntegrationOutboxEntry::query()->where('operation', TimeWritebackDispatcher::updateOperation(TogglPlugin::ID))->firstOrFail();

        $this->assertTrue(app(TogglOutboxDispatcher::class)->dispatch($outbox), 'Konflikt ist kein Fehlschlag');

        $conflict = IntegrationInboxItem::query()
            ->where('dedupe_key', 'toggl-entry-conflict:' . self::ENTRY_KEY)
            ->first();

        $this->assertNotNull($conflict, 'Konflikt muss in der Inbox landen');
        $this->assertSame('remote_changed', $conflict->remote_snapshot['reason'] ?? null);
    }

    public function test_remote_change_is_pulled_into_the_local_entry(): void {
        $start = CarbonImmutable::parse('2026-07-01 09:00');
        $end = CarbonImmutable::parse('2026-07-01 10:00');
        $entry = $this->linkedEntry($start, $end, 'Alt');

        // Stufe 1: derselbe Toggl-Eintrag, drüben um eine Stunde verlängert.
        $remote = new ImportedTimeEntry(
            entryKey: self::ENTRY_KEY,
            clientName: null,
            projectName: null,
            activity: null,
            description: 'In Toggl korrigiert',
            startedAt: $start,
            endedAt: $end->addHour(),
            billable: true,
        );

        $this->assertSame('updated', $this->ingestKnown($remote));

        $entry->refresh();
        $this->assertSame('In Toggl korrigiert', $entry->description);
        $this->assertSame(120, (int) $entry->minutes);
    }

    public function test_unchanged_remote_leaves_the_local_entry_alone(): void {
        $start = CarbonImmutable::parse('2026-07-01 09:00');
        $end = CarbonImmutable::parse('2026-07-01 10:00');
        $entry = $this->linkedEntry($start, $end, 'Alt');

        $remote = new ImportedTimeEntry(
            entryKey: self::ENTRY_KEY,
            clientName: null,
            projectName: null,
            activity: null,
            description: 'Alt',
            startedAt: $start,
            endedAt: $end,
            billable: true,
        );

        $this->assertSame('unchanged', $this->ingestKnown($remote));
        $this->assertSame('Alt', $entry->refresh()->description);
    }

    public function test_remote_change_after_export_becomes_a_conflict(): void {
        $start = CarbonImmutable::parse('2026-07-01 09:00');
        $end = CarbonImmutable::parse('2026-07-01 10:00');
        $entry = $this->linkedEntry($start, $end, 'Alt');
        $entry->forceFill(['exported' => true])->saveQuietly();

        $remote = new ImportedTimeEntry(
            entryKey: self::ENTRY_KEY,
            clientName: null,
            projectName: null,
            activity: null,
            description: 'Nach Abrechnung geändert',
            startedAt: $start,
            endedAt: $end->addHour(),
            billable: true,
        );

        $this->assertSame('conflict', $this->ingestKnown($remote));

        // Abgerechnete Zeit bleibt, wie sie ist — die Abweichung wird sichtbar.
        $this->assertSame('Alt', $entry->refresh()->description);
        $conflict = IntegrationInboxItem::query()
            ->where('dedupe_key', 'toggl-remote-changed:' . self::ENTRY_KEY)
            ->first();
        $this->assertNotNull($conflict);
        $this->assertSame('remote_changed_after_export', $conflict->remote_snapshot['reason'] ?? null);
    }

    /** Ruft den geschützten Abgleich der Import-Basis auf. */
    private function ingestKnown(ImportedTimeEntry $remote): string {
        $service = app(TogglImportService::class);
        $sync = (new \ReflectionClass($service))->getMethod('syncKnownEntry');

        return $sync->invoke($service, $this->organization, $remote);
    }

    public function test_local_deletion_enqueues_a_delete(): void {
        $entry = $this->linkedEntry(CarbonImmutable::parse('2026-07-01 09:00'), CarbonImmutable::parse('2026-07-01 10:00'), 'Alt');

        $entry->delete();

        $this->assertDatabaseHas('integration_outbox', [
            'plugin_id' => TogglPlugin::ID,
            'operation' => TimeWritebackDispatcher::deleteOperation(TogglPlugin::ID),
        ]);
    }

    public function test_the_conflict_carries_the_fields_the_inbox_works_with(): void {
        $start = CarbonImmutable::parse('2026-07-01 09:00');
        $end = CarbonImmutable::parse('2026-07-01 10:00');
        $entry = $this->linkedEntry($start, $end, 'Alt');

        FakePluginHttp::fake([
            self::BASE . '/workspaces/' . self::WORKSPACE . '/time_entries/' . self::EXTERNAL_ID => FakePluginHttp::response([
                'start' => $start->toIso8601String(),
                'stop' => $end->addHour()->toIso8601String(),
                'description' => 'In Toggl nachgetragen',
                'billable' => true,
            ]),
        ]);

        $entry->update(['description' => 'Hier korrigiert']);
        app(TogglOutboxDispatcher::class)->dispatch(
            IntegrationOutboxEntry::query()->where('operation', TimeWritebackDispatcher::updateOperation(TogglPlugin::ID))->firstOrFail(),
        );

        $conflict = IntegrationInboxItem::query()->where('dedupe_key', 'toggl-entry-conflict:' . self::ENTRY_KEY)->firstOrFail();

        // Ohne mapped_snapshot/diff_fields übernähme „Fremdstand übernehmen" nichts —
        // der Fall wäre eine Sackgasse.
        $this->assertContains('description', $conflict->diff_fields ?? []);
        $this->assertSame('In Toggl nachgetragen', $conflict->mapped_snapshot['description'] ?? null);

        app(InboxActionService::class)->acceptRemote($conflict);
        $this->assertSame('In Toggl nachgetragen', $entry->fresh()->description);
    }

    public function test_keeping_the_local_state_overrules_the_fingerprint_check(): void {
        $start = CarbonImmutable::parse('2026-07-01 09:00');
        $end = CarbonImmutable::parse('2026-07-01 10:00');
        $entry = $this->linkedEntry($start, $end, 'Alt');

        $fake = FakePluginHttp::fake([
            self::BASE . '/workspaces/' . self::WORKSPACE . '/time_entries/' . self::EXTERNAL_ID => FakePluginHttp::response([
                'start' => $start->toIso8601String(),
                'stop' => $end->addHour()->toIso8601String(),
                'description' => 'In Toggl nachgetragen',
                'billable' => true,
            ]),
        ]);

        // „Lokal behalten" enqueued generisch `timeentry.update` — der lokale Stand
        // soll auch drüben gelten, sonst meldet der nächste Lauf denselben Konflikt.
        $outbox = IntegrationOutboxEntry::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'operation' => 'timeentry.update',
            'payload' => ['external_id' => self::ENTRY_KEY, 'fields' => ['description']],
            'idempotency_key' => 'inbox-keep-local:test',
        ]);

        $this->assertTrue(app(TogglOutboxDispatcher::class)->dispatch($outbox));

        $fake->assertSent(fn (RequestInterface $r): bool => $r->getMethod() === 'PUT');
        // Kein neuer Konflikt: die Entscheidung ist durchgesetzt, nicht erneut gemeldet.
        $this->assertDatabaseCount('integration_inbox_items', 0);
    }

    public function test_an_invoiced_entry_cannot_be_pulled_to_the_remote_state(): void {
        $start = CarbonImmutable::parse('2026-07-01 09:00');
        $end = CarbonImmutable::parse('2026-07-01 10:00');
        $entry = $this->linkedEntry($start, $end, 'Alt');
        $entry->forceFill(['exported' => true])->saveQuietly();

        $remote = new ImportedTimeEntry(
            entryKey: self::ENTRY_KEY,
            clientName: null,
            projectName: null,
            activity: null,
            description: 'Nach Abrechnung geändert',
            startedAt: $start,
            endedAt: $end->addHour(),
            billable: true,
        );
        $this->assertSame('conflict', $this->ingestKnown($remote));

        $conflict = IntegrationInboxItem::query()->where('dedupe_key', 'toggl-remote-changed:' . self::ENTRY_KEY)->firstOrFail();

        $this->expectExceptionMessageMatches('/abgerechnet/');
        app(InboxActionService::class)->acceptRemote($conflict);
    }
}
