<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAccountFrameworkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\TimeAccounts;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Enums\TimeAccount\{CarryoverPolicy, TimeAccountSource, TimeAccountUnit};
use App\Models\{ScheduledShift, ShiftType, TimeAccount, TimeAccountBalance, TimeAccountEntry, User};
use App\Models\Surcharge\{SurchargeRule, TimeRuleResult};
use App\Services\TimeAccount\TimeAccountPostingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Generisches Zeitkonten-Framework (MVP-526): Regel-Posting aus dem
 * Bestand, Idempotenz, Storno/Repost, Kappung, Sonderbuchung, UI.
 */
class TimeAccountFrameworkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->user = $this->orgUser();
    }

    /** @param array<string, mixed> $attributes */
    private function account(array $attributes = []): TimeAccount {
        return TimeAccount::create(array_merge([
            'organization_id' => $this->organization->id,
            'code' => 'nightshift',
            'name' => 'Nachtstunden-Konto',
            'unit' => TimeAccountUnit::Minutes->value,
            'carryover_policy' => CarryoverPolicy::Carry->value,
            'is_active' => true,
        ], $attributes));
    }

    private function nightResult(string $date, int $minutes): TimeRuleResult {
        $rule = SurchargeRule::query()->first() ?? SurchargeRule::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        return TimeRuleResult::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'surcharge_rule_id' => $rule->id,
            'date' => $date,
            'minutes' => $minutes,
            'wage_type_code' => 'surcharge.night',
            'percentage' => '25.00',
            'calculation_snapshot' => [],
        ]);
    }

    private function runPosting(): array {
        return app(TimeAccountPostingService::class)->postRange(
            $this->organization,
            CarbonImmutable::parse('2026-05-01'),
            CarbonImmutable::parse('2026-06-30'),
        );
    }

    public function test_wage_type_rule_posts_with_factor_and_is_idempotent(): void {
        $account = $this->account();
        $account->rules()->create(['source_type' => TimeAccountSource::WageType->value, 'match_value' => 'surcharge.night*', 'factor' => 1.25]);
        $this->nightResult('2026-06-10', 120);

        $stats = $this->runPosting();
        $this->assertSame(1, $stats['posted']);

        $entry = TimeAccountEntry::query()->firstOrFail();
        $this->assertSame('150.00', (string) $entry->quantity); // 120 × 1,25
        $this->assertSame($this->user->id, $entry->user_id);

        // Zweiter Lauf bucht nichts doppelt.
        $stats = $this->runPosting();
        $this->assertSame(0, $stats['posted']);
        $this->assertSame(1, TimeAccountEntry::query()->count());

        // Monatsstand materialisiert.
        $balance = TimeAccountBalance::query()->firstOrFail();
        $this->assertSame(2026, $balance->year);
        $this->assertSame(6, $balance->month);
        $this->assertSame('150.00', (string) $balance->balance);
    }

    public function test_shift_type_counter_counts_only_worked_shifts(): void {
        $type = ShiftType::create([
            'organization_id' => $this->organization->id,
            'name' => 'Nachtdienst',
            'abbreviation' => 'N1',
            'is_active' => true,
        ]);
        $account = $this->account(['code' => 'nights', 'unit' => TimeAccountUnit::Count->value]);
        $account->rules()->create(['source_type' => TimeAccountSource::ShiftTypeCount->value, 'match_value' => (string) $type->id, 'factor' => 1]);

        foreach (['2026-06-10', '2026-06-12', '2026-06-20' /* Zukunft */] as $date) {
            ScheduledShift::create([
                'organization_id' => $this->organization->id,
                'user_id' => $this->user->id,
                'shift_type_id' => $type->id,
                'date' => $date,
                'status' => ScheduledShiftStatus::Published->value,
            ]);
        }

        $stats = $this->runPosting();

        // Nur die zwei geleisteten (vergangenen) Dienste zählen.
        $this->assertSame(2, $stats['posted']);
        $this->assertSame(2.0, (float) TimeAccountEntry::query()->sum('quantity'));
    }

    public function test_reversal_frees_slot_for_repost(): void {
        $account = $this->account();
        $account->rules()->create(['source_type' => TimeAccountSource::WageType->value, 'match_value' => 'surcharge.*', 'factor' => 1]);
        $this->nightResult('2026-06-10', 60);
        $this->runPosting();

        $entry = TimeAccountEntry::query()->firstOrFail();
        $admin = $this->orgAdmin();
        app(TimeAccountPostingService::class)->reverseEntry($entry, $admin, 'Falsch berechnet');

        $this->assertSame(2, TimeAccountEntry::query()->count());
        $this->assertSame(0.0, (float) TimeAccountEntry::query()->sum('quantity'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'timeAccount.reversed']);

        // Repost bucht den Slot erneut.
        $stats = $this->runPosting();
        $this->assertSame(1, $stats['posted']);
        $this->assertSame(60.0, (float) TimeAccountEntry::query()->sum('quantity'));
    }

    public function test_monthly_cap_creates_capping_entry(): void {
        $account = $this->account([
            'code' => 'freizeit',
            'carryover_policy' => CarryoverPolicy::Cap->value,
            'cap_amount' => 100,
        ]);
        $account->rules()->create(['source_type' => TimeAccountSource::WageType->value, 'match_value' => 'surcharge.*', 'factor' => 1]);
        // Vormonat Mai: 180 Minuten > Kappungsgrenze 100.
        $this->nightResult('2026-05-20', 180);

        $stats = $this->runPosting();

        $this->assertSame(1, $stats['posted']);
        $this->assertSame(1, $stats['capped']);
        $cap = TimeAccountEntry::query()
            ->where('source_type', TimeAccountPostingService::SOURCE_CAP)
            ->firstOrFail();
        $this->assertSame('-80.00', (string) $cap->quantity);
        $this->assertSame('2026-05-31', $cap->booking_date->toDateString());

        // Idempotent: zweiter Lauf kappt nicht erneut.
        $stats = $this->runPosting();
        $this->assertSame(0, $stats['capped']);
    }

    public function test_manual_entry_requires_admin_and_is_audited(): void {
        $account = $this->account();
        $admin = $this->orgAdmin();

        $this->actingAs($this->user)
            ->post(route('admin.time-accounts.manual', $account), [
                'user_id' => $this->user->sqid,
                'booking_date' => '2026-06-01',
                'quantity' => 30,
                'note' => 'Testbuchung',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.time-accounts.manual', $account), [
                'user_id' => $this->user->sqid,
                'booking_date' => '2026-06-01',
                'quantity' => 30,
                'note' => 'Prämienstunden Q2',
            ])
            ->assertRedirect();

        $this->assertSame(1, TimeAccountEntry::query()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'timeAccount.manualEntry']);
    }

    public function test_member_page_shows_balance_and_journal(): void {
        $account = $this->account();
        $account->rules()->create(['source_type' => TimeAccountSource::WageType->value, 'match_value' => 'surcharge.*', 'factor' => 1]);
        $this->nightResult('2026-06-10', 90);
        $this->runPosting();

        $this->actingAs($this->user)
            ->get(route('time-accounts.index'))
            ->assertOk()
            ->assertSee('Nachtstunden-Konto')
            ->assertSee('1:30');

        $this->actingAs($this->user)
            ->get(route('time-accounts.index', ['account' => \App\Support\Sqid::encode(TimeAccount::class, (int) $account->id)]))
            ->assertOk()
            ->assertSee(__('Journal'));
    }

    public function test_report_shows_opening_turnover_closing(): void {
        $account = $this->account();
        $account->rules()->create(['source_type' => TimeAccountSource::WageType->value, 'match_value' => 'surcharge.*', 'factor' => 1]);
        $this->nightResult('2026-05-10', 60);  // vor Zeitraum
        $this->nightResult('2026-06-10', 120); // im Zeitraum
        $this->runPosting();

        $admin = $this->orgAdmin();
        $response = $this->actingAs($admin)
            ->get(route('reports.time-accounts', ['from' => '2026-06-01', 'to' => '2026-06-30', 'export' => 'csv']))
            ->assertOk();

        $csv = (string) $response->getContent();
        $this->assertStringContainsString($this->user->name, $csv);
        $this->assertStringContainsString('60.00', $csv);  // Anfangsstand
        $this->assertStringContainsString('120.00', $csv); // Umsatz
        $this->assertStringContainsString('180.00', $csv); // Endstand
    }

    public function test_org_isolation_on_admin_page(): void {
        $foreign = \App\Models\Organization::factory()->create();
        TimeAccount::query()->create([
            'organization_id' => $foreign->id,
            'code' => 'fremd',
            'name' => 'Fremdkonto',
            'unit' => TimeAccountUnit::Minutes->value,
            'carryover_policy' => CarryoverPolicy::Carry->value,
            'is_active' => true,
        ]);
        $this->account();

        $this->actingAs($this->orgAdmin())
            ->get(route('admin.time-accounts.index'))
            ->assertOk()
            ->assertSee('Nachtstunden-Konto')
            ->assertDontSee('Fremdkonto');
    }
}
