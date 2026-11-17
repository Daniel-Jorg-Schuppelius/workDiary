<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkDelegationServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Delegation;

use App\Enums\Delegation\WorkDelegationKind;
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\Absence\{SickLeave, Vacation};
use App\Models\Audit\AuditLog;
use App\Models\Delegation\WorkDelegation;
use App\Models\Platform\{Organization, User};
use App\Services\Delegation\{ActsForResolver, WorkDelegationService};
use App\Support\MorphMap;
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vertretung bei Abwesenheit (Feature 202, MVP-1647): Regeln beim Anlegen und
 * die Auflösung „für wen handelt jemand heute“.
 */
final class WorkDelegationServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private WorkDelegationService $service;

    private ActsForResolver $resolver;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->setUpOrganization();
        $this->service = app(WorkDelegationService::class);
        $this->resolver = app(ActsForResolver::class);
    }

    private function day(string $date): CarbonImmutable {
        return CarbonImmutable::parse($date);
    }

    private function delegate(User $represented, User $deputy, string $from = '2026-11-12', ?string $until = '2026-11-20', ?User $actor = null): WorkDelegation {
        return $this->service->create($represented, $deputy, $this->day($from), $until !== null ? $this->day($until) : null, WorkDelegationKind::Other, null, null, $actor ?? $represented);
    }

    /**
     * @param  list<User>  $users
     * @return list<int>
     */
    private function ids(array $users): array {
        return array_map(static fn (User $u): int => (int) $u->id, $users);
    }

    private function assertRejected(callable $create, string $field): void {
        try {
            $create();
            $this->fail('Vertretung hätte abgelehnt werden müssen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_the_deputy_acts_for_the_represented_person_within_the_period_only(): void {
        $anna = $this->orgUser(['name' => 'Anna Beispiel']);
        $ben = $this->orgUser(['name' => 'Ben Muster']);
        $delegation = $this->delegate($anna, $ben, '2026-11-12', '2026-11-20');

        $this->assertSame([(int) $anna->id], $this->resolver->principalIds($ben));
        $this->assertSame([(int) $ben->id], $this->ids($this->resolver->deputiesOf($anna)));
        $this->assertSame([], $this->resolver->principalIds($anna), 'die vertretene Person vertritt niemanden');
        $this->assertSame([(int) $anna->id], $this->resolver->principalIds($ben, $this->day('2026-11-20')), 'letzter Tag zählt');
        $this->assertSame([], $this->resolver->principalIds($ben, $this->day('2026-11-21')), 'endet zum Datum');
        $this->assertSame([], $this->resolver->principalIds($ben, $this->day('2026-11-11')));

        $this->service->end($delegation, $anna);
        $this->assertSame([], $this->resolver->principalIds($ben), 'Beenden wirkt sofort');
        $this->assertTrue($delegation->fresh()?->isEnded());

        $events = AuditLog::query()->withoutGlobalScopes()
            ->where('auditable_type', MorphMap::stableKey(WorkDelegation::class))
            ->where('auditable_id', $delegation->id)
            ->pluck('event')->all();
        $this->assertContains('created', $events);
        $this->assertContains('work_delegation.ended', $events);

        $this->expectException(ValidationException::class);
        $this->service->end($delegation->refresh(), $anna);
    }

    public function test_an_open_ended_delegation_runs_until_ended_and_deactivated_accounts_do_not_count(): void {
        $anna = $this->orgUser();
        $ben = $this->orgUser();
        $this->delegate($anna, $ben, '2026-11-12', null);

        $this->assertSame([(int) $anna->id], $this->resolver->principalIds($ben, $this->day('2027-06-01')));

        $ben->forceFill(['deactivated_at' => now()])->save();
        $this->resolver->flush();
        $this->assertSame([], $this->resolver->deputiesOf($anna));
    }

    public function test_rejects_self_foreign_organization_deactivated_and_past_start(): void {
        $anna = $this->orgUser();
        $ben = $this->orgUser();
        $foreigner = User::factory()->user()->create(['organization_id' => Organization::factory()->create()->id]);
        $gone = $this->orgUser(['deactivated_at' => now()]);

        $this->assertRejected(fn () => $this->delegate($anna, $anna), 'deputy_user_id');
        $this->assertRejected(fn () => $this->delegate($anna, $foreigner), 'deputy_user_id');
        $this->assertRejected(fn () => $this->delegate($anna, $gone), 'deputy_user_id');
        $this->assertRejected(fn () => $this->delegate($anna, $ben, '2026-11-11'), 'valid_from');
        $this->assertRejected(fn () => $this->delegate($anna, $ben, '2026-11-15', '2026-11-14'), 'valid_until');
        $this->assertSame(0, WorkDelegation::query()->count());
    }

    public function test_one_deputy_at_a_time_and_no_chains(): void {
        $anna = $this->orgUser();
        $ben = $this->orgUser();
        $clara = $this->orgUser();
        $dora = $this->orgUser();
        $this->delegate($anna, $ben, '2026-11-12', '2026-11-20');

        // Zweite Vertretung für Anna im selben Zeitraum.
        $this->assertRejected(fn () => $this->delegate($anna, $clara, '2026-11-18', '2026-11-25'), 'valid_from');
        // Ben vertritt — er kann nicht selbst vertreten werden.
        $this->assertRejected(fn () => $this->delegate($ben, $clara, '2026-11-15', null), 'represented_user_id');
        // Anna wird vertreten — sie kann nicht vertreten.
        $this->assertRejected(fn () => $this->delegate($dora, $anna, '2026-11-19', '2026-11-19'), 'deputy_user_id');

        // Ohne Überschneidung geht es.
        $this->delegate($anna, $clara, '2026-11-21', '2026-11-30');
        $this->delegate($ben, $dora, '2026-11-21', null);
        $this->assertSame(3, WorkDelegation::query()->count());
        // Dora vertritt Ben, Ben vertritt niemanden mehr — keine Kette über Ben zu Anna.
        $this->assertSame([(int) $ben->id], $this->resolver->principalIds($dora, $this->day('2026-11-25')));
        $this->assertSame([(int) $clara->id], $this->ids($this->resolver->deputiesOf($anna, $this->day('2026-11-25'))));
    }

    public function test_suggestion_from_an_approved_vacation_uses_the_named_deputy(): void {
        $deputy = $this->orgUser();
        $anna = $this->orgUser(['deputy_user_id' => $deputy->id]);
        $vacation = Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $anna->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-24',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Approved->value,
        ]);

        $suggestion = $this->service->suggestionFor($vacation);
        $this->assertNotNull($suggestion);
        $this->assertSame((int) $deputy->id, $suggestion['deputy_user_id']);
        $this->assertSame('2026-11-12', $suggestion['valid_from']->format('Y-m-d'), 'begonnener Urlaub: ab heute');
        $this->assertSame('2026-11-24', $suggestion['valid_until']->format('Y-m-d'));
        $this->assertSame(WorkDelegationKind::Vacation, $suggestion['kind']);

        $delegation = $this->service->create($anna, $deputy, $suggestion['valid_from'], $suggestion['valid_until'], $suggestion['kind'], $vacation, null, $anna);
        $this->assertSame('vacations', $delegation->absence_type);
        $this->assertSame((int) $vacation->id, $delegation->absence_id);

        $vacation->update(['status' => VacationStatus::Pending]);
        $this->assertNull($this->service->suggestionFor($vacation), 'nur genehmigter Urlaub');
        $this->assertRejected(fn () => $this->service->create($anna, $this->orgUser(), $this->day('2026-11-25'), null, WorkDelegationKind::Vacation, $vacation, null, $anna), 'absence');
    }

    public function test_suggestion_from_a_sick_leave_and_not_from_a_cancelled_one(): void {
        $anna = $this->orgUser();
        $sick = SickLeave::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $anna->id,
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-14',
        ]);

        $suggestion = $this->service->suggestionFor($sick);
        $this->assertNotNull($suggestion);
        $this->assertSame(WorkDelegationKind::Sickness, $suggestion['kind']);
        $this->assertNull($suggestion['deputy_user_id']);

        $sick->update(['cancelled_at' => now()]);
        $this->assertNull($this->service->suggestionFor($sick->fresh() ?? $sick));
    }
}
