<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationMinimaTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Schedule;

use App\Models\{CoverageRequirement, DutyPlan, Qualification, ScheduledShift, ShiftType};
use App\Services\CoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-530: Qualifikations-Mindestbesetzung — „mindestens 2 Examinierte in
 * der Frühschicht" (zählbares Minimum neben der Alle-müssen-Regel).
 */
class QualificationMinimaTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * @return array{plan: DutyPlan, fruh: ShiftType, qual: Qualification}
     */
    private function planContext(): array {
        $plan = DutyPlan::factory()->draft()->weekly()->create([
            'organization_id' => $this->organization->id,
            'from_date' => '2026-05-18', // Montag
            'to_date' => '2026-05-24',
            'min_staff' => 0,
        ]);
        $fruh = ShiftType::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Frühdienst',
            'abbreviation' => 'F',
            'color' => '#3b82f6',
        ]);
        $qual = Qualification::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Examiniert',
        ]);

        return ['plan' => $plan, 'fruh' => $fruh, 'qual' => $qual];
    }

    public function test_store_accepts_minima_with_sqid_keys(): void {
        $ctx = $this->planContext();

        // Formularweg: Sqids für Schichttyp, Checkboxen UND Minima-Keys.
        $res = $this->actingAs($this->orgAdmin())
            ->post(route('duty-plans.coverage.store', $ctx['plan']), [
                'shift_type_id' => $ctx['fruh']->sqid,
                'weekday' => 1,
                'min_staff' => 5,
                'required_qualification_ids' => [$ctx['qual']->sqid],
                'qualification_minima' => [$ctx['qual']->sqid => '2'],
            ]);

        $res->assertRedirect(route('duty-plans.coverage.index', $ctx['plan']));

        $req = CoverageRequirement::query()->firstOrFail();
        $this->assertSame([$ctx['qual']->id], array_map('intval', (array) $req->required_qualification_ids));
        $this->assertSame([$ctx['qual']->id => 2], $req->qualificationMinima());
    }

    public function test_qualified_staffing_counts_only_valid_holders(): void {
        $ctx = $this->planContext();

        CoverageRequirement::factory()->forWeekday(1)->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'shift_type_id' => $ctx['fruh']->id,
            'min_staff' => 3,
            'qualification_minima' => [$ctx['qual']->id => 2],
        ]);

        $holder = $this->orgUser();
        $expired = $this->orgUser();
        $unskilled = $this->orgUser();
        $holder->qualifications()->attach($ctx['qual']->id);
        $expired->qualifications()->attach($ctx['qual']->id, ['valid_until' => '2026-01-31']);

        foreach ([$holder, $expired, $unskilled] as $user) {
            ScheduledShift::factory()->published()->create([
                'organization_id' => $this->organization->id,
                'duty_plan_id' => $ctx['plan']->id,
                'user_id' => $user->id,
                'shift_type_id' => $ctx['fruh']->id,
                'date' => '2026-05-18', // Montag
            ]);
        }

        $svc = app(CoverageService::class);

        $actual = $svc->actualQualifiedStaffing($ctx['plan'], [$ctx['qual']->id]);
        $this->assertSame(1, $actual['2026-05-18'][$ctx['fruh']->id][$ctx['qual']->id]);

        $gaps = $svc->qualificationGaps($ctx['plan']);
        $this->assertCount(1, $gaps);
        $this->assertSame('2026-05-18', $gaps[0]['date']);
        $this->assertSame(2, $gaps[0]['required']);
        $this->assertSame(1, $gaps[0]['actual']);
    }

    public function test_heatmap_flags_missed_minima_despite_enough_heads(): void {
        $ctx = $this->planContext();

        CoverageRequirement::factory()->forWeekday(1)->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'shift_type_id' => $ctx['fruh']->id,
            'min_staff' => 2,
            'qualification_minima' => [$ctx['qual']->id => 1],
        ]);

        // Kopfzahl erfüllt (2), aber niemand ist examiniert.
        foreach ([$this->orgUser(), $this->orgUser()] as $user) {
            ScheduledShift::factory()->published()->create([
                'organization_id' => $this->organization->id,
                'duty_plan_id' => $ctx['plan']->id,
                'user_id' => $user->id,
                'shift_type_id' => $ctx['fruh']->id,
                'date' => '2026-05-18',
            ]);
        }

        $this->actingAs($this->orgAdmin())
            ->get(route('duty-plans.show', $ctx['plan']))
            ->assertOk()
            ->assertSee(__('Qualifikations-Mindestbesetzung verfehlt'))
            ->assertSee('Examiniert');
    }
}
