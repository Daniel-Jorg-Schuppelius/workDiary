<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\Timesheet\TimesheetStatus;
use App\Mail\TimesheetSignedMail;
use App\Models\{Material, Project, TimeEntry, Timesheet, User};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, Storage};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TimesheetTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'TS-Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_user_can_create_timesheet(): void {
        $this->actingAs($this->user)
            ->post(route('projects.timesheets.store', $this->project), [
                'work_date' => '2030-02-01',
                'customer_name' => 'Kunde GmbH',
                'customer_email' => 'kunde@example.com',
                'notes' => 'Erste Anfahrt',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('timesheets', [
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'work_date' => '2030-02-01 00:00:00',
            'status' => TimesheetStatus::Draft->value,
            'customer_name' => 'Kunde GmbH',
        ]);
    }

    public function test_create_lands_on_new_timesheet_with_open_entry_dialog(): void {
        // Flow-Regression: Nach dem Anlegen muss der Nutzer im frisch erzeugten
        // Stundenzettel stehen — und zwar mit geöffnetem Zeilendialog, sonst
        // bricht die Kette „anlegen → eintragen, was gemacht wurde" ab.
        $response = $this->actingAs($this->user)
            ->post(route('projects.timesheets.store', $this->project), [
                'work_date' => '2030-02-01',
            ]);

        /** @var Timesheet $ts */
        $ts = Timesheet::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('projects.timesheets.show', [$this->project, $ts, 'add' => 'entry']));

        $this->actingAs($this->user)
            ->get(route('projects.timesheets.show', [$this->project, $ts, 'add' => 'entry']))
            ->assertOk()
            ->assertSee('data-entry-modal-autoopen', false);
    }

    public function test_create_from_dialog_returns_redirect_target_as_json(): void {
        // Dialog-Submits laufen per fetch; einer 302 folgt nur der fetch selbst
        // und die JS-Schicht lädt danach die Ausgangsseite neu — der Zettel
        // bliebe unsichtbar. Deshalb muss das Ziel als JSON zurückkommen.
        $response = $this->actingAs($this->user)
            ->withHeader('X-Entry-Dialog', '1')
            ->post(route('projects.timesheets.store', $this->project), [
                'work_date' => '2030-02-02',
            ]);

        /** @var Timesheet $ts */
        $ts = Timesheet::query()->latest('id')->firstOrFail();
        $response->assertOk()->assertExactJson([
            'redirect' => route('projects.timesheets.show', [$this->project, $ts, 'add' => 'entry']),
        ]);
    }

    public function test_owner_cannot_self_lock_or_sign_via_mass_assignment(): void {
        // Regression Mass-Assignment: status ist kein Formularfeld mehr —
        // ein Owner darf sich nicht per PUT status=locked/signed selbst
        // sperren/signieren (umgeht Admin-Lock + SignatureService).
        $ts = $this->makeTimesheet();

        $this->actingAs($this->user)
            ->put(route('projects.timesheets.update', [$this->project, $ts]), [
                'work_date' => '2030-02-15',
                'status' => TimesheetStatus::Locked->value,
            ])
            ->assertRedirect();

        $this->assertSame(TimesheetStatus::Draft, $ts->fresh()->status);
    }

    public function test_create_ignores_injected_status(): void {
        // Auch beim Anlegen darf ein mitgeschicktes status nichts überschreiben.
        $this->actingAs($this->user)
            ->post(route('projects.timesheets.store', $this->project), [
                'work_date' => '2030-03-01',
                'status' => TimesheetStatus::Signed->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('timesheets', [
            'work_date' => '2030-03-01 00:00:00',
            'user_id' => $this->user->id,
            'status' => TimesheetStatus::Draft->value,
        ]);
    }

    public function test_entry_recalculates_totals(): void {
        $ts = $this->makeTimesheet();
        $ts->entries()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => $ts->work_date,
            'minutes' => 90,
            'kind' => TimeEntryKind::Work->value,
        ]);

        $this->assertSame(90, (int) $ts->fresh()->totals_minutes);
    }

    public function test_material_usage_computes_line_total(): void {
        $ts = $this->makeTimesheet();
        $material = Material::create([
            'organization_id' => $this->organization->id,
            'sku' => 'TST-1',
            'name' => 'Testartikel',
            'unit' => 'Stk',
            'default_unit_price' => 5.0000,
            'tax_rate' => 19.00,
            'is_active' => true,
        ]);
        $usage = $ts->materialUsages()->create([
            'material_id' => $material->id,
            'description' => $material->name,
            'quantity' => 3,
            'unit' => $material->unit,
            'unit_price' => $material->default_unit_price,
            'tax_rate' => $material->tax_rate,
        ]);

        $this->assertSame('15.00', $usage->fresh()->line_total_net?->getAmount());
        $this->assertSame('15.00', (string) $ts->fresh()->totals_material_net);
    }

    public function test_signature_locks_editing_and_dispatches_mail(): void {
        Storage::fake('local');
        Mail::fake();

        $ts = $this->makeTimesheet(['customer_email' => 'kunde@example.com']);
        $png = $this->fakePngBase64();

        $this->actingAs($this->user)
            ->post(route('projects.timesheets.sign', [$this->project, $ts]), [
                'signature' => $png,
                'customer_name' => 'Max Kunde',
            ])
            ->assertRedirect();

        $ts->refresh();
        $this->assertSame(TimesheetStatus::Signed, $ts->status);
        $this->assertNotNull($ts->signed_at);
        $this->assertNotNull($ts->signature_attachment_id);
        $this->assertFalse($ts->canEdit());

        Mail::assertSent(TimesheetSignedMail::class);
    }

    public function test_signed_timesheet_blocks_entry_changes(): void {
        $ts = $this->makeTimesheet(['status' => TimesheetStatus::Signed->value]);

        $this->actingAs($this->user)
            ->post(route('projects.timesheets.entries.store', [$this->project, $ts]), [
                'date' => $ts->work_date->toDateString(),
                'minutes' => 60,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_lock_and_unlock_timesheet(): void {
        $ts = $this->makeTimesheet(['status' => TimesheetStatus::Signed->value]);

        $this->actingAs($this->admin)
            ->post(route('projects.timesheets.lock', [$this->project, $ts]))
            ->assertRedirect();

        $this->assertSame(TimesheetStatus::Locked, $ts->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('projects.timesheets.unlock', [$this->project, $ts]))
            ->assertRedirect();

        $this->assertSame(TimesheetStatus::Signed, $ts->fresh()->status);
    }

    public function test_public_signature_via_magic_token(): void {
        Storage::fake('local');
        $ts = $this->makeTimesheet();
        $ts->forceFill(['magic_token' => 'tok123', 'magic_expires_at' => now()->addDay()])->save();

        $this->get(route('timesheets.public-sign', 'tok123'))->assertOk();

        $this->post(route('timesheets.public-sign.submit', 'tok123'), [
            'signature' => $this->fakePngBase64(),
            'customer_name' => 'Extern Kunde',
        ])->assertRedirect(route('timesheets.public-thanks'));

        $ts->refresh();
        $this->assertSame(TimesheetStatus::Signed, $ts->status);
        $this->assertNull($ts->magic_token);
    }

    public function test_expired_magic_token_is_rejected(): void {
        $ts = $this->makeTimesheet();
        $ts->forceFill(['magic_token' => 'old', 'magic_expires_at' => now()->subDay()])->save();

        $this->get(route('timesheets.public-sign', 'old'))->assertStatus(410);
    }

    public function test_index_accepts_numeric_project_filter_fallback(): void {
        $today = CarbonImmutable::today()->toDateString();

        $otherProject = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Anderes Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        $tsA = Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'work_date' => $today,
            'status' => TimesheetStatus::Draft->value,
        ]);
        Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $otherProject->id,
            'user_id' => $this->admin->id,
            'work_date' => $today,
            'status' => TimesheetStatus::Draft->value,
        ]);

        $this->actingAs($this->admin)
            ->get(route('timesheets.index', [
                'scope' => 'team',
                'project' => (string) $this->project->id,
            ]))
            ->assertOk()
            ->assertViewHas('timesheets', static function ($timesheets) use ($tsA): bool {
                $items = $timesheets->items();
                return count($items) === 1 && (int) $items[0]->id === (int) $tsA->id;
            })
            ->assertViewHas('selectedProjectSqid', Sqid::encode(Project::class, $this->project->id));
    }

    public function test_unsigned_timesheet_can_be_deleted_and_frees_its_times(): void {
        // Solange nichts signiert/gesperrt ist, muss der Zettel weg dürfen.
        // Die erfassten Zeiten sind eigenständig (FK nullOnDelete) und stehen
        // danach wieder zur Übernahme bereit.
        $ts = $this->makeTimesheet();
        $entry = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'timesheet_id' => $ts->id,
            'date' => $ts->work_date,
            'minutes' => 60,
            'kind' => TimeEntryKind::Work->value,
        ]);

        $this->actingAs($this->user)
            ->delete(route('projects.timesheets.destroy', [$this->project, $ts]))
            ->assertRedirect(route('projects.show', $this->project));

        $this->assertDatabaseMissing('timesheets', ['id' => $ts->id]);
        $this->assertNotNull($entry->fresh(), 'Die erfasste Zeit darf nicht mitgelöscht werden.');
        $this->assertNull($entry->fresh()?->timesheet_id);
    }

    public function test_signed_timesheet_cannot_be_deleted(): void {
        $ts = $this->makeTimesheet(['status' => TimesheetStatus::Signed->value]);

        $this->actingAs($this->user)
            ->delete(route('projects.timesheets.destroy', [$this->project, $ts]))
            ->assertForbidden();

        $this->assertDatabaseHas('timesheets', ['id' => $ts->id]);
    }

    public function test_delete_button_appears_only_while_unsigned(): void {
        $draft = $this->makeTimesheet();
        $signed = $this->makeTimesheet([
            'work_date' => '2030-02-16',
            'status' => TimesheetStatus::Signed->value,
        ]);

        // Auf die Rückfrage prüfen, nicht auf die URL: die Destroy-Route ist
        // wortgleich mit der Show-Route und unterscheidet sich nur im Verb.
        $confirm = 'Erfasste Zeiten bleiben erhalten';

        $this->actingAs($this->user)
            ->get(route('projects.timesheets.show', [$this->project, $draft]))
            ->assertOk()
            ->assertSee($confirm);

        $this->actingAs($this->user)
            ->get(route('projects.timesheets.show', [$this->project, $signed]))
            ->assertOk()
            ->assertDontSee($confirm);
    }

    public function test_entry_can_be_recorded_as_duration_with_tags(): void {
        // Der Zeilendialog bietet jetzt Dauer statt nur Von/Bis und einen
        // Tag-Picker — beides muss serverseitig ankommen.
        $ts = $this->makeTimesheet();

        $this->actingAs($this->user)
            ->post(route('projects.timesheets.entries.store', [$this->project, $ts]), [
                'minutes' => 90,
                'description' => 'Nacherfasst',
                'new_tags' => 'Notdienst',
            ])
            ->assertRedirect();

        $entry = TimeEntry::query()->where('timesheet_id', $ts->id)->sole();
        $this->assertSame(90, (int) $entry->minutes);
        $this->assertSame(['Notdienst'], $entry->tags->pluck('name')->all());
        $this->assertSame(90, (int) $ts->fresh()->totals_minutes);
    }

    public function test_entry_dialog_offers_duration_mode_and_recent_texts(): void {
        $ts = $this->makeTimesheet();
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-02-10',
            'minutes' => 60,
            'kind' => TimeEntryKind::Work->value,
            'description' => 'Wartung Klimaanlage',
        ]);

        $this->actingAs($this->user)
            ->get(route('projects.timesheets.entries.create', [$this->project, $ts]))
            ->assertOk()
            ->assertSee('data-time-mode-toggle', false)
            ->assertSee('Wartung Klimaanlage');
    }

    public function test_second_create_for_same_day_opens_the_existing_sheet(): void {
        // Der Stoppuhr-Start legte den Zettel per firstOrCreate an, die
        // Sidebar-Anlage blind neu — für denselben Einsatz entstanden zwei
        // Zettel, und die Zeiten verteilten sich auf beide.
        $this->actingAs($this->user)
            ->post(route('projects.timesheets.store', $this->project), ['work_date' => '2030-04-01']);
        $this->actingAs($this->user)
            ->post(route('projects.timesheets.store', $this->project), ['work_date' => '2030-04-01']);

        $this->assertSame(1, Timesheet::query()
            ->where('project_id', $this->project->id)
            ->where('user_id', $this->user->id)
            ->count());
    }

    public function test_reopened_sheet_keeps_its_header_data(): void {
        // Kopfdaten des zweiten Anlaufs dürfen abgestimmte Angaben nicht
        // überschreiben — leere Felder darf er aber füllen.
        $this->actingAs($this->user)
            ->post(route('projects.timesheets.store', $this->project), [
                'work_date' => '2030-04-02',
                'customer_name' => 'Kunde GmbH',
            ]);

        $this->actingAs($this->user)
            ->post(route('projects.timesheets.store', $this->project), [
                'work_date' => '2030-04-02',
                'customer_name' => 'Falscher Kunde',
                'notes' => 'Nachtrag',
            ]);

        $ts = Timesheet::query()->where('work_date', '2030-04-02 00:00:00')->sole();
        $this->assertSame('Kunde GmbH', $ts->customer_name);
        $this->assertSame('Nachtrag', $ts->notes);
    }

    public function test_signed_sheet_does_not_block_a_second_visit_same_day(): void {
        // Nach der Kundenfreigabe muss ein zweiter Einsatz am selben Tag
        // erfassbar bleiben — der freigegebene Zettel ist tabu.
        $signed = $this->makeTimesheet([
            'work_date' => '2030-04-03',
            'status' => TimesheetStatus::Signed->value,
        ]);

        $this->actingAs($this->user)
            ->post(route('projects.timesheets.store', $this->project), ['work_date' => '2030-04-03']);

        $sheets = Timesheet::query()->where('work_date', '2030-04-03 00:00:00')->get();
        $this->assertCount(2, $sheets);
        $this->assertSame(TimesheetStatus::Signed, $signed->fresh()->status);
    }

    public function test_stopwatch_start_reuses_the_open_sheet_of_the_day(): void {
        $existing = $this->makeTimesheet(['work_date' => CarbonImmutable::today()->toDateString()]);

        $this->actingAs($this->user)
            ->post(route('stopwatch.start'), [
                'project_id' => $this->project->sqid,
                'description' => 'Anfahrt',
            ])
            ->assertRedirect();

        $this->assertSame(1, Timesheet::query()->where('project_id', $this->project->id)->count());
        $this->assertSame($existing->id, (int) TimeEntry::query()->latest('id')->firstOrFail()->timesheet_id);
    }

    public function test_existing_times_of_the_day_can_be_adopted(): void {
        // Zeiten aus Stoppuhr/Heute/Import hängen an keinem Zettel — sie sollen
        // sich anhängen lassen, statt in den Zettel abgetippt zu werden.
        $ts = $this->makeTimesheet();
        $loose = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => $ts->work_date,
            'minutes' => 45,
            'kind' => TimeEntryKind::Work->value,
            'description' => 'Schon erfasst',
        ]);
        // Fremder Tag: darf nicht mitgenommen werden.
        $otherDay = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-02-14',
            'minutes' => 30,
            'kind' => TimeEntryKind::Work->value,
        ]);

        $this->actingAs($this->user)
            ->post(route('projects.timesheets.entries.adopt', [$this->project, $ts]), [
                'entry_ids' => [$loose->sqid, $otherDay->sqid],
            ])
            ->assertRedirect();

        $this->assertSame($ts->id, (int) $loose->fresh()->timesheet_id);
        $this->assertNull($otherDay->fresh()->timesheet_id);
        $this->assertSame(45, (int) $ts->fresh()->totals_minutes);
    }

    public function test_adopt_dialog_lists_only_unassigned_times(): void {
        $ts = $this->makeTimesheet();
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'timesheet_id' => $ts->id,
            'date' => $ts->work_date,
            'minutes' => 60,
            'kind' => TimeEntryKind::Work->value,
            'description' => 'Hängt schon dran',
        ]);

        $this->actingAs($this->user)
            ->get(route('projects.timesheets.entries.adopt.form', [$this->project, $ts]))
            ->assertOk()
            ->assertDontSee('Hängt schon dran')
            ->assertSee(__('Keine offenen Zeiten'));
    }

    private function makeTimesheet(array $attrs = []): Timesheet {
        return Timesheet::create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'work_date' => '2030-02-15',
            'status' => TimesheetStatus::Draft->value,
        ], $attrs));
    }

    private function fakePngBase64(): string {
        // 1x1 transparent PNG
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }
}
