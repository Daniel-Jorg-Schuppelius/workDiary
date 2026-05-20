<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\CoverageRequirement;
use App\Models\DutyPlan;
use App\Models\Holiday;
use App\Models\Qualification;
use App\Models\ScheduledShift;
use App\Models\ShiftType;
use App\Models\User;
use App\Models\Vacation;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use App\Enums\Vacation\VacationStatus;
use App\Enums\Shift\ScheduledShiftStatus;

class ComplianceTest extends TestCase
{
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function user(): User
    {
        return User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    /** Setze den Compliance-Mode auf der Organisation. */
    private function setMode(string $mode, array $extra = []): void
    {
        $this->organization->settings = [
            'compliance' => array_replace(['mode' => $mode], $extra),
        ];
        $this->organization->save();
    }

    private function payload(User $u, array $overrides = []): array
    {
        return array_replace([
            'user_id' => $u->id,
            'date' => '2026-06-01',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'status' => ScheduledShiftStatus::Draft->value,
        ], $overrides);
    }

    // ─── Mode behavior ───────────────────────────────────────────────────────

    public function test_mode_off_skips_all_checks(): void
    {
        $this->setMode('off');
        $admin = $this->admin();
        $u = $this->user();
        ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'date' => '2026-06-01',
            'start_time' => '07:00',
            'end_time' => '13:00',
        ]);

        $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->payload($u))
            ->assertCreated()
            ->assertJsonMissingPath('compliance_warnings');
    }

    public function test_mode_warn_returns_warnings_but_creates(): void
    {
        $this->setMode('warn');
        $admin = $this->admin();
        $u = $this->user();
        ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'date' => '2026-06-01',
            'start_time' => '07:00',
            'end_time' => '13:00',
        ]);

        $res = $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->payload($u))
            ->assertCreated();

        $this->assertNotEmpty($res->json('compliance_warnings'));
    }

    public function test_mode_block_rejects_violations(): void
    {
        $this->setMode('block');
        $admin = $this->admin();
        $u = $this->user();
        ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'date' => '2026-06-01',
            'start_time' => '07:00',
            'end_time' => '13:00',
        ]);

        $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->payload($u))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['compliance']);
    }

    public function test_block_can_be_overridden(): void
    {
        $this->setMode('block');
        $admin = $this->admin();
        $u = $this->user();
        ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'date' => '2026-06-01',
            'start_time' => '07:00',
            'end_time' => '13:00',
        ]);

        $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->payload($u, ['override_compliance' => 1]))
            ->assertCreated();
    }

    // ─── Individual rules (mode=block to surface as 422) ─────────────────────

    public function test_overlap_rule_detects_conflict(): void
    {
        $this->setMode('block');
        $admin = $this->admin();
        $u = $this->user();
        ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'date' => '2026-06-01',
            'start_time' => '10:00',
            'end_time' => '14:00',
        ]);

        $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->payload($u, [
                'start_time' => '12:00',
                'end_time' => '16:00',
            ]))
            ->assertStatus(422);
    }

    public function test_overlap_rule_passes_for_disjoint_shifts(): void
    {
        $this->setMode('block');
        $admin = $this->admin();
        $u = $this->user();
        ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'date' => '2026-06-01',
            'start_time' => '06:00',
            'end_time' => '07:00',
        ]);

        $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->payload($u, [
                'start_time' => '20:00',
                'end_time' => '22:00',
            ]))
            ->assertCreated();
    }

    public function test_rest_period_rule_detects_short_break(): void
    {
        $this->setMode('block');
        $admin = $this->admin();
        $u = $this->user();
        // Vortag 18:00-23:00 → 9h Pause bis 08:00 nächster Tag.
        ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'date' => '2026-05-31',
            'start_time' => '18:00',
            'end_time' => '23:00',
        ]);

        $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->payload($u))
            ->assertStatus(422);
    }

    public function test_max_daily_hours_rule_detects_overload(): void
    {
        $this->setMode('block', ['max_hours_day' => 8]);
        $admin = $this->admin();
        $u = $this->user();
        ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'date' => '2026-06-01',
            'start_time' => '06:00',
            'end_time' => '12:00',
        ]);

        // +6h zu vorhandenen 6h = 12h → > 8h Limit
        $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->payload($u, [
                'start_time' => '13:00',
                'end_time' => '19:00',
            ]))
            ->assertStatus(422);
    }

    public function test_max_weekly_hours_rule_only_warns(): void
    {
        // Wochenstunden ist severity=warning → block triggert nur bei Errors.
        // Daher muss der Endpoint mit 201 antworten und compliance_warnings setzen.
        $this->setMode('block', ['max_hours_week' => 4]);
        $admin = $this->admin();
        $u = $this->user();

        $res = $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->payload($u, [
                'start_time' => '08:00',
                'end_time' => '14:00',
            ]))
            ->assertCreated();

        $codes = collect($res->json('compliance_warnings'))->pluck('code')->all();
        $this->assertContains('max_weekly_hours', $codes);
    }

    public function test_consecutive_days_rule_warns(): void
    {
        $this->setMode('warn', ['max_consecutive_days' => 2]);
        $admin = $this->admin();
        $u = $this->user();
        // Drei Tage davor je eine Schicht
        foreach (['2026-05-29', '2026-05-30', '2026-05-31'] as $d) {
            ScheduledShift::factory()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $u->id,
                'date' => $d,
                'start_time' => '09:00',
                'end_time' => '11:00',
            ]);
        }

        $res = $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->payload($u))
            ->assertCreated();

        $codes = collect($res->json('compliance_warnings'))->pluck('code')->all();
        $this->assertContains('consecutive_days', $codes);
    }

    public function test_vacation_conflict_rule_blocks_approved_vacation(): void
    {
        $this->setMode('block');
        $admin = $this->admin();
        $u = $this->user();
        Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'start_date' => '2026-05-30',
            'end_date' => '2026-06-05',
            'status' => VacationStatus::Approved->value,
        ]);

        $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->payload($u))
            ->assertStatus(422);
    }

    public function test_qualification_match_rule_warns_when_user_lacks_qualification(): void
    {
        $this->setMode('warn');
        $admin = $this->admin();
        $u = $this->user();

        $type = ShiftType::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $qual = Qualification::create([
            'organization_id' => $this->organization->id,
            'name' => 'Brandschutz',
            'created_by' => $admin->id,
        ]);

        $plan = DutyPlan::factory()->draft()->weekly()->create([
            'organization_id' => $this->organization->id,
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-07',
            'min_staff' => 0,
        ]);

        CoverageRequirement::create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $plan->id,
            'shift_type_id' => $type->id,
            'weekday' => null,
            'specific_date' => '2026-06-01',
            'min_staff' => 1,
            'max_staff' => null,
            'required_qualification_ids' => [$qual->id],
            'created_by' => $admin->id,
        ]);

        $res = $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->payload($u, [
                'shift_type_id' => $type->id,
                'duty_plan_id' => $plan->id,
            ]))
            ->assertCreated();

        $codes = collect($res->json('compliance_warnings'))->pluck('code')->all();
        $this->assertContains('qualification_match', $codes);
    }

    public function test_holiday_double_book_rule_warns(): void
    {
        $this->setMode('warn');
        $admin = $this->admin();
        $u = $this->user();
        Holiday::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test-Feiertag',
            'date' => '2026-06-01',
            'recurrence_type' => 'fixed',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $res = $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), $this->payload($u))
            ->assertCreated();

        $codes = collect($res->json('compliance_warnings'))->pluck('code')->all();
        $this->assertContains('holiday_double_book', $codes);
    }
}
