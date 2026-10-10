<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContinuedPaymentServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Sickness;

use App\Enums\Sickness\SickLeaveKind;
use App\Models\Absence\SickLeave;
use App\Models\Platform\User;
use App\Services\Sickness\ContinuedPaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ContinuedPaymentServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private ContinuedPaymentService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        config()->set('sickness.continued_pay_weeks', 6);
        config()->set('sickness.chain_reset_after_months', 6);
        $this->service = app(ContinuedPaymentService::class);
    }

    public function test_no_sick_leaves_returns_full_entitlement(): void {
        $status = $this->service->statusFor($this->user, CarbonImmutable::parse('2026-05-18'));

        $this->assertSame(42, $status->entitlementDays);
        $this->assertSame(0, $status->usedDays);
        $this->assertSame(42, $status->remainingDays);
        $this->assertFalse($status->exhausted);
    }

    public function test_initial_episode_counts_calendar_days(): void {
        SickLeave::factory()->create([
            'user_id' => $this->user->id,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-10',
            'kind' => SickLeaveKind::Initial->value,
        ]);

        $status = $this->service->statusFor($this->user, CarbonImmutable::parse('2026-05-10'));

        $this->assertSame(7, $status->usedDays);
        $this->assertSame(35, $status->remainingDays);
        $this->assertFalse($status->exhausted);
    }

    public function test_follow_up_extends_chain(): void {
        $initial = SickLeave::factory()->create([
            'user_id' => $this->user->id,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-10',
            'kind' => SickLeaveKind::Initial->value,
        ]);
        SickLeave::factory()->create([
            'user_id' => $this->user->id,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-17',
            'kind' => SickLeaveKind::FollowUp->value,
            'follow_up_for_id' => $initial->id,
        ]);

        $status = $this->service->statusFor($this->user, CarbonImmutable::parse('2026-05-17'));

        $this->assertSame(14, $status->usedDays);
        $this->assertSame(28, $status->remainingDays);
    }

    public function test_chain_exhausted_after_42_days(): void {
        SickLeave::factory()->create([
            'user_id' => $this->user->id,
            'start_date' => '2026-04-01',
            'end_date' => '2026-05-12',
            'kind' => SickLeaveKind::Initial->value,
        ]);

        $status = $this->service->statusFor($this->user, CarbonImmutable::parse('2026-05-12'));

        $this->assertSame(42, $status->usedDays);
        $this->assertSame(0, $status->remainingDays);
        $this->assertTrue($status->exhausted);
    }

    public function test_new_episode_after_reset_window_starts_fresh(): void {
        SickLeave::factory()->create([
            'user_id' => $this->user->id,
            'start_date' => '2025-01-05',
            'end_date' => '2025-01-12',
            'kind' => SickLeaveKind::Initial->value,
        ]);
        SickLeave::factory()->create([
            'user_id' => $this->user->id,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-06',
            'kind' => SickLeaveKind::Initial->value,
        ]);

        $status = $this->service->statusFor($this->user, CarbonImmutable::parse('2026-05-06'));

        $this->assertSame(3, $status->usedDays);
        $this->assertSame(39, $status->remainingDays);
    }

    public function test_cancelled_sick_leaves_are_ignored(): void {
        SickLeave::factory()->cancelled()->create([
            'user_id' => $this->user->id,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-15',
        ]);

        $status = $this->service->statusFor($this->user, CarbonImmutable::parse('2026-05-15'));

        $this->assertSame(0, $status->usedDays);
        $this->assertSame(42, $status->remainingDays);
    }

    private function leave(string $start, string $end, ?SickLeave $continuationOf = null): SickLeave {
        return SickLeave::factory()->create([
            'user_id' => $this->user->id,
            'start_date' => $start,
            'end_date' => $end,
            'kind' => SickLeaveKind::Initial->value,
            'continuation_of_id' => $continuationOf?->id,
        ]);
    }

    /** MVP-1093: Eine neue Krankheit nach Arbeitstagen gibt einen neuen Anspruch. */
    public function test_new_illness_after_working_days_starts_fresh(): void {
        $this->leave('2026-03-02', '2026-03-13');
        $this->leave('2026-04-06', '2026-04-08');

        $status = $this->service->statusFor($this->user, CarbonImmutable::parse('2026-04-08'));

        $this->assertSame(3, $status->usedDays);
        $this->assertSame('2026-04-06', $status->chainStart?->toDateString());
    }

    /** MVP-1093: Fortsetzungserkrankung zählt nur die Krankheitstage, nicht die Arbeitstage dazwischen. */
    public function test_continuation_adds_only_days_of_incapacity(): void {
        $first = $this->leave('2026-03-02', '2026-03-13');
        $this->leave('2026-04-06', '2026-04-08', continuationOf: $first);

        $status = $this->service->statusFor($this->user, CarbonImmutable::parse('2026-04-08'));

        $this->assertSame(15, $status->usedDays);
        $this->assertSame(27, $status->remainingDays);
        $this->assertSame('2026-03-02', $status->chainStart?->toDateString());
        $this->assertSame('2026-05-05', $status->exhaustionDate?->toDateString(), 'Fortlaufende Arbeitsunfähigkeit ab Stichtag');
    }

    public function test_continuation_exhausts_on_the_42nd_day_of_incapacity(): void {
        $first = $this->leave('2026-01-05', '2026-02-05');
        $this->leave('2026-03-02', '2026-03-20', continuationOf: $first);

        $status = $this->service->statusFor($this->user, CarbonImmutable::parse('2026-03-20'));

        $this->assertTrue($status->exhausted);
        $this->assertSame(32 + 19, $status->usedDays);
        $this->assertSame('2026-03-11', $status->exhaustionDate?->toDateString());
    }

    public function test_continuation_after_six_months_without_this_illness_starts_fresh(): void {
        $first = $this->leave('2026-01-05', '2026-01-20');
        $this->leave('2026-07-21', '2026-07-24', continuationOf: $first);

        $this->assertSame(4, $this->service->statusFor($this->user, CarbonImmutable::parse('2026-07-24'))->usedDays);
    }

    public function test_continuation_twelve_months_after_first_onset_starts_fresh(): void {
        $first = $this->leave('2025-01-06', '2025-01-17');
        $second = $this->leave('2025-06-02', '2025-06-13', continuationOf: $first);
        $third = $this->leave('2025-11-03', '2025-11-14', continuationOf: $second);
        $this->leave('2026-01-12', '2026-01-14', continuationOf: $third);

        $this->assertSame(36, $this->service->statusFor($this->user, CarbonImmutable::parse('2025-11-14'))->usedDays);
        $this->assertSame(3, $this->service->statusFor($this->user, CarbonImmutable::parse('2026-01-14'))->usedDays);
    }

    /** Einheit des Verhinderungsfalls: neue Krankheit während der laufenden verlängert den Fall. */
    public function test_overlapping_new_illness_extends_the_running_case(): void {
        $this->leave('2026-03-02', '2026-03-13');
        $this->leave('2026-03-10', '2026-03-20');

        $this->assertSame(19, $this->service->statusFor($this->user, CarbonImmutable::parse('2026-03-20'))->usedDays);
    }
}
