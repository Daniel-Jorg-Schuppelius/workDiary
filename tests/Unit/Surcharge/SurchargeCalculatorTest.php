<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurchargeCalculatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Surcharge;

use App\Models\Holiday;
use App\Models\Surcharge\SurchargeRule;
use App\Services\Surcharge\SurchargeCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 005 — reine Kalkulationslogik des SurchargeCalculators.
 *
 * Fixe Daten (Januar 2026): 01.01. Do (Neujahr), 03.01. Sa, 04.01. So,
 * 08./09.01. Do/Fr, 15.01. Do (im Test als Org-Feiertag angelegt).
 */
class SurchargeCalculatorTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_night_window_across_midnight_is_split_per_day(): void {
        $night = $this->makeRule(SurchargeRule::factory()->night('23:00:00', '06:00:00', '25.00'));

        // Do 08.01. 22:00 → Fr 09.01. 06:30 (kein Wochenende/Feiertag)
        $shares = $this->calc(
            '2026-01-08 22:00:00',
            '2026-01-09 06:30:00',
            [$night],
        );

        $this->assertCount(2, $shares);
        $this->assertSame('2026-01-08', $shares[0]->date);
        $this->assertSame(60, $shares[0]->minutes);   // 23:00–24:00
        $this->assertSame('2026-01-09', $shares[1]->date);
        $this->assertSame(360, $shares[1]->minutes);  // 00:00–06:00
        $this->assertSame($night->id, $shares[0]->rule->id);
    }

    public function test_saturday_rule_counts_only_saturday_minutes(): void {
        $saturday = $this->makeRule(SurchargeRule::factory()->saturday('20.00'));

        // Fr 02.01. 20:00 → Sa 03.01. 04:00
        $shares = $this->calc('2026-01-02 20:00:00', '2026-01-03 04:00:00', [$saturday]);

        $this->assertCount(1, $shares);
        $this->assertSame('2026-01-03', $shares[0]->date);
        $this->assertSame(240, $shares[0]->minutes);
    }

    public function test_sunday_rule_covers_whole_sunday_shift(): void {
        $sunday = $this->makeRule(SurchargeRule::factory()->sunday('50.00'));

        // So 04.01. 08:00–16:30
        $shares = $this->calc('2026-01-04 08:00:00', '2026-01-04 16:30:00', [$sunday]);

        $this->assertCount(1, $shares);
        $this->assertSame(510, $shares[0]->minutes);
    }

    public function test_holiday_rule_uses_holiday_service(): void {
        Holiday::query()->create([
            'organization_id' => $this->organization->id,
            'date' => '2026-01-15',
            'name' => 'Testfeiertag',
            'is_recurring' => false,
            'recurrence_type' => 'fixed',
        ]);

        $holiday = $this->makeRule(SurchargeRule::factory()->holiday('125.00'));

        // Do 15.01. 08:00–16:00 (Org-Feiertag) und Fr 16.01. (kein Feiertag)
        $sharesOnHoliday = $this->calc('2026-01-15 08:00:00', '2026-01-15 16:00:00', [$holiday]);
        $sharesOffHoliday = $this->calc('2026-01-16 08:00:00', '2026-01-16 16:00:00', [$holiday]);

        $this->assertCount(1, $sharesOnHoliday);
        $this->assertSame(480, $sharesOnHoliday[0]->minutes);
        $this->assertSame([], $sharesOffHoliday);
    }

    public function test_overlap_resolves_to_highest_percentage_not_additive(): void {
        $night = $this->makeRule(SurchargeRule::factory()->night('23:00:00', '06:00:00', '25.00'));
        $sunday = $this->makeRule(SurchargeRule::factory()->sunday('50.00'));

        // So 04.01. 00:00–08:00: 00:00–06:00 überlappt Nacht+Sonntag.
        $shares = $this->calc('2026-01-04 00:00:00', '2026-01-04 08:00:00', [$night, $sunday]);

        // Sonntag (50 %) gewinnt überall — Nacht erhält keine Minuten.
        $this->assertCount(1, $shares);
        $this->assertSame($sunday->id, $shares[0]->rule->id);
        $this->assertSame(480, $shares[0]->minutes);
    }

    public function test_overlap_higher_night_percentage_wins_inside_window(): void {
        $night = $this->makeRule(SurchargeRule::factory()->night('23:00:00', '06:00:00', '60.00'));
        $sunday = $this->makeRule(SurchargeRule::factory()->sunday('50.00'));

        // So 04.01. 00:00–08:00: Nacht (60 %) gewinnt 00:00–06:00, Sonntag den Rest.
        $shares = $this->calc('2026-01-04 00:00:00', '2026-01-04 08:00:00', [$night, $sunday]);

        $byRule = collect($shares)->keyBy(fn($s) => $s->rule->id);
        $this->assertSame(360, $byRule[$night->id]->minutes);
        $this->assertSame(120, $byRule[$sunday->id]->minutes);
    }

    public function test_equal_percentage_is_resolved_by_priority(): void {
        $night = $this->makeRule(SurchargeRule::factory()->night('00:00:00', '06:00:00', '50.00')->state(['priority' => 10]));
        $sunday = $this->makeRule(SurchargeRule::factory()->sunday('50.00')->state(['priority' => 0]));

        $shares = $this->calc('2026-01-04 00:00:00', '2026-01-04 06:00:00', [$night, $sunday]);

        $this->assertCount(1, $shares);
        $this->assertSame($night->id, $shares[0]->rule->id);
        $this->assertSame(360, $shares[0]->minutes);
    }

    public function test_validity_window_is_checked_per_calendar_day(): void {
        $sunday = $this->makeRule(SurchargeRule::factory()->sunday('50.00')->state([
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-01-03',
        ]));

        // So 04.01. liegt außerhalb der Gültigkeit.
        $this->assertSame([], $this->calc('2026-01-04 08:00:00', '2026-01-04 16:00:00', [$sunday]));

        $sundayLater = $this->makeRule(SurchargeRule::factory()->sunday('50.00')->state([
            'valid_from' => '2026-01-04',
            'valid_until' => null,
        ]));
        $shares = $this->calc('2026-01-04 08:00:00', '2026-01-04 16:00:00', [$sundayLater]);
        $this->assertSame(480, $shares[0]->minutes);
    }

    public function test_inactive_rule_yields_no_minutes(): void {
        $sunday = $this->makeRule(SurchargeRule::factory()->sunday('50.00')->inactive());

        $this->assertSame([], $this->calc('2026-01-04 08:00:00', '2026-01-04 16:00:00', [$sunday]));
    }

    public function test_custom_window_within_one_day(): void {
        $custom = $this->makeRule(SurchargeRule::factory()->custom('06:00:00', '08:00:00', '10.00'));

        $shares = $this->calc('2026-01-05 05:00:00', '2026-01-05 09:00:00', [$custom]);

        $this->assertCount(1, $shares);
        $this->assertSame(120, $shares[0]->minutes);
    }

    // ── Helfer ─────────────────────────────────────────────────────────

    /** @param  list<SurchargeRule>  $rules @return list<\App\Services\Surcharge\SurchargeShare> */
    private function calc(string $start, string $end, array $rules): array {
        /** @var SurchargeCalculator $calculator */
        $calculator = app(SurchargeCalculator::class);

        return $calculator->calculate(
            CarbonImmutable::parse($start),
            CarbonImmutable::parse($end),
            collect($rules),
        );
    }

    /** @param  \Illuminate\Database\Eloquent\Factories\Factory<SurchargeRule>  $factory */
    private function makeRule($factory): SurchargeRule {
        /** @var SurchargeRule $rule */
        $rule = $factory->create(['organization_id' => $this->organization->id]);

        return $rule;
    }
}
