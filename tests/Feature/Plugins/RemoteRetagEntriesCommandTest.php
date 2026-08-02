<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteRetagEntriesCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{ExternalReference, Project, TimeEntry, User};
use App\Plugins\RemoteSupport\{RemoteSupportPlugin, RemoteSupportService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Backfill-Command für Alt-Einträge: „Anydesk — …"-Präfix raus, Tags
 * (Anbieter + Remote) rein — Anker ist die session-ExternalReference.
 */
class RemoteRetagEntriesCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();

        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function legacyEntry(string $description, string $sessionKey, ?string $provider = 'anydesk', bool $exported = false): TimeEntry {
        $entry = TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => (int) $this->organization->owner_id,
            'date' => '2026-06-01',
            'started_at' => CarbonImmutable::parse('2026-06-01 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-06-01 10:30:00'),
            'kind' => TimeEntryKind::Work,
            'description' => $description,
            'billable' => true,
            'exported' => $exported,
        ]);

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => RemoteSupportPlugin::ID,
            'external_type' => RemoteSupportService::EXT_TYPE_SESSION,
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->getKey(),
            'external_id' => $sessionKey,
            'payload' => $provider !== null ? ['provider' => $provider] : null,
            'synced_at' => now(),
        ]);

        return $entry;
    }

    public function test_strips_provider_prefix_and_applies_tags(): void {
        $entry = $this->legacyEntry('Anydesk — Kanzlei-Server (Wartung)', 'anydesk:ad-old-1');

        $this->artisan('remote:retag-entries')->assertExitCode(0);

        $entry->refresh();
        $this->assertSame('Kanzlei-Server (Wartung)', (string) $entry->description);
        $this->assertEqualsCanonicalizing(['AnyDesk', 'Remote'], $entry->tags()->pluck('name')->all());
    }

    public function test_provider_falls_back_to_session_key_when_payload_missing(): void {
        $entry = $this->legacyEntry('Teamviewer — PC01 (Fernwartungssitzung)', 'teamviewer:tv-old-1', provider: null);

        $this->artisan('remote:retag-entries')->assertExitCode(0);

        $entry->refresh();
        $this->assertSame('PC01 (Fernwartungssitzung)', (string) $entry->description);
        $this->assertEqualsCanonicalizing(['Remote', 'TeamViewer'], $entry->tags()->pluck('name')->all());
    }

    public function test_linked_foreign_description_stays_untouched_but_gets_tags(): void {
        // Verknüpfte (fremd erfasste) Einträge tragen kein Präfix — nur Tags.
        $entry = $this->legacyEntry('Toggl: Support', 'anydesk:ad-old-2');

        $this->artisan('remote:retag-entries')->assertExitCode(0);

        $entry->refresh();
        $this->assertSame('Toggl: Support', (string) $entry->description);
        $this->assertEqualsCanonicalizing(['AnyDesk', 'Remote'], $entry->tags()->pluck('name')->all());
    }

    public function test_exported_entry_keeps_description_but_gets_tags(): void {
        $entry = $this->legacyEntry('Anydesk — Kanzlei-Server (Wartung)', 'anydesk:ad-old-3', exported: true);

        $this->artisan('remote:retag-entries')->assertExitCode(0);

        $entry->refresh();
        $this->assertSame('Anydesk — Kanzlei-Server (Wartung)', (string) $entry->description);
        $this->assertEqualsCanonicalizing(['AnyDesk', 'Remote'], $entry->tags()->pluck('name')->all());
    }

    public function test_dry_run_changes_nothing(): void {
        $entry = $this->legacyEntry('Anydesk — Kanzlei-Server (Wartung)', 'anydesk:ad-old-4');

        $this->artisan('remote:retag-entries', ['--dry-run' => true])->assertExitCode(0);

        $entry->refresh();
        $this->assertSame('Anydesk — Kanzlei-Server (Wartung)', (string) $entry->description);
        $this->assertSame(0, $entry->tags()->count());
    }

    public function test_command_is_idempotent(): void {
        $entry = $this->legacyEntry('Anydesk — Kanzlei-Server (Wartung)', 'anydesk:ad-old-5');

        $this->artisan('remote:retag-entries')->assertExitCode(0);
        $this->artisan('remote:retag-entries')->assertExitCode(0);

        $entry->refresh();
        $this->assertSame('Kanzlei-Server (Wartung)', (string) $entry->description);
        $this->assertEqualsCanonicalizing(['AnyDesk', 'Remote'], $entry->tags()->pluck('name')->all());
    }
}
