<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryReassignTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Enums\Timesheet\TimesheetStatus;
use App\Enums\User\Permission as P;
use App\Models\{AuditLog, ExternalReference, Organization, Project, TimeEntry, Timesheet, User};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-508: Projektzeiten gesammelt einem anderen Benutzer zuordnen.
 * Transaktional, hart gesperrte Einträge blockieren die gesamte Auswahl,
 * das Selbstbearbeitungsfenster blockiert die berechtigte Aktion nicht.
 */
class TimeEntryReassignTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $actor;

    private User $target;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->actor = $this->orgUser();
        $this->grantReassign($this->actor);
        $this->target = $this->orgUser();

        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Reassign-Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->actor->id,
        ]);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function grantReassign(User $user): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        SpatiePermission::findOrCreate(P::TimeEntryReassign->value, 'web');
        $user->givePermissionTo(P::TimeEntryReassign->value);
    }

    private function makeEntry(User $owner, array $overrides = []): TimeEntry {
        return TimeEntry::create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $owner->id,
            'date' => now()->subDays(2)->toDateString(),
            'minutes' => 60,
        ], $overrides));
    }

    /** @param array<int, TimeEntry> $entries */
    private function payload(array $entries, User $target): array {
        return [
            'ids' => array_map(static fn(TimeEntry $e): string => $e->sqid, $entries),
            'target_user_id' => Sqid::encode(User::class, $target->id),
        ];
    }

    public function test_authorized_user_can_reassign_multiple_entries(): void {
        $owner = $this->orgUser();
        $a = $this->makeEntry($owner);
        $b = $this->makeEntry($owner, ['minutes' => 90]);

        $this->actingAs($this->actor)
            ->post(route('projects.time-entries.reassign', $this->project), $this->payload([$a, $b], $this->target))
            ->assertRedirect(route('projects.show', ['project' => $this->project, '#' => 'time']));

        $this->assertSame($this->target->id, $a->fresh()->user_id);
        $this->assertSame($this->target->id, $b->fresh()->user_id);

        $audit = AuditLog::query()
            ->where('event', 'timeEntry.reassigned')
            ->where('auditable_type', TimeEntry::class)
            ->get();
        $this->assertCount(2, $audit);
        $this->assertSame($owner->id, (int) $audit->first()->getAttribute('changes')['from_user_id']);
        $this->assertSame($this->target->id, (int) $audit->first()->getAttribute('changes')['to_user_id']);
    }

    public function test_admin_can_reassign_without_explicit_permission(): void {
        $admin = $this->orgAdmin();
        $entry = $this->makeEntry($this->orgUser());

        $this->actingAs($admin)
            ->post(route('projects.time-entries.reassign', $this->project), $this->payload([$entry], $this->target))
            ->assertRedirect();

        $this->assertSame($this->target->id, $entry->fresh()->user_id);
    }

    public function test_user_without_permission_gets_403(): void {
        $plain = $this->orgUser();
        $entry = $this->makeEntry($plain);

        $this->actingAs($plain)
            ->post(route('projects.time-entries.reassign', $this->project), $this->payload([$entry], $this->target))
            ->assertForbidden();

        $this->actingAs($plain)
            ->get(route('projects.time-entries.reassign-dialog', $this->project))
            ->assertForbidden();

        $this->assertSame($plain->id, $entry->fresh()->user_id);
    }

    public function test_mixed_selection_with_exported_entry_saves_nothing(): void {
        $owner = $this->orgUser();
        $free = $this->makeEntry($owner);
        $locked = $this->makeEntry($owner, ['exported' => true]);

        $this->actingAs($this->actor)
            ->from(route('projects.show', $this->project))
            ->post(route('projects.time-entries.reassign', $this->project), $this->payload([$free, $locked], $this->target))
            ->assertSessionHasErrors('ids');

        $this->assertSame($owner->id, $free->fresh()->user_id, 'Gemischte Auswahl darf nie teilweise speichern.');
        $this->assertSame($owner->id, $locked->fresh()->user_id);
    }

    public function test_signed_timesheet_entry_is_blocked(): void {
        $owner = $this->orgUser();
        $timesheet = Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $owner->id,
            'work_date' => now()->subDays(2)->toDateString(),
            'status' => TimesheetStatus::Signed->value,
        ]);
        $entry = $this->makeEntry($owner, ['timesheet_id' => $timesheet->id]);

        $this->actingAs($this->actor)
            ->post(route('projects.time-entries.reassign', $this->project), $this->payload([$entry], $this->target))
            ->assertSessionHasErrors('ids');

        $this->assertSame($owner->id, $entry->fresh()->user_id);
    }

    public function test_entry_of_other_project_is_rejected(): void {
        $otherProject = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Anderes Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->actor->id,
        ]);
        $foreign = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $otherProject->id,
            'user_id' => $this->actor->id,
            'date' => now()->subDay()->toDateString(),
            'minutes' => 30,
        ]);

        $this->actingAs($this->actor)
            ->post(route('projects.time-entries.reassign', $this->project), $this->payload([$foreign], $this->target))
            ->assertSessionHasErrors('ids');

        $this->assertSame($this->actor->id, $foreign->fresh()->user_id);
    }

    public function test_cross_org_target_is_rejected(): void {
        $otherOrg = Organization::factory()->create();
        $foreignTarget = User::factory()->user()->create(['organization_id' => $otherOrg->id]);
        $entry = $this->makeEntry($this->orgUser());

        $this->actingAs($this->actor)
            ->post(route('projects.time-entries.reassign', $this->project), $this->payload([$entry], $foreignTarget))
            ->assertSessionHasErrors('target_user_id');

        $this->assertNotSame($foreignTarget->id, $entry->fresh()->user_id);
    }

    public function test_portal_and_deactivated_targets_are_rejected(): void {
        $customer = \App\Models\Customer::factory()->create(['organization_id' => $this->organization->id]);
        $portal = User::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);
        $deactivated = $this->orgUser(['deactivated_at' => now()]);
        $entry = $this->makeEntry($this->orgUser());

        foreach ([$portal, $deactivated] as $invalidTarget) {
            $this->actingAs($this->actor)
                ->post(route('projects.time-entries.reassign', $this->project), $this->payload([$entry], $invalidTarget))
                ->assertSessionHasErrors('target_user_id');
        }

        $this->assertNotSame($portal->id, $entry->fresh()->user_id);
        $this->assertNotSame($deactivated->id, $entry->fresh()->user_id);
    }

    public function test_edit_window_does_not_block_reassign(): void {
        $owner = $this->orgUser();
        $old = $this->makeEntry($owner, ['date' => now()->subDays(60)->toDateString()]);

        $this->actingAs($this->actor)
            ->post(route('projects.time-entries.reassign', $this->project), $this->payload([$old], $this->target))
            ->assertRedirect();

        $this->assertSame($this->target->id, $old->fresh()->user_id);
    }

    public function test_internal_cost_snapshot_follows_new_user(): void {
        $owner = $this->orgUser(['internal_rate' => '20.00']);
        $target = $this->orgUser(['internal_rate' => '50.00']);
        $entry = $this->makeEntry($owner, ['minutes' => 60]);
        $this->assertSame('20.00', $entry->fresh()->internal_rate?->getAmount());

        $this->actingAs($this->actor)
            ->post(route('projects.time-entries.reassign', $this->project), $this->payload([$entry], $target))
            ->assertRedirect();

        $this->assertSame('50.00', $entry->fresh()->internal_rate?->getAmount(), 'Kostensnapshot muss dem neuen Benutzer folgen.');
    }

    public function test_manual_rate_override_and_references_survive(): void {
        $owner = $this->orgUser();
        $entry = $this->makeEntry($owner, ['hourly_rate' => '99.00']);
        $entry->syncTagsFromInput([], ['wartung']);
        $reference = ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'toggl',
            'external_type' => 'entry',
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->getKey(),
            'external_id' => 'api:12345',
        ]);

        $this->actingAs($this->actor)
            ->post(route('projects.time-entries.reassign', $this->project), $this->payload([$entry], $this->target))
            ->assertRedirect();

        $fresh = $entry->fresh();
        $this->assertSame('99.00', $fresh->hourly_rate?->getAmount(), 'Manueller Satz-Override darf nicht verloren gehen.');
        $this->assertSame($this->target->id, $fresh->user_id);
        $this->assertSame('wartung', $fresh->tags()->first()?->name);
        $this->assertSame($entry->getKey(), $reference->fresh()->referenceable_id, 'Fremdsystem-Referenz bleibt am Eintrag.');
        $this->assertSame('api:12345', $reference->fresh()->external_id);
    }

    public function test_dialog_shows_blocked_entries_with_reason(): void {
        $owner = $this->orgUser();
        $free = $this->makeEntry($owner);
        $locked = $this->makeEntry($owner, ['exported' => true]);

        $this->actingAs($this->actor)
            ->get(route('projects.time-entries.reassign-dialog', $this->project) . '?' . http_build_query([
                'ids' => [$free->sqid, $locked->sqid],
            ]))
            ->assertOk()
            ->assertSee(__('Eintrag bereits exportiert'))
            ->assertSee(__('Gesperrte Einträge in der Auswahl — bitte Auswahl bereinigen:'));
    }

    public function test_manipulated_ids_are_ignored_in_dialog(): void {
        $this->actingAs($this->actor)
            ->get(route('projects.time-entries.reassign-dialog', $this->project) . '?ids[]=manipuliert')
            ->assertOk()
            ->assertSee(__('Keine zuordenbaren Einträge in der Auswahl.'));
    }
}
