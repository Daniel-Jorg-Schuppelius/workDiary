<?php
/*
 * Created on   : Tue Jul 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteTimeDeletionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{ExternalReference, IntegrationInboxItem, Organization, Project, TimeEntry, User};
use App\Plugins\Support\{ImportedTimeEntry, RemoteSyncWindow, RemoteTimeFingerprint};
use App\Plugins\Toggl\{TogglImportService, TogglPlugin};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Löschungserkennung im Fensterabgleich: ein Eintrag, den ein **vollständiger**
 * Lauf nicht mehr liefert, ist im Fremdsystem gelöscht.
 *
 * Die Gegenprobe ist genauso wichtig wie der Fall selbst — ein gefilterter Lauf,
 * eine leere Antwort oder ein CSV-Import dürfen niemals lokal löschen.
 */
class RemoteTimeDeletionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const FROM = '2026-07-01';

    private const TO = '2026-07-31';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config(['plugins.toggl.enabled' => true]);
    }

    private function linkedEntry(string $externalKey, bool $exported = false, string $date = '2026-07-10'): TimeEntry {
        $user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);

        $entry = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'date' => $date,
            'started_at' => CarbonImmutable::parse($date . ' 09:00'),
            'ended_at' => CarbonImmutable::parse($date . ' 10:00'),
            'minutes' => 60,
            'exported' => $exported,
        ]);

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => 'entry',
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->getKey(),
            'external_id' => $externalKey,
            'payload' => [
                'fingerprint' => RemoteTimeFingerprint::fromParts(
                    CarbonImmutable::parse($date . ' 09:00'),
                    CarbonImmutable::parse($date . ' 10:00'),
                    null,
                    null,
                    false,
                ),
            ],
            'synced_at' => now(),
        ]);

        return $entry->fresh();
    }

    /** Ein weiterhin gelieferter Eintrag — sonst greift das Sicherheitsnetz. */
    private function stillDelivered(string $externalKey, string $date = '2026-07-11'): ImportedTimeEntry {
        $this->linkedEntry($externalKey, date: $date);

        return new ImportedTimeEntry(
            entryKey: $externalKey,
            clientName: null,
            projectName: null,
            activity: null,
            description: null,
            startedAt: CarbonImmutable::parse($date . ' 09:00'),
            endedAt: CarbonImmutable::parse($date . ' 10:00'),
            billable: false,
        );
    }

    /**
     * @param  array<int, ImportedTimeEntry>  $entries
     * @return array{created: int, skipped: int, unmatched: int, updated: int, conflicts: int, removed: int}
     */
    private function ingest(array $entries, ?RemoteSyncWindow $window): array {
        $service = app(TogglImportService::class);
        $ingest = (new ReflectionClass($service))->getMethod('ingest');

        /** @var array{created: int, skipped: int, unmatched: int, updated: int, conflicts: int, removed: int} $result */
        $result = $ingest->invoke($service, $this->organization, $entries, ['default_user_id' => Organization::find($this->organization->id)?->owner_id], $window);

        return $result;
    }

    private function window(): RemoteSyncWindow {
        return new RemoteSyncWindow(CarbonImmutable::parse(self::FROM), CarbonImmutable::parse(self::TO));
    }

    public function test_an_entry_the_full_run_no_longer_delivers_is_removed(): void {
        $gone = $this->linkedEntry('toggl:111');

        $result = $this->ingest([$this->stillDelivered('toggl:222')], $this->window());

        $this->assertSame(1, $result['removed']);
        $this->assertNull(TimeEntry::query()->find($gone->getKey()));
        $this->assertDatabaseMissing('external_references', ['external_id' => 'toggl:111']);
    }

    public function test_an_exported_entry_is_kept_and_reported(): void {
        $gone = $this->linkedEntry('toggl:111', exported: true);

        $result = $this->ingest([$this->stillDelivered('toggl:222')], $this->window());

        $this->assertSame(0, $result['removed']);
        $this->assertNotNull(TimeEntry::query()->find($gone->getKey()), 'Abgerechnete Zeit bleibt');
        $conflict = IntegrationInboxItem::query()->where('dedupe_key', 'toggl-remote-deleted:toggl:111')->first();
        $this->assertNotNull($conflict);
        $this->assertSame('remote_deleted_after_export', $conflict->remote_snapshot['reason'] ?? null);
    }

    public function test_an_empty_delivery_never_removes_anything(): void {
        $entry = $this->linkedEntry('toggl:111');

        // Eine API, die kurzzeitig nichts liefert, darf den Zeitraum nicht leerräumen.
        $result = $this->ingest([], $this->window());

        $this->assertSame(0, $result['removed']);
        $this->assertNotNull(TimeEntry::query()->find($entry->getKey()));
    }

    public function test_a_partial_run_without_window_never_removes_anything(): void {
        $entry = $this->linkedEntry('toggl:111');

        $result = $this->ingest([$this->stillDelivered('toggl:222')], null);

        $this->assertSame(0, $result['removed']);
        $this->assertNotNull(TimeEntry::query()->find($entry->getKey()));
    }

    public function test_csv_imported_entries_are_never_removed(): void {
        // CSV-Schlüssel adressieren kein Fremdobjekt — ihr Fehlen im API-Lauf
        // sagt nichts über eine Löschung aus.
        $entry = $this->linkedEntry('csv:abcdef0123456789');

        $result = $this->ingest([$this->stillDelivered('toggl:222')], $this->window());

        $this->assertSame(0, $result['removed']);
        $this->assertNotNull(TimeEntry::query()->find($entry->getKey()));
    }

    public function test_entries_outside_the_window_are_untouched(): void {
        $outside = $this->linkedEntry('toggl:111', date: '2026-06-15');

        $result = $this->ingest([$this->stillDelivered('toggl:222')], $this->window());

        $this->assertSame(0, $result['removed']);
        $this->assertNotNull(TimeEntry::query()->find($outside->getKey()));
    }
}
