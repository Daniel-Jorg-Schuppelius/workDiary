<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Schedule;

use App\Enums\Diary\{DispatchStatus, Mode, Status};
use App\Enums\Shift\{ScheduledShiftStatus, ShiftExchangeStatus};
use App\Models\Diary\DiaryEntry;
use App\Models\Hr\Qualification;
use App\Models\Platform\User;
use App\Models\Schedule\{ScheduledShift, ShiftExchange, ShiftType};
use App\Services\Dispatch\{DispatchConflictChecker, DispatchStatusResolver};
use App\Services\Schedule\{ShiftExchangeException, ShiftExchangeService};
use App\Settings\{SettingScope, SettingsRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Qualifikation: Sperre statt Hinweis (Feature 013, MVP-1514). Vorgabe bleibt
 * der Hinweis; mit `qualifications.enforcement = block` lehnen Schicht-,
 * Tausch- und Auftragswege die Zuweisung ab.
 */
class QualificationEnforcementTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private ShiftType $type;

    private Qualification $qualification;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->type = ShiftType::factory()->create(['organization_id' => $this->organization->id]);
        $this->qualification = Qualification::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Höhenarbeit',
            'abbreviation' => 'HA',
        ]);
        $this->type->qualifications()->attach($this->qualification->id);
    }

    private function block(): void {
        $this->organization->settings = ['qualifications' => ['enforcement' => 'block']];
        $this->organization->save();
    }

    /** @return array<string, mixed> */
    private function shiftPayload(User $user): array {
        return [
            'user_id' => $user->sqid,
            'shift_type_id' => $this->type->sqid,
            'date' => '2030-05-04',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'status' => ScheduledShiftStatus::Draft->value,
        ];
    }

    public function test_hint_mode_keeps_assigning_unqualified_staff(): void {
        $this->actingAs($this->orgAdmin())
            ->postJson(route('schedule.shifts.store'), $this->shiftPayload($this->orgUser()))
            ->assertCreated();
    }

    public function test_block_mode_rejects_shift_for_missing_or_expired_qualification(): void {
        $this->block();
        $admin = $this->orgAdmin();
        $unqualified = $this->orgUser(['name' => 'Erika Beispiel']);

        $response = $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->shiftPayload($unqualified) + ['override_compliance' => true])
            ->assertUnprocessable();
        $this->assertStringContainsString('Zuweisung gesperrt: Erika Beispiel fehlt am 04.05.2030 die Pflichtqualifikation HA', (string) $response->json('errors.user_id.0'));

        $expired = $this->orgUser();
        $expired->qualifications()->attach($this->qualification->id, ['valid_until' => '2030-05-01']);
        $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->shiftPayload($expired))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $valid = $this->orgUser();
        $valid->qualifications()->attach($this->qualification->id, ['valid_until' => '2030-12-31']);
        $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->shiftPayload($valid))
            ->assertCreated();
    }

    public function test_block_mode_leaves_unchanged_existing_assignments_editable(): void {
        $user = $this->orgUser();
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'shift_type_id' => $this->type->id,
            'date' => '2030-05-04',
        ]);
        $this->block();

        $this->actingAs($this->orgAdmin())
            ->putJson(route('schedule.shifts.update', $shift), ['note' => 'Material mitnehmen'])
            ->assertOk();
    }

    public function test_block_mode_rejects_shift_swap_to_unqualified_colleague(): void {
        $this->block();
        $qualified = $this->orgUser();
        $qualified->qualifications()->attach($this->qualification->id);
        $colleague = $this->orgUser();
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $qualified->id,
            'shift_type_id' => $this->type->id,
            'date' => '2030-05-04',
        ]);
        $exchange = ShiftExchange::query()->create([
            'organization_id' => $this->organization->id,
            'scheduled_shift_id' => $shift->id,
            'requested_by_user_id' => $qualified->id,
            'target_user_id' => $colleague->id,
            'status' => ShiftExchangeStatus::Accepted,
        ]);

        $this->expectException(ShiftExchangeException::class);
        $this->expectExceptionMessage('fehlt am 04.05.2030 die Pflichtqualifikation HA');

        try {
            app(ShiftExchangeService::class)->approve($exchange, $this->orgAdmin(), overrideCompliance: true);
        } finally {
            $this->assertSame($qualified->id, (int) $shift->refresh()->user_id);
        }
    }

    public function test_block_mode_rejects_order_assignment_and_confirmation_needs_a_reason(): void {
        $admin = $this->orgAdmin();
        $unqualified = $this->orgUser(['name' => 'Max Muster']);
        $entry = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $admin->id,
            'mode' => Mode::Fixed->value,
            'status' => Status::Open->value,
            'content' => 'Dachrinne reinigen',
            'start_at' => Carbon::parse('2030-05-04 09:00'),
            'end_at' => Carbon::parse('2030-05-04 11:00'),
        ]);
        $entry->requiredQualifications()->attach($this->qualification->id);
        $this->block();

        $this->actingAs($admin)
            ->put(route('diary.update', $entry), [
                'content' => 'Dachrinne reinigen',
                'status' => Status::Open->value,
                'start_at' => '2030-05-04 09:00:00',
                'end_at' => '2030-05-04 11:00:00',
                'assigned_user_id' => $unqualified->sqid,
            ])
            ->assertSessionHasErrors(['assigned_user_id' => 'Zuweisung gesperrt: Max Muster fehlt am 04.05.2030 die Pflichtqualifikation HA (nicht vorhanden, abgelaufen oder noch nicht gültig).']);
        $this->assertNull($entry->refresh()->assigned_user_id);

        // Bestandszuweisung aus der Zeit vor der Sperre: Bestätigung nur mit Begründung.
        $entry->forceFill(['assigned_user_id' => $unqualified->id])->save();
        $report = app(DispatchConflictChecker::class)->check($entry->refresh());
        $this->assertContains('qualification_missing', array_map(fn ($v) => $v->code, $report->violations));
        $this->assertTrue($report->hasErrors());

        app(DispatchStatusResolver::class)->transition($entry, DispatchStatus::Planned);
        $this->actingAs($admin)
            ->post(route('dispatch.transition', $entry), ['dispatch_status' => DispatchStatus::Confirmed->value])
            ->assertSessionHasErrors('dispatch');
        $this->actingAs($admin)
            ->post(route('dispatch.transition', $entry), ['dispatch_status' => DispatchStatus::Confirmed->value, 'override_reason' => 'Begleitung durch qualifizierte Kollegin vor Ort'])
            ->assertSessionHasNoErrors();
        $this->assertSame(DispatchStatus::Confirmed, app(DispatchStatusResolver::class)->resolve($entry->refresh()));
    }

    public function test_hint_mode_assigns_orders_without_conflict(): void {
        $admin = $this->orgAdmin();
        $entry = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $admin->id,
            'content' => 'Dachrinne reinigen',
            'status' => Status::Open->value,
            'start_at' => Carbon::parse('2030-05-04 09:00'),
        ]);
        $entry->requiredQualifications()->attach($this->qualification->id);
        $unqualified = $this->orgUser();

        $this->actingAs($admin)
            ->put(route('diary.update', $entry), ['content' => 'Dachrinne reinigen', 'status' => Status::Open->value, 'start_at' => '2030-05-04 09:00:00', 'end_at' => '2030-05-04 11:00:00', 'assigned_user_id' => $unqualified->sqid])
            ->assertSessionHasNoErrors();
        $this->assertSame($unqualified->id, (int) $entry->refresh()->assigned_user_id);
        $this->assertNotContains('qualification_missing', array_map(fn ($v) => $v->code, app(DispatchConflictChecker::class)->check($entry)->violations));
    }

    private function assignedOrder(User $assignee): DiaryEntry {
        $entry = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->orgAdmin()->id,
            'content' => 'Fassade streichen',
            'status' => Status::Open->value,
            'start_at' => Carbon::parse('2030-05-04 09:00'),
        ]);
        $entry->forceFill(['assigned_user_id' => $assignee->id])->save();

        return $entry->refresh();
    }

    /** MVP-1528: Eine nachträglich gesetzte Pflichtqualifikation prüft die schon zuständige Person. */
    public function test_block_mode_rejects_a_new_requirement_the_assignee_lacks(): void {
        $admin = $this->orgAdmin();
        $assignee = $this->orgUser(['name' => 'Max Muster']);
        $entry = $this->assignedOrder($assignee);
        $this->block();

        $this->actingAs($admin)->from(route('diary.show', $entry))
            ->put(route('diary.qualifications.update', $entry), ['qualifications' => [$this->qualification->sqid]])
            ->assertSessionHasErrors(['qualifications' => 'Zuweisung gesperrt: Max Muster fehlt am 04.05.2030 die Pflichtqualifikation HA (nicht vorhanden, abgelaufen oder noch nicht gültig).']);
        $this->assertSame(0, $entry->requiredQualifications()->count(), 'die Sperre rollt die Änderung zurück');

        $assignee->qualifications()->attach($this->qualification->id, ['valid_until' => '2030-12-31']);
        $this->actingAs($admin)->from(route('diary.show', $entry))
            ->put(route('diary.qualifications.update', $entry), ['qualifications' => [$this->qualification->sqid]])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('warning');
        $this->assertSame([$this->qualification->id], $entry->requiredQualifications()->pluck('qualifications.id')->map(fn ($id): int => (int) $id)->all());
    }

    public function test_hint_mode_saves_a_new_requirement_and_names_the_gap(): void {
        $admin = $this->orgAdmin();
        $entry = $this->assignedOrder($this->orgUser(['name' => 'Max Muster']));

        $this->actingAs($admin)->from(route('diary.show', $entry))
            ->put(route('diary.qualifications.update', $entry), ['qualifications' => [$this->qualification->sqid]])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning', 'Hinweis: Max Muster fehlt am 04.05.2030 die Pflichtqualifikation HA (nicht vorhanden, abgelaufen oder noch nicht gültig).');
        $this->assertSame(1, $entry->requiredQualifications()->count());

        // Ohne zuständige Person gibt es nichts zu prüfen.
        $entry->forceFill(['assigned_user_id' => null])->save();
        $this->actingAs($admin)->from(route('diary.show', $entry))
            ->put(route('diary.qualifications.update', $entry), ['qualifications' => [$this->qualification->sqid]])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('warning');
    }

    public function test_block_mode_fails_the_shift_import_row(): void {
        $this->block();
        $this->orgUser(['email' => 'max.muster@example.org']);
        $spec = app(\App\Services\Schedule\Import\ScheduledShiftSpec::class);

        [$outcome, $issue] = $spec->upsert($spec->normalize([
            'user_email' => 'max.muster@example.org',
            'shift_type' => $this->type->name,
            'date' => '2030-05-04',
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]), $this->organization);

        $this->assertSame(\App\Services\Import\ImportOutcome::Failed, $outcome);
        $this->assertSame(\App\Enums\Import\ImportErrorCode::Blocked, $issue?->code);
        $this->assertSame(0, ScheduledShift::query()->count());
    }

    public function test_setting_is_registered_for_the_organization_form(): void {
        $rules = app(SettingsRegistry::class)->formRulesForScope(SettingScope::Organization);

        $this->assertArrayHasKey('settings.qualifications.enforcement', $rules);
    }
}
