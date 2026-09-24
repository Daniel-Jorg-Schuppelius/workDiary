<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\Platform\User;
use App\Models\Schedule\ScheduledShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScheduleTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    // ── Index view ──────────────────────────────────────────────────────────

    public function test_guest_cannot_access_schedule(): void {
        $this->get(route('schedule.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_schedule_week(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('schedule.index', ['view' => 'week']))
            ->assertOk()
            ->assertViewIs('schedule.index')
            ->assertViewHas('view', 'week');
    }

    public function test_user_can_view_schedule_month(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('schedule.index', ['view' => 'month']))
            ->assertOk()
            ->assertViewHas('view', 'month');
    }

    /**
     * Sicherheitsaudit 2026-09-13: Der Schichtplan lud die Auswahlliste mit
     * `User::orderBy('name')->get()`. Das Benutzermodell traegt bewusst keinen
     * globalen Org-Scope, also stand dort jeder Name der gesamten Installation —
     * in Filter, Schicht-Dialog und Wochenmatrix. Das zustaendige Gate
     * (UserOrgScopingRuleTest) lief gruen, weil es `User::orderBy(` nicht kannte.
     */
    public function test_schedule_user_list_stays_inside_the_own_tenant(): void {
        $own = User::factory()->user()->create(['name' => 'Eigene Person']);
        $foreign = User::factory()->user()->create([
            'organization_id' => \App\Models\Platform\Organization::factory()->create()->id,
            'name' => 'Fremde Person',
        ]);

        app()->instance('currentOrganization', $own->organization);

        $names = $this->actingAs($own)
            ->get(route('schedule.index', ['view' => 'week']))
            ->assertOk()
            ->viewData('users')
            ->pluck('name');

        $this->assertTrue($names->contains('Eigene Person'));
        $this->assertFalse($names->contains('Fremde Person'), 'Der Schichtplan darf keine fremden Mandanten listen.');
        $this->assertNotNull($foreign->id);
    }

    public function test_schedule_index_accepts_numeric_user_filter_fallback(): void {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->user()->create(['organization_id' => $admin->organization_id]);

        $this->actingAs($admin)
            ->get(route('schedule.index', ['user' => (string) $worker->id]))
            ->assertOk()
            ->assertViewHas('userFilter', $worker->id);
    }

    // ── CRUD (admin only) ────────────────────────────────────────────────────

    public function test_regular_user_cannot_create_shift(): void {
        $user = User::factory()->user()->create();
        $target = User::factory()->user()->create();

        $this->actingAs($user)
            ->postJson(route('schedule.shifts.store'), [
                'user_id' => $target->sqid,
                'date' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_shift(): void {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->user()->create(['organization_id' => $admin->organization_id]);

        $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), [
                'user_id' => $target->sqid,
                'date' => now()->toDateString(),
                'status' => 'draft',
            ])
            ->assertCreated()
            ->assertJsonPath('user_id', $target->sqid);
    }

    public function test_admin_can_update_shift(): void {
        $admin = User::factory()->admin()->create();
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->putJson(route('schedule.shifts.update', $shift), [
                'date' => now()->addDay()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('date', now()->addDay()->toDateString());
    }

    public function test_admin_can_delete_shift(): void {
        $admin = User::factory()->admin()->create();
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('schedule.shifts.destroy', $shift))
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('scheduled_shifts', ['id' => $shift->id]);
    }

    // ── Shift types ──────────────────────────────────────────────────────────

    public function test_admin_can_create_shift_type(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('schedule.types.store'), [
                'name' => 'Frühschicht',
                'abbreviation' => 'F',
                'color' => '#22c55e',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Frühschicht');

        $this->assertDatabaseHas('shift_types', ['abbreviation' => 'F']);
    }

    public function test_regular_user_cannot_create_shift_type(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->postJson(route('schedule.types.store'), [
                'name' => 'Test',
                'abbreviation' => 'T',
                'color' => '#000000',
            ])
            ->assertForbidden();
    }

    // ── Confirm (own user) ───────────────────────────────────────────────────

    public function test_user_can_confirm_own_shift(): void {
        $user = User::factory()->user()->create();
        $shift = ScheduledShift::factory()
            ->published()
            ->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patchJson(route('schedule.shifts.confirm', $shift))
            ->assertOk()
            ->assertJsonPath('status', ScheduledShiftStatus::Confirmed->value);
    }

    public function test_user_cannot_confirm_other_users_shift(): void {
        $user = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $user->organization_id]);
        $shift = ScheduledShift::factory()
            ->published()
            ->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->patchJson(route('schedule.shifts.confirm', $shift))
            ->assertForbidden();
    }

    // ── Import ──────────────────────────────────────────────────────────────

    public function test_admin_can_access_import_page(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('schedule.import'))
            ->assertOk()
            ->assertViewIs('schedule.import.index');
    }

    /**
     * UI-Fuzz 2026-09-21: Die Datei landete auf der local-Disk
     * (storage/app/private), gelesen wurde storage/app/… — jeder Import endete
     * in „CSV-Datei nicht lesbar“ (HTTP 500).
     */
    public function test_import_preview_reads_the_uploaded_file(): void {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $csv = UploadedFile::fake()->createWithContent('plan.csv', "Datum;Mitarbeiter;Schicht\n2026-09-22;Uta Tester;Früh\n");

        $this->actingAs($admin)
            ->post(route('schedule.import.preview'), ['file' => $csv])
            ->assertOk()
            ->assertViewIs('schedule.import.preview')
            ->assertViewHas('remaining', 1)
            // Vorschau und Controller sprechen dieselbe Zuordnung (map[Spalte]).
            ->assertSee('name="map[0]"', false);
    }

    public function test_import_confirm_creates_shifts_from_the_column_mapping(): void {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->user()->create(['organization_id' => $admin->organization_id, 'name' => 'Uta Tester']);
        app()->instance('currentOrganization', $admin->organization);
        $csv = UploadedFile::fake()->createWithContent('plan.csv', "Datum;Mitarbeiter;Von;Bis\n2026-09-22;Uta Tester;08:00;16:00\n");

        $this->actingAs($admin)->post(route('schedule.import.preview'), ['file' => $csv])->assertOk();

        $this->actingAs($admin)
            ->post(route('schedule.import.confirm'), ['map' => ['date', 'user', 'start_time', 'end_time']])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('scheduled_shifts', ['user_id' => $worker->id, 'date' => '2026-09-22']);
        $this->assertSame([], Storage::disk('local')->allFiles('schedule-imports'));
    }

    public function test_import_confirm_requires_date_and_employee_columns(): void {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $csv = UploadedFile::fake()->createWithContent('plan.csv', "Datum;Mitarbeiter\n2026-09-22;Uta Tester\n");

        $this->actingAs($admin)->post(route('schedule.import.preview'), ['file' => $csv])->assertOk();

        $this->actingAs($admin)
            ->post(route('schedule.import.confirm'), ['map' => ['date', 'skip']])
            ->assertRedirect(route('schedule.import'))
            ->assertSessionHasErrors('file');
    }

    public function test_non_admin_cannot_access_import_page(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('schedule.import'))
            ->assertForbidden();
    }
}
