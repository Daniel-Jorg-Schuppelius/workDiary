<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftRotationRollerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Schedule;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\{ScheduledShift, ShiftRotation, ShiftType, User, Vacation};
use App\Services\Schedule\ShiftRotationRoller;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rollplan-Fortschreibung (MVP-522): Wochen-Modulo, Idempotenz, Vorrang
 * manueller Planung und genehmigter Abwesenheiten.
 */
class ShiftRotationRollerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private ShiftType $early;

    private ShiftType $late;

    private ShiftRotation $rotation;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-15 08:00:00')); // Montag
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->user = $this->orgUser();

        $this->early = ShiftType::create([
            'organization_id' => $this->organization->id,
            'name' => 'Frühdienst',
            'abbreviation' => 'F1',
            'default_start_time' => '06:00',
            'default_end_time' => '14:00',
            'is_active' => true,
        ]);
        $this->late = ShiftType::create([
            'organization_id' => $this->organization->id,
            'name' => 'Spätdienst',
            'abbreviation' => 'S1',
            'default_start_time' => '14:00',
            'default_end_time' => '22:00',
            'is_active' => true,
        ]);

        // 2-Wochen-Rhythmus: Woche 1 Mo–Fr Früh, Woche 2 Mo–Fr Spät.
        $this->rotation = ShiftRotation::create([
            'organization_id' => $this->organization->id,
            'name' => 'Früh/Spät-Wechsel',
            'weeks_count' => 2,
            'is_active' => true,
        ]);
        for ($dow = 1; $dow <= 5; $dow++) {
            $this->rotation->entries()->create(['week_index' => 0, 'iso_weekday' => $dow, 'shift_type_id' => $this->early->id]);
            $this->rotation->entries()->create(['week_index' => 1, 'iso_weekday' => $dow, 'shift_type_id' => $this->late->id]);
        }
        $this->rotation->assignments()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'anchor_date' => '2026-06-15', // Montag = Woche 0
        ]);
    }

    private function roll(int $weeks = 2): array {
        return app(ShiftRotationRoller::class)->rollForward(
            $this->organization,
            CarbonImmutable::parse('2026-06-15'),
            $weeks,
        );
    }

    public function test_generates_alternating_weeks(): void {
        $stats = $this->roll(2);

        $this->assertSame(10, $stats['created']);
        $mondayWeek1 = ScheduledShift::query()->whereDate('date', '2026-06-15')->firstOrFail();
        $mondayWeek2 = ScheduledShift::query()->whereDate('date', '2026-06-22')->firstOrFail();
        $this->assertSame($this->early->id, $mondayWeek1->shift_type_id);
        $this->assertSame($this->late->id, $mondayWeek2->shift_type_id);
        $this->assertSame(ScheduledShiftStatus::Draft, $mondayWeek1->status);
        $this->assertSame('06:00', substr((string) $mondayWeek1->start_time, 0, 5));
        // Wochenende bleibt frei.
        $this->assertSame(0, ScheduledShift::query()->whereDate('date', '2026-06-20')->count());
    }

    public function test_roll_is_idempotent(): void {
        $this->roll(2);
        $stats = $this->roll(2);

        $this->assertSame(0, $stats['created']);
        $this->assertSame(10, ScheduledShift::query()->count());
    }

    public function test_manual_shift_wins(): void {
        ScheduledShift::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'shift_type_id' => $this->late->id,
            'date' => '2026-06-15',
            'start_time' => '10:00',
            'end_time' => '18:00',
            'status' => ScheduledShiftStatus::Published->value,
        ]);

        $this->roll(1);

        $monday = ScheduledShift::query()->whereDate('date', '2026-06-15')->get();
        $this->assertCount(1, $monday);
        $this->assertSame($this->late->id, $monday->first()->shift_type_id);
    }

    public function test_approved_absence_blocks_generation(): void {
        Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-19',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Approved->value,
        ]);

        $stats = $this->roll(1);

        $this->assertSame(0, $stats['created']);
        $this->assertSame(5, $stats['skipped']);
    }

    public function test_command_runs_for_all_organizations(): void {
        $this->artisan('shifts:roll-forward', ['--weeks' => 1])->assertExitCode(0);

        $this->assertSame(5, ScheduledShift::query()->count());
    }
}
