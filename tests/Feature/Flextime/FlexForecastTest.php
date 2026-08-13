<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexForecastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Flextime;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\{FlexBalance, ScheduledShift, User, WorkSchedule};
use App\Services\Flextime\{FlexForecastService, FlexTrafficLight};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Ampelphasen + Vorausberechnung des Gleitzeitsaldos (MVP-521).
 */
class FlexForecastTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->user = $this->orgUser();

        WorkSchedule::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'weekly_minutes' => 2400,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2020-01-01',
        ]);
    }

    public function test_traffic_light_uses_org_thresholds(): void {
        $this->organization->update([
            'settings' => ['flex' => ['warn_minutes' => 600, 'critical_minutes' => 1200]],
        ]);
        app()->instance('currentOrganization', $this->organization->fresh());

        $light = FlexTrafficLight::current();
        $this->assertSame('success', $light->tone(599));
        $this->assertSame('warning', $light->tone(600));
        $this->assertSame('warning', $light->tone(-700));
        $this->assertSame('error', $light->tone(1300));
        $this->assertSame('error', $light->tone(-1300));
    }

    public function test_forecast_assumes_target_adherence_without_shifts(): void {
        FlexBalance::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'year' => 2026,
            'month' => 5,
            'target_minutes' => 9600,
            'actual_minutes' => 9720,
            'balance_minutes' => 120,
            'carry_over_minutes' => 0,
        ]);

        $forecast = app(FlexForecastService::class)->forecast($this->user, 3);

        $this->assertSame(120, $forecast['start_balance']);
        $this->assertCount(3, $forecast['months']);
        foreach ($forecast['months'] as $month) {
            $this->assertFalse($month['has_shifts']);
            $this->assertSame(0, $month['delta']);
            $this->assertSame(120, $month['projected']);
        }
    }

    public function test_forecast_projects_planned_shift_delta(): void {
        // Juli 2026: jeden Werktag ein 10-h-Dienst geplant (08:00–18:00) —
        // Soll 8 h/Tag ⇒ Δ = +2 h je Arbeitstag.
        $cursor = Carbon::parse('2026-07-01');
        $workdays = 0;
        while ($cursor->month === 7) {
            if ($cursor->isWeekday()) {
                ScheduledShift::create([
                    'organization_id' => $this->organization->id,
                    'user_id' => $this->user->id,
                    'date' => $cursor->toDateString(),
                    'start_time' => '08:00',
                    'end_time' => '18:00',
                    'status' => ScheduledShiftStatus::Published->value,
                ]);
                $workdays++;
            }
            $cursor->addDay();
        }

        $forecast = app(FlexForecastService::class)->forecast($this->user, 1);
        $july = $forecast['months'][0];

        $this->assertSame('2026-07', $july['key']);
        $this->assertTrue($july['has_shifts']);
        $this->assertSame($workdays * 600, $july['planned']);
        // Soll enthält ggf. Feiertagsabzüge — das Delta muss der Differenz entsprechen.
        $this->assertSame($july['planned'] - $july['target'], $july['delta']);
        $this->assertSame($july['delta'], $july['projected']);
    }
}
