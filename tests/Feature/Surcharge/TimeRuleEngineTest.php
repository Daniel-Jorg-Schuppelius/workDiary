<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeRuleEngineTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Surcharge;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Enums\Surcharge\SurchargeKind;
use App\Models\{Attendance, AttendanceTerminal, AuditLog, Customer, Site, Team, User};
use App\Models\Surcharge\{SurchargeRule, TimeRuleResult};
use App\Services\Surcharge\TimeRuleEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-513 (Feature 103): Zeitregel-Engine — persistierte Ergebnisse mit
 * Snapshot, Regel-Bedingungen (Team/Standort), Feiertags-Region des
 * Einsatz-Standorts und auditierte Neuberechnung.
 */
class TimeRuleEngineTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->user = $this->orgUser();
    }

    private function engine(): TimeRuleEngine {
        return app(TimeRuleEngine::class);
    }

    /** @param array<string, mixed> $overrides */
    private function attendance(string $start, string $end, array $overrides = []): Attendance {
        return Attendance::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => substr($start, 0, 10),
            'started_at' => $start,
            'ended_at' => $end,
            'status' => AttendanceStatus::Closed->value,
            'source' => AttendanceSource::Manual->value,
            'break_minutes_auto' => 0,
            'break_minutes_manual' => 0,
        ], $overrides));
    }

    /** @param array<string, mixed> $attributes */
    private function nightRule(array $attributes = []): SurchargeRule {
        return SurchargeRule::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'code' => 'night',
        ], $attributes));
    }

    private function evaluateJanuary(): array {
        return $this->engine()->evaluateUserPeriod(
            (int) $this->organization->id,
            (int) $this->user->id,
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-31 23:59:59'),
            SurchargeRule::query()->where('active', true)->get(),
        );
    }

    public function test_results_are_persisted_with_rule_snapshot(): void {
        $rule = $this->nightRule();
        // Do 08.01. 22:00 → Fr 09.01. 06:00: Nacht = 60 min (08.) + 360 min (09.).
        $this->attendance('2026-01-08 22:00:00', '2026-01-09 06:00:00');

        $acc = $this->evaluateJanuary();

        $this->assertCount(2, $acc);
        $results = TimeRuleResult::query()->orderBy('date')->get();
        $this->assertCount(2, $results);
        $this->assertSame(60, $results[0]->minutes);
        $this->assertSame(360, $results[1]->minutes);
        $this->assertSame('2010', $results[0]->wage_type_code);
        $this->assertSame($rule->id, $results[0]->surcharge_rule_id);
        $this->assertSame('night', $results[0]->calculation_snapshot['rule']['code']);
        $this->assertSame('25.00', $results[0]->calculation_snapshot['rule']['percentage']);

        // Idempotenz: erneute Bewertung ersetzt statt dupliziert.
        $this->evaluateJanuary();
        $this->assertSame(2, TimeRuleResult::query()->count());
    }

    public function test_team_condition_restricts_rule(): void {
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $this->nightRule(['conditions' => ['team_ids' => [$team->id]]]);
        $this->attendance('2026-01-08 23:00:00', '2026-01-08 23:59:00');

        // Ohne Teamzugehörigkeit greift die Regel nicht.
        $this->assertSame([], $this->evaluateJanuary());
        $this->assertSame(0, TimeRuleResult::query()->count());

        $this->user->teams()->attach($team->id);
        $acc = $this->evaluateJanuary();

        $this->assertCount(1, $acc);
        $this->assertSame(59, array_values($acc)[0]['minutes']);
    }

    public function test_site_condition_uses_terminal_context(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $site = Site::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        AttendanceTerminal::issue((int) $this->organization->id, 'Werkstor', (int) $site->id);
        $this->nightRule(['conditions' => ['site_ids' => [$site->id]]]);

        // Browser-Stempel: kein Standortkontext → bedingte Regel greift nicht.
        $this->attendance('2026-01-07 23:00:00', '2026-01-07 23:30:00');
        // Terminal-Stempel am Standort → Regel greift.
        $this->attendance('2026-01-08 23:00:00', '2026-01-08 23:30:00', [
            'source' => AttendanceSource::Terminal->value,
            'started_device' => 'Werkstor',
        ]);

        $acc = $this->evaluateJanuary();

        $this->assertCount(1, $acc);
        $row = array_values($acc)[0];
        $this->assertSame('2026-01-08', $row['date']);
        $this->assertSame(30, $row['minutes']);
        $this->assertSame($site->id, TimeRuleResult::query()->first()->calculation_snapshot['context']['site_id']);
    }

    public function test_site_holiday_region_controls_holiday_rule(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $site = Site::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'holiday_provider' => 'Germany\\Bavaria',
        ]);
        AttendanceTerminal::issue((int) $this->organization->id, 'Werk München', (int) $site->id);
        SurchargeRule::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'holiday',
            'kind' => SurchargeKind::Holiday->value,
            'window_start' => null,
            'window_end' => null,
            'percentage' => '125.00',
            'wage_type_code' => '2030',
        ]);

        // 06.01. (Heilige Drei Könige): Feiertag in Bayern, nicht bundesweit.
        $this->attendance('2026-01-06 10:00:00', '2026-01-06 12:00:00'); // Browser → Org-Default (Germany)
        $this->attendance('2026-01-06 13:00:00', '2026-01-06 15:00:00', [
            'source' => AttendanceSource::Terminal->value,
            'started_device' => 'Werk München',
        ]);

        $acc = $this->evaluateJanuary();

        $this->assertCount(1, $acc);
        $row = array_values($acc)[0];
        $this->assertSame(120, $row['minutes']); // nur der Terminal-Stempel am bayerischen Standort
        $this->assertSame('Germany\\Bavaria', TimeRuleResult::query()->first()->calculation_snapshot['holiday_provider']);
    }

    public function test_recalculate_command_replaces_results_and_audits(): void {
        $this->nightRule();
        $this->attendance('2026-01-08 22:00:00', '2026-01-09 06:00:00');

        $this->artisan('rules:recalculate', ['--org' => $this->organization->id, '--year' => 2026, '--month' => 1])
            ->assertExitCode(0);

        $this->assertSame(2, TimeRuleResult::query()->count());
        $this->assertTrue(
            AuditLog::query()
                ->where('organization_id', $this->organization->id)
                ->where('event', 'rules.recalculated')
                ->exists(),
        );

        // Wiederholung ersetzt statt zu duplizieren.
        $this->artisan('rules:recalculate', ['--org' => $this->organization->id, '--year' => 2026, '--month' => 1])
            ->assertExitCode(0);
        $this->assertSame(2, TimeRuleResult::query()->count());
    }
}
