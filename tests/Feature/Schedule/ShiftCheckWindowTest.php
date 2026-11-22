<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftCheckWindowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Schedule;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\Absence\Vacation;
use App\Models\Platform\User;
use App\Models\Schedule\{ScheduledShift, ShiftType};
use App\Services\Compliance\{ComplianceViolation, ShiftCheckWindow, ShiftComplianceService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ShiftCheckWindowTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** Mit vorgeladenen Nachbarschichten muss jede Regel dasselbe melden wie mit Einzelabfragen. */
    public function test_window_reports_the_same_violations_with_far_fewer_queries(): void {
        $this->setUpOrganization();
        $org = $this->organization->id;
        $type = ShiftType::factory()->create(['organization_id' => $org, 'default_start_time' => '08:00', 'default_end_time' => '16:30']);
        $users = User::factory()->count(2)->create(['organization_id' => $org]);
        $shift = fn (User $user, string $date, ?string $from = null, ?string $to = null, ScheduledShiftStatus $status = ScheduledShiftStatus::Published): ScheduledShift => ScheduledShift::factory()->create([
            'organization_id' => $org, 'user_id' => $user->id, 'shift_type_id' => $type->id, 'date' => $date, 'start_time' => $from, 'end_time' => $to, 'status' => $status,
        ]);
        // Acht Tage in Folge, eine Überschneidung, eine zu kurze Ruhezeit, eine abgesagte Schicht, Urlaub.
        foreach (range(2, 9) as $day) {
            $shift($users[0], sprintf('2030-09-%02d', $day));
        }
        $shift($users[0], '2030-09-04', '14:00', '22:00');
        $shift($users[0], '2030-09-05', '00:30', '05:00');
        $shift($users[0], '2030-09-06', '06:00', '20:00', ScheduledShiftStatus::Cancelled);
        $shift($users[1], '2030-09-03');
        $shift($users[1], '2030-09-10');
        Vacation::create(['organization_id' => $org, 'user_id' => $users[1]->id, 'start_date' => '2030-09-10', 'end_date' => '2030-09-12', 'type' => VacationType::Vacation->value, 'status' => VacationStatus::Pending->value]);
        Vacation::create(['organization_id' => $org, 'user_id' => $users[0]->id, 'start_date' => '2030-09-02', 'end_date' => '2030-09-02', 'type' => VacationType::Vacation->value, 'status' => VacationStatus::Approved->value]);

        $shifts = ScheduledShift::query()->with('shiftType')->orderBy('id')->get();
        $service = app(ShiftComplianceService::class);
        $run = function () use ($shifts, $service): array {
            $found = [];
            foreach ($shifts as $one) {
                $found[$one->id] = array_map(static function (ComplianceViolation $v): array {
                    $related = $v->relatedShiftIds;
                    sort($related);

                    return [$v->code, $v->severity, $v->message, $related, $v->context];
                }, $service->check($one, $this->organization)->violations);
            }

            return $found;
        };

        DB::enableQueryLog();
        $single = $run();
        $singleQueries = count(DB::getQueryLog());
        DB::flushQueryLog();

        $window = app(ShiftCheckWindow::class);
        $window->open($shifts);
        $batched = $run();
        $window->close();
        $batchedQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($single, $batched);
        $codes = array_unique(array_merge(...array_map(static fn (array $rows): array => array_column($rows, 0), array_values($single))));
        foreach (['overlap', 'rest_period', 'consecutive_days', 'vacation_conflict'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertLessThan($singleQueries / 4, $batchedQueries);

        // Nach dem Schließen fragt die Prüfung wieder frisch: eine neue Überschneidung erscheint sofort.
        $shift($users[1], '2030-09-03', '10:00', '12:00');
        $first = $shifts->firstWhere('user_id', $users[1]->id);
        $this->assertContains('overlap', array_map(static fn (ComplianceViolation $v): string => $v->code, $service->check($first, $this->organization)->violations));
    }
}
