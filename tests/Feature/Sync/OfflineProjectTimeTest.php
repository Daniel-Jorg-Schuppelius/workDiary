<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OfflineProjectTimeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeApproval\DayClosureStatus;
use App\Models\Diary\DayClosure;
use App\Models\Integration\SyncCommand;
use App\Models\Platform\{LicenseFlagOverride, Organization, User};
use App\Models\Project\{Project, Task};
use App\Models\Time\TimeEntry;
use App\Services\Licensing\ModuleExtensions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Projektzeit offline (MVP-1593): Befehl `time.entry` aus der Eingabeleiste —
 * gleiche Regeln wie online, idempotent über die Befehls-ID, abgeschlossener
 * Tag und fehlende Pflichtangabe als Konflikt; abgeschaltete Bereiche weist
 * der Sync-Endpunkt ab.
 */
class OfflineProjectTimeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string DAY = '2030-01-15';

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2030-01-16 10:00:00'));
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Außendienst Nord',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    private function send(array $payload, ?string $uuid = null): TestResponse {
        return $this->actingAs($this->user)->postJson(route('api.internal.sync.commands'), [
            'commands' => [[
                'client_uuid' => $uuid ?? (string) Str::uuid(),
                'type' => 'time.entry',
                'payload' => $payload,
                'captured_at' => self::DAY . 'T12:00:00Z',
            ]],
        ]);
    }

    public function test_duration_entry_is_booked_like_the_entry_bar(): void {
        $this->project->forceFill(['billable' => true])->save();

        $response = $this->send(['project_id' => $this->project->sqid, 'date' => self::DAY, 'minutes' => '90', 'description' => 'Wartung vor Ort'])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'applied');

        $entry = TimeEntry::query()->sole();
        $this->assertSame('time_entries:' . $entry->id, $response->json('results.0.ref'));
        $this->assertSame(90, (int) $entry->minutes);
        $this->assertSame(self::DAY, $entry->date->toDateString());
        $this->assertSame((int) $this->user->id, (int) $entry->user_id);
        $this->assertSame((int) $this->project->id, (int) $entry->project_id);
        $this->assertTrue((bool) $entry->billable, 'Abrechenbarkeit erbt die Projektvorgabe wie online.');
        // Freitext liegt im Zielmodell, nicht doppelt im Sync-Register.
        $this->assertArrayNotHasKey('description', (array) SyncCommand::query()->sole()->payload);
    }

    public function test_range_entry_is_read_as_wall_clock_time(): void {
        $this->send(['project_id' => $this->project->sqid, 'date' => self::DAY, 'start_time' => '08:00', 'end_time' => '09:30', 'break_minutes' => '15'])
            ->assertJsonPath('results.0.status', 'applied');

        $entry = TimeEntry::query()->sole();
        $this->assertSame(self::DAY . ' 08:00:00', $entry->started_at->toDateTimeString());
        $this->assertSame(75, (int) $entry->minutes);
    }

    public function test_same_command_id_books_only_once(): void {
        $uuid = (string) Str::uuid();
        $payload = ['project_id' => $this->project->sqid, 'date' => self::DAY, 'minutes' => '30'];

        $this->send($payload, $uuid)->assertJsonPath('results.0.status', 'applied');
        $this->send($payload, $uuid)->assertJsonPath('results.0.status', 'duplicate');

        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_day_closed_meanwhile_becomes_a_conflict_without_booking(): void {
        DayClosure::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'day' => self::DAY,
            'status' => DayClosureStatus::Closed,
        ]);

        $this->send(['project_id' => $this->project->sqid, 'date' => self::DAY, 'minutes' => '60'])
            ->assertJsonPath('results.0.status', 'conflict')
            ->assertJsonPath('results.0.conflict.server.date', self::DAY)
            ->assertJsonPath('results.0.conflict.server.lock', __('sync.conflict.lock.day'));

        $this->assertSame(0, TimeEntry::query()->count());
        $this->assertSame('conflict', SyncCommand::query()->sole()->result_status->value);
    }

    public function test_missing_project_becomes_a_conflict(): void {
        $this->send(['date' => self::DAY, 'minutes' => '60'])
            ->assertJsonPath('results.0.status', 'conflict')
            ->assertJsonPath('results.0.conflict.server.missing', __('validation.attributes.project_id'));

        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_project_of_another_organization_is_rejected(): void {
        $other = Organization::factory()->create();
        app()->instance('currentOrganization', $other);
        $foreign = Project::create(['organization_id' => $other->id, 'name' => 'Fremd', 'status' => ProjectStatus::Active->value]);
        $foreignSqid = $foreign->sqid;
        app()->instance('currentOrganization', $this->organization);

        $this->send(['project_id' => $foreignSqid, 'date' => self::DAY, 'minutes' => '60'])
            ->assertJsonPath('results.0.status', 'rejected');

        $this->assertSame(0, TimeEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_task_of_another_project_is_rejected(): void {
        $otherProject = Project::create(['organization_id' => $this->organization->id, 'name' => 'Anderes', 'status' => ProjectStatus::Active->value]);
        $task = Task::create(['organization_id' => $this->organization->id, 'project_id' => $otherProject->id, 'title' => 'Fremde Aufgabe']);

        $response = $this->send(['project_id' => $this->project->sqid, 'task_id' => $task->sqid, 'date' => self::DAY, 'minutes' => '60'])
            ->assertJsonPath('results.0.status', 'rejected');

        $this->assertArrayHasKey('task_id', (array) $response->json('results.0.errors'));
        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_command_of_a_switched_off_module_is_rejected(): void {
        LicenseFlagOverride::query()->create([
            'organization_id' => $this->organization->id,
            'flag' => 'module.lms',
            'disabled_at' => now(),
            'disabled_by_user_id' => $this->user->id,
        ]);
        $this->app->make(ModuleExtensions::class)->flush();

        $this->actingAs($this->user)->postJson(route('api.internal.sync.commands'), [
            'commands' => [[
                'client_uuid' => (string) Str::uuid(),
                'type' => 'learning.unit-complete',
                'payload' => ['enrollment' => 'x', 'unit' => 'y'],
            ]],
        ])
            ->assertJsonPath('results.0.status', 'rejected')
            ->assertJsonPath('results.0.errors.command.0', __('sync.error.module_inactive'));
    }
}
