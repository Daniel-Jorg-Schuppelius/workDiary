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
use App\Models\{SickLeave, User};
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
}
