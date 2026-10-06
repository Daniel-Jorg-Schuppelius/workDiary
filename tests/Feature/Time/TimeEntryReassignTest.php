<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryReassignTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Time;

use App\Enums\Project\ProjectStatus;
use App\Enums\Timesheet\TimesheetStatus;
use App\Enums\User\Permission as P;
use App\Models\Audit\AuditLog;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Models\Time\{TimeEntry, Timesheet};
use App\Support\{MorphMap, Sqid};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * MVP-508: Projektzeiten gesammelt einem anderen Benutzer zuordnen.
 * Transaktional, hart gesperrte Einträge blockieren die gesamte Auswahl,
 * das Selbstbearbeitungsfenster blockiert die berechtigte Aktion nicht.
 */
class TimeEntryReassignTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
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

    /**
     * Neuzuordnung greift nur auf sichtbare Zeiten (MVP-1073): die Rollen mit
     * dem Recht (Teamleitung, Buchhaltung) sehen alle Zeiten, der Handelnde
     * im Test deshalb auch.
     */
    private function grantReassign(User $user, bool $withView = true): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        foreach (array_filter([P::TimeEntryReassign, $withView ? P::TimeEntryViewAny : null]) as $permission) {
            SpatiePermission::findOrCreate($permission->value, 'web');
            $user->givePermissionTo($permission->value);
        }
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
            ->where('auditable_type', MorphMap::stableKey(TimeEntry::class))
            ->get();
        $this->assertCount(2, $audit);
        $this->assertSame($owner->id, (int) $audit->first()->getAttribute('changes')['from_user_id']);
        $this->assertSame($this->target->id, (int) $audit->first()->getAttribute('changes')['to_user_id']);
    }

    /** Sicherheitsaudit 2026-10-04, li-5: der abgeschlossene Monat des Zielbenutzers nimmt keine Zeiten mehr an. */
    public function test_reassign_into_a_closed_month_of_the_target_is_blocked(): void {
        $owner = $this->orgUser();
        $entry = $this->makeEntry($owner, ['date' => '2026-05-15']);
        \App\Models\Time\MonthClosure::query()->create([
            'organization_id' => $this->organization->id, 'user_id' => $this->target->id, 'period_year' => 2026, 'period_month' => 5,
            'status' => \App\Enums\TimeApproval\MonthClosureStatus::Approved,
        ]);

        $this->actingAs($this->actor)
            ->post(route('projects.time-entries.reassign', $this->project), $this->payload([$entry], $this->target))
            ->assertSessionHasErrors('ids');

        $this->assertSame($owner->id, $entry->fresh()->user_id);
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
        $customer = \App\Models\Customer\Customer::factory()->create(['organization_id' => $this->organization->id]);
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

    public function test_reassign_without_view_right_cannot_touch_foreign_entries(): void {
        $limited = $this->orgUser();
        $this->grantReassign($limited, withView: false);
        $owner = $this->orgUser();
        $foreign = $this->makeEntry($owner);
        $own = $this->makeEntry($limited);

        $this->actingAs($limited)
            ->post(route('projects.time-entries.reassign', $this->project), $this->payload([$foreign], $this->target))
            ->assertSessionHasErrors('ids');
        $this->assertSame($owner->id, $foreign->fresh()->user_id);

        // Auch der Dialog nennt den fremden Eintrag nicht.
        $this->actingAs($limited)
            ->get(route('projects.time-entries.reassign-dialog', $this->project) . '?' . http_build_query(['ids' => [$foreign->sqid]]))
            ->assertOk()
            ->assertViewHas('entries', fn($entries): bool => $entries->isEmpty())
            ->assertViewHas('missing', 1);

        $this->actingAs($limited)
            ->post(route('projects.time-entries.reassign', $this->project), $this->payload([$own], $this->target))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->target->id, $own->fresh()->user_id);
    }

    public function test_overview_reassigns_entries_across_projects(): void {
        $second = $this->secondProject();
        $owner = $this->orgUser();
        $a = $this->makeEntry($owner);
        $b = $this->makeEntry($owner, ['project_id' => $second->id, 'minutes' => 45]);

        $this->actingAs($this->actor)
            ->get(route('projects.times.reassign-dialog') . '?' . http_build_query(['ids' => [$a->sqid, $b->sqid]]))
            ->assertOk()
            ->assertViewHas('entries', fn($entries): bool => $entries->count() === 2)
            ->assertSee('Zweites Projekt')
            ->assertSee('action="' . route('projects.times.reassign') . '"', false);

        $this->actingAs($this->actor)
            ->post(route('projects.times.reassign'), $this->payload([$a, $b], $this->target))
            ->assertRedirect(route('projects.times'))
            ->assertSessionHas('success');

        $this->assertSame($this->target->id, $a->fresh()->user_id);
        $this->assertSame($this->target->id, $b->fresh()->user_id);

        $projectIds = AuditLog::query()
            ->where('event', 'timeEntry.reassigned')
            ->where('auditable_type', MorphMap::stableKey(TimeEntry::class))
            ->get()
            ->map(fn(AuditLog $log): int => (int) $log->getAttribute('changes')['project_id'])
            ->sort()->values()->all();
        $this->assertSame([$this->project->id, $second->id], $projectIds);
    }

    public function test_overview_reassign_requires_the_right(): void {
        $plain = $this->orgUser();
        $entry = $this->makeEntry($plain);

        $this->actingAs($plain)->get(route('projects.times.reassign-dialog'))->assertForbidden();
        $this->actingAs($plain)
            ->post(route('projects.times.reassign'), $this->payload([$entry], $this->target))
            ->assertForbidden();

        $this->assertSame($plain->id, $entry->fresh()->user_id);
    }

    public function test_overview_reassign_saves_nothing_with_locked_or_foreign_entries(): void {
        $owner = $this->orgUser();
        $free = $this->makeEntry($owner);
        $locked = $this->makeEntry($owner, ['exported' => true]);

        $this->actingAs($this->actor)
            ->from(route('projects.times'))
            ->post(route('projects.times.reassign'), $this->payload([$free, $locked], $this->target))
            ->assertSessionHasErrors('ids');
        $this->assertSame($owner->id, $free->fresh()->user_id);

        // Zeiten einer anderen Organisation sind nie Teil der Auswahl.
        $otherOrg = Organization::factory()->create();
        $stranger = User::factory()->user()->create(['organization_id' => $otherOrg->id]);
        $strangerProject = Project::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Fremdes Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $stranger->id,
        ]);
        $foreign = TimeEntry::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'project_id' => $strangerProject->id,
            'user_id' => $stranger->id,
            'date' => now()->subDays(2)->toDateString(),
            'minutes' => 30,
        ]);

        $this->actingAs($this->actor)
            ->from(route('projects.times'))
            ->post(route('projects.times.reassign'), [
                'ids' => [$free->sqid, Sqid::encode(TimeEntry::class, $foreign->id)],
                'target_user_id' => Sqid::encode(User::class, $this->target->id),
            ])
            ->assertSessionHasErrors('ids');
        $this->assertSame($owner->id, $free->fresh()->user_id);
        $this->assertSame($stranger->id, (int) TimeEntry::withoutGlobalScopes()->findOrFail($foreign->id)->user_id);
    }

    public function test_overview_offers_the_selection_only_with_the_right(): void {
        $this->makeEntry($this->orgUser());
        $range = $this->dateRangeSession(now()->subDays(10)->toDateString(), now()->toDateString());

        $this->actingAs($this->actor)->withSession($range)->get(route('projects.times'))
            ->assertOk()
            ->assertSee('data-bulk-checkbox', false)
            ->assertSee('data-bulk-select-group', false)
            ->assertSee(route('projects.times.reassign-dialog'), false);

        $plain = $this->orgUser();
        $this->makeEntry($plain);
        $this->actingAs($plain)->withSession($range)->get(route('projects.times'))
            ->assertOk()
            ->assertDontSee('data-bulk-checkbox', false)
            ->assertDontSee(route('projects.times.reassign-dialog'), false);
    }

    private function secondProject(): Project {
        return Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Zweites Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->actor->id,
        ]);
    }

    public function test_manipulated_ids_are_ignored_in_dialog(): void {
        $this->actingAs($this->actor)
            ->get(route('projects.time-entries.reassign-dialog', $this->project) . '?ids[]=manipuliert')
            ->assertOk()
            ->assertSee(__('Keine zuordenbaren Einträge in der Auswahl.'));
    }
}
